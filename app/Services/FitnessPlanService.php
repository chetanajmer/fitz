<?php

namespace App\Services;

use App\Enums\FitnessPlanType;
use App\Models\FitnessPlan;
use App\Models\FitnessPlanSection;
use App\Models\Member;
use App\Models\PlanCategory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Diet charts & workout plans: categories, default plans (all members) and customised
 * plans (one member). Images are stored on the public disk under gyms/{id}/plans.
 */
class FitnessPlanService
{
    // ---- Member view ----------------------------------------------------------------

    /**
     * What a member sees: their customised plans first, then published default plans
     * grouped by active category (plus uncategorised defaults).
     *
     * @return array{custom: Collection, categories: Collection, uncategorized: Collection}
     */
    public function libraryFor(Member $member, FitnessPlanType $type): array
    {
        $plans = FitnessPlan::ofType($type)
            ->visibleToMember($member)
            ->with('category')
            ->withCount('sections')
            ->orderBy('sort_order')->latest('updated_at')
            ->get();

        $defaults = $plans->whereNull('member_id');

        $categories = PlanCategory::ofType($type)->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn (PlanCategory $c) => $c->setRelation('plans', $defaults->where('plan_category_id', $c->id)->values()))
            ->filter(fn (PlanCategory $c) => $c->plans->isNotEmpty())
            ->values();

        $activeCategoryIds = $categories->pluck('id')->all();

        return [
            'custom' => $plans->whereNotNull('member_id')->values(),
            'categories' => $categories,
            // Defaults without a category, or whose category is hidden.
            'uncategorized' => $defaults->filter(fn (FitnessPlan $p) => ! in_array($p->plan_category_id, $activeCategoryIds, true))->values(),
        ];
    }

    /** A single plan the member is allowed to open (published default or their own custom plan). */
    public function findForMember(Member $member, int $planId): ?FitnessPlan
    {
        return FitnessPlan::visibleToMember($member)->with(['sections', 'category'])->find($planId);
    }

    // ---- Categories ---------------------------------------------------------------

    /** @param array{name:string, description?:?string, sort_order?:int, is_active?:bool} $data */
    public function saveCategory(?PlanCategory $category, FitnessPlanType $type, array $data, ?UploadedFile $image = null, bool $removeImage = false): PlanCategory
    {
        $category ??= new PlanCategory(['type' => $type]);
        $category->fill([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        if ($removeImage || $image) {
            $this->deleteFile($category->image_path);
            $category->image_path = null;
        }
        if ($image) {
            $category->image_path = $image->store($this->dir(), 'public');
        }

        $category->save();

        return $category;
    }

    public function deleteCategory(PlanCategory $category): void
    {
        // Plans keep existing; they simply become uncategorised (FK nullOnDelete).
        $this->deleteFile($category->image_path);
        $category->delete();
    }

    // ---- Plans ----------------------------------------------------------------------

    /**
     * Create or update a plan with its sections.
     *
     * @param  array{title:string, summary?:?string, plan_category_id?:?int, level?:?string, duration_weeks?:?int, daily_calories?:?int, is_published?:bool, sort_order?:int}  $data
     * @param  list<array{id?:?int, title:string, subtitle?:?string, body?:?string, remove_image?:bool, upload?:?UploadedFile}>  $sections
     */
    public function savePlan(?FitnessPlan $plan, FitnessPlanType $type, array $data, array $sections, ?Member $member = null, ?UploadedFile $cover = null, bool $removeCover = false): FitnessPlan
    {
        $categoryId = $data['plan_category_id'] ?? null;
        if ($categoryId && ! PlanCategory::ofType($type)->whereKey($categoryId)->exists()) {
            throw ValidationException::withMessages(['form.plan_category_id' => 'Choose a valid category.']);
        }

        return DB::transaction(function () use ($plan, $type, $data, $sections, $member, $cover, $removeCover, $categoryId) {
            $isNew = $plan === null;
            $plan ??= new FitnessPlan(['type' => $type]);

            $plan->fill([
                'title' => $data['title'],
                'summary' => $data['summary'] ?? null,
                'plan_category_id' => $categoryId ?: null,
                'level' => $data['level'] ?? null,
                'duration_weeks' => $data['duration_weeks'] ?? null,
                'daily_calories' => $type === FitnessPlanType::Diet ? ($data['daily_calories'] ?? null) : null,
                'is_published' => (bool) ($data['is_published'] ?? true),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ]);

            if ($isNew) {
                $plan->member_id = $member?->id;
                $plan->created_by = Auth::guard('web')->id();
            }
            $plan->updated_by = Auth::guard('web')->id();

            if ($removeCover || $cover) {
                $this->deleteFile($plan->cover_image_path);
                $plan->cover_image_path = null;
            }
            if ($cover) {
                $plan->cover_image_path = $cover->store($this->dir(), 'public');
            }

            $plan->save();
            $this->syncSections($plan, $sections);

            return $plan->load('sections');
        });
    }

    /** Copy a plan (e.g. a default chart) into a customised plan for one member. */
    public function copyForMember(FitnessPlan $source, Member $member): FitnessPlan
    {
        return DB::transaction(function () use ($source, $member) {
            $copy = $source->replicate(['member_id', 'cover_image_path', 'created_by', 'updated_by', 'deleted_at', 'library_key']);
            $copy->member_id = $member->id;
            $copy->copied_from_id = $source->id;
            $copy->title = $source->title;
            $copy->cover_image_path = $this->copyFile($source->cover_image_path);
            $copy->created_by = Auth::guard('web')->id();
            $copy->updated_by = Auth::guard('web')->id();
            $copy->save();

            foreach ($source->sections as $section) {
                $new = $section->replicate(['fitness_plan_id', 'image_path']);
                $new->fitness_plan_id = $copy->id;
                $new->image_path = $this->copyFile($section->image_path);
                $new->save();
            }

            return $copy->load('sections');
        });
    }

    public function deletePlan(FitnessPlan $plan): void
    {
        DB::transaction(function () use ($plan) {
            foreach ($plan->sections as $section) {
                $this->deleteFile($section->image_path);
            }
            $this->deleteFile($plan->cover_image_path);
            $plan->sections()->delete();
            $plan->delete();
        });
    }

    /** @param list<array<string, mixed>> $sections */
    protected function syncSections(FitnessPlan $plan, array $sections): void
    {
        $existing = $plan->sections()->get()->keyBy('id');
        $keep = [];

        foreach (array_values($sections) as $order => $input) {
            $section = (! empty($input['id']) && $existing->has($input['id']))
                ? $existing->get($input['id'])
                : new FitnessPlanSection;

            $section->fill([
                'title' => $input['title'],
                'subtitle' => $input['subtitle'] ?? null,
                'body' => $input['body'] ?? null,
                'sort_order' => $order,
            ]);
            $section->fitness_plan_id = $plan->id;

            $upload = $input['upload'] ?? null;
            if (! empty($input['remove_image']) || $upload instanceof UploadedFile) {
                $this->deleteFile($section->image_path);
                $section->image_path = null;
            }
            if ($upload instanceof UploadedFile) {
                $section->image_path = $upload->store($this->dir(), 'public');
            }

            $section->save();
            $keep[] = $section->id;
        }

        foreach ($existing->except($keep) as $removed) {
            $this->deleteFile($removed->image_path);
            $removed->delete();
        }
    }

    protected function dir(): string
    {
        return 'gyms/'.current_gym()->id.'/plans';
    }

    protected function deleteFile(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    protected function copyFile(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $new = $this->dir().'/'.uniqid('copy_', true).'.'.pathinfo($path, PATHINFO_EXTENSION);
        Storage::disk('public')->copy($path, $new);

        return $new;
    }
}
