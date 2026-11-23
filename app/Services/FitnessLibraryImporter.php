<?php

namespace App\Services;

use App\Enums\FitnessPlanType;
use App\Models\FitnessPlan;
use App\Models\LibraryCategory;
use App\Models\LibraryPlan;
use App\Models\PlanCategory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Imports the Gymai default fitness library (edited by the Super Admin in Platform →
 * Default Library) into the current gym. Idempotent via library_key: importing again
 * only adds missing plans and fills empty photo slots — it never duplicates content or
 * overwrites what the gym changed. Gyms receive their own copies of the photos.
 */
class FitnessLibraryImporter
{
    /** @var array<string, list<array<string, mixed>>> */
    protected array $cache = [];

    /**
     * Published library content for a type, as plain arrays.
     *
     * @return list<array{key:string, name:string, description:?string, image_path:?string, plans:list<array<string, mixed>>}>
     */
    public function categories(FitnessPlanType $type): array
    {
        return $this->cache[$type->value] ??= LibraryCategory::where('type', $type->value)
            ->with(['plans' => fn ($q) => $q->where('is_published', true)->with('sections')])
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->filter(fn (LibraryCategory $c) => $c->plans->isNotEmpty())
            ->map(fn (LibraryCategory $c) => [
                'key' => $c->key,
                'name' => $c->name,
                'description' => $c->description,
                'image_path' => $c->image_path,
                'plans' => $c->plans->map(fn (LibraryPlan $p) => [
                    'key' => $p->key,
                    'title' => $p->title,
                    'summary' => $p->summary,
                    'level' => $p->level,
                    'duration_weeks' => $p->duration_weeks,
                    'daily_calories' => $p->daily_calories,
                    'cover_image_path' => $p->cover_image_path,
                    'sections' => $p->sections->map(fn ($s) => [
                        'title' => $s->title, 'subtitle' => $s->subtitle, 'body' => $s->body, 'image_path' => $s->image_path,
                    ])->all(),
                ])->all(),
            ])
            ->values()
            ->all();
    }

    /** "Version" shown to gyms = when the library was last edited. */
    public function version(): string
    {
        $latest = max((string) LibraryPlan::max('updated_at'), (string) LibraryCategory::max('updated_at'));

        return $latest ? Carbon::parse($latest)->format('d M Y') : '—';
    }

    /**
     * @return list<array{key:string, name:string, description:?string, plans:int, imported:int, titles:list<string>, photos:int}>
     */
    public function preview(FitnessPlanType $type): array
    {
        $existing = FitnessPlan::ofType($type)->defaults()->whereNotNull('library_key')->pluck('library_key')->all();

        return array_map(fn (array $category) => [
            'key' => $category['key'],
            'name' => $category['name'],
            'description' => $category['description'],
            'plans' => count($category['plans']),
            'imported' => count(array_intersect(array_column($category['plans'], 'key'), $existing)),
            'titles' => array_column($category['plans'], 'title'),
            'photos' => $this->photoCount($category),
        ], $this->categories($type));
    }

    /**
     * @param  list<string>|null  $categoryKeys  null = everything
     * @return array{categories_created:int, categories_reused:int, plans_created:int, plans_skipped:int, images_added:int}
     */
    public function import(FitnessPlanType $type, ?array $categoryKeys = null, bool $publish = true): array
    {
        $result = ['categories_created' => 0, 'categories_reused' => 0, 'plans_created' => 0, 'plans_skipped' => 0, 'images_added' => 0];
        $userId = Auth::guard('web')->id();
        unset($this->cache[$type->value]);

        DB::transaction(function () use ($type, $categoryKeys, $publish, $userId, &$result) {
            $existingPlans = FitnessPlan::ofType($type)->defaults()->whereNotNull('library_key')->with('sections')->get()->keyBy('library_key');
            $nextOrder = (int) PlanCategory::ofType($type)->max('sort_order');

            foreach ($this->categories($type) as $data) {
                if ($categoryKeys !== null && ! in_array($data['key'], $categoryKeys, true)) {
                    continue;
                }

                $category = $this->resolveCategory($type, $data, $nextOrder, $result);

                if (! $category->image_path && ($path = $this->copyToGym($data['image_path'], $category->gym_id))) {
                    $category->image_path = $path;
                    $category->save();
                    $result['images_added']++;
                }

                foreach ($data['plans'] as $order => $planData) {
                    if ($existing = $existingPlans->get($planData['key'])) {
                        $result['images_added'] += $this->fillMissingImages($existing, $planData);
                        $result['plans_skipped']++;

                        continue;
                    }

                    $plan = new FitnessPlan([
                        'type' => $type,
                        'plan_category_id' => $category->id,
                        'title' => $planData['title'],
                        'summary' => $planData['summary'],
                        'level' => $planData['level'],
                        'duration_weeks' => $planData['duration_weeks'],
                        'daily_calories' => $type === FitnessPlanType::Diet ? $planData['daily_calories'] : null,
                        'is_published' => $publish,
                        'sort_order' => $order,
                    ]);
                    $plan->library_key = $planData['key'];
                    $plan->created_by = $userId;
                    $plan->updated_by = $userId;
                    if ($cover = $this->copyToGym($planData['cover_image_path'], $category->gym_id)) {
                        $plan->cover_image_path = $cover;
                        $result['images_added']++;
                    }
                    $plan->save();

                    foreach ($planData['sections'] as $i => $section) {
                        $image = $this->copyToGym($section['image_path'], $plan->gym_id);
                        $plan->sections()->create([
                            'title' => $section['title'],
                            'subtitle' => $section['subtitle'],
                            'body' => $section['body'],
                            'image_path' => $image,
                            'sort_order' => $i,
                        ]);
                        $result['images_added'] += $image ? 1 : 0;
                    }

                    $result['plans_created']++;
                }
            }
        });

        return $result;
    }

    /** Fill empty photo slots of an already-imported plan. Sections are matched by title. */
    protected function fillMissingImages(FitnessPlan $plan, array $planData): int
    {
        $added = 0;

        if (! $plan->cover_image_path && ($cover = $this->copyToGym($planData['cover_image_path'], $plan->gym_id))) {
            $plan->cover_image_path = $cover;
            $plan->save();
            $added++;
        }

        foreach ($planData['sections'] as $section) {
            if (! $section['image_path']) {
                continue;
            }

            $target = $plan->sections->first(fn ($s) => ! $s->image_path && Str::lower(trim($s->title)) === Str::lower(trim($section['title'])));

            if ($target && ($path = $this->copyToGym($section['image_path'], $plan->gym_id))) {
                $target->image_path = $path;
                $target->save();
                $added++;
            }
        }

        return $added;
    }

    /** Copy a library photo into the gym's own folder. */
    protected function copyToGym(?string $path, int $gymId): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $target = "gyms/{$gymId}/plans/".uniqid('lib_', true).'.'.pathinfo($path, PATHINFO_EXTENSION);
        Storage::disk('public')->copy($path, $target);

        return $target;
    }

    protected function photoCount(array $category): int
    {
        $count = $category['image_path'] ? 1 : 0;
        foreach ($category['plans'] as $plan) {
            $count += ($plan['cover_image_path'] ? 1 : 0) + count(array_filter(array_column($plan['sections'], 'image_path')));
        }

        return $count;
    }

    /** Find by library key, else reuse a same-named category the gym already made, else create. */
    protected function resolveCategory(FitnessPlanType $type, array $data, int &$nextOrder, array &$result): PlanCategory
    {
        $category = PlanCategory::ofType($type)->where('library_key', $data['key'])->first()
            ?? PlanCategory::ofType($type)->whereRaw('LOWER(name) = ?', [Str::lower($data['name'])])->first();

        if ($category) {
            if (! $category->library_key) {
                $category->library_key = $data['key'];
                $category->save();
            }
            $result['categories_reused']++;

            return $category;
        }

        $category = new PlanCategory([
            'type' => $type,
            'name' => $data['name'],
            'description' => $data['description'],
            'sort_order' => ++$nextOrder,
            'is_active' => true,
        ]);
        $category->library_key = $data['key'];
        $category->save();
        $result['categories_created']++;

        return $category;
    }
}
