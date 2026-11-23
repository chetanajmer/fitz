<?php

namespace App\Services;

use App\Enums\FitnessPlanType;
use App\Models\LibraryCategory;
use App\Models\LibraryPlan;
use App\Models\LibrarySection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Super Admin editing of the default fitness library. Library photos live under
 * storage "library/". Gyms hold their own copies, so edits and deletions here never
 * change content a gym has already imported.
 */
class FitnessLibraryService
{
    /** @param array{name:string, description?:?string} $data */
    public function saveCategory(?LibraryCategory $category, FitnessPlanType $type, array $data, ?UploadedFile $image = null, bool $removeImage = false): LibraryCategory
    {
        if (! $category) {
            $category = new LibraryCategory;
            $category->type = $type;
            $category->key = $this->uniqueKey(LibraryCategory::class, $type->value.'-'.$data['name']);
            $category->sort_order = (int) LibraryCategory::where('type', $type->value)->max('sort_order') + 1;
        }

        $category->fill(['name' => $data['name'], 'description' => $data['description'] ?? null]);
        $category->image_path = $this->replaceFile($category->image_path, $image, $removeImage);
        $category->save();

        return $category;
    }

    public function deleteCategory(LibraryCategory $category): void
    {
        DB::transaction(function () use ($category) {
            foreach ($category->plans as $plan) {
                $this->deletePlan($plan);
            }
            $this->deleteFile($category->image_path);
            $category->delete();
        });
    }

    public function moveCategory(LibraryCategory $category, int $direction): void
    {
        $this->swapOrder(LibraryCategory::where('type', $category->type->value)->orderBy('sort_order')->orderBy('id')->get(), $category, $direction);
    }

    /**
     * @param  array{title:string, summary?:?string, library_category_id:int, level?:?string, duration_weeks?:?int, daily_calories?:?int, is_published?:bool}  $data
     * @param  list<array{id?:?int, title:string, subtitle?:?string, body?:?string, remove_image?:bool, upload?:?UploadedFile}>  $sections
     */
    public function savePlan(?LibraryPlan $plan, FitnessPlanType $type, array $data, array $sections, ?UploadedFile $cover = null, bool $removeCover = false): LibraryPlan
    {
        return DB::transaction(function () use ($plan, $type, $data, $sections, $cover, $removeCover) {
            $category = LibraryCategory::where('type', $type->value)->findOrFail($data['library_category_id']);

            if (! $plan) {
                $plan = new LibraryPlan;
                $plan->type = $type;
                $plan->key = $this->uniqueKey(LibraryPlan::class, $type->value.'-'.$data['title']);
                $plan->sort_order = (int) $category->plans()->reorder()->max('sort_order') + 1;
            }

            $plan->fill([
                'library_category_id' => $category->id,
                'title' => $data['title'],
                'summary' => $data['summary'] ?? null,
                'level' => $data['level'] ?? null,
                'duration_weeks' => $data['duration_weeks'] ?? null,
                'daily_calories' => $type === FitnessPlanType::Diet ? ($data['daily_calories'] ?? null) : null,
                'is_published' => (bool) ($data['is_published'] ?? true),
            ]);
            $plan->cover_image_path = $this->replaceFile($plan->cover_image_path, $cover, $removeCover);
            $plan->updated_by = Auth::guard('web')->id();
            $plan->save();

            $this->syncSections($plan, $sections);

            return $plan->load('sections');
        });
    }

    public function duplicatePlan(LibraryPlan $source): LibraryPlan
    {
        return DB::transaction(function () use ($source) {
            $copy = $source->replicate(['key', 'cover_image_path']);
            $copy->key = $this->uniqueKey(LibraryPlan::class, $source->type->value.'-'.$source->title.'-copy');
            $copy->title = $source->title.' (copy)';
            $copy->is_published = false;
            $copy->cover_image_path = $this->copyFile($source->cover_image_path);
            $copy->updated_by = Auth::guard('web')->id();
            $copy->save();

            foreach ($source->sections as $section) {
                $new = $section->replicate(['library_plan_id', 'image_path']);
                $new->library_plan_id = $copy->id;
                $new->image_path = $this->copyFile($section->image_path);
                $new->save();
            }

            return $copy;
        });
    }

    public function deletePlan(LibraryPlan $plan): void
    {
        DB::transaction(function () use ($plan) {
            foreach ($plan->sections as $section) {
                $this->deleteFile($section->image_path);
            }
            $this->deleteFile($plan->cover_image_path);
            $plan->delete();
        });
    }

    public function movePlan(LibraryPlan $plan, int $direction): void
    {
        $this->swapOrder(LibraryPlan::where('library_category_id', $plan->library_category_id)->orderBy('sort_order')->orderBy('id')->get(), $plan, $direction);
    }

    /** @param list<array<string, mixed>> $sections */
    protected function syncSections(LibraryPlan $plan, array $sections): void
    {
        $existing = $plan->sections()->get()->keyBy('id');
        $keep = [];

        foreach (array_values($sections) as $order => $input) {
            $section = (! empty($input['id']) && $existing->has($input['id'])) ? $existing->get($input['id']) : new LibrarySection;
            $section->fill([
                'title' => $input['title'],
                'subtitle' => $input['subtitle'] ?? null,
                'body' => $input['body'] ?? null,
                'sort_order' => $order,
            ]);
            $section->library_plan_id = $plan->id;
            $section->image_path = $this->replaceFile($section->image_path, $input['upload'] ?? null, (bool) ($input['remove_image'] ?? false));
            $section->save();
            $keep[] = $section->id;
        }

        foreach ($existing->except($keep) as $removed) {
            $this->deleteFile($removed->image_path);
            $removed->delete();
        }
    }

    protected function replaceFile(?string $current, mixed $upload, bool $remove): ?string
    {
        if ($upload instanceof UploadedFile) {
            $this->deleteFile($current);

            return $upload->store('library', 'public');
        }

        if ($remove) {
            $this->deleteFile($current);

            return null;
        }

        return $current;
    }

    protected function swapOrder($items, $item, int $direction): void
    {
        $items = $items->values();
        $index = $items->search(fn ($i) => $i->id === $item->id);
        $target = $index + ($direction < 0 ? -1 : 1);

        if ($index === false || ! isset($items[$target])) {
            return;
        }

        // Normalise ordering first, then swap.
        $items->each(fn ($i, $pos) => $i->sort_order === $pos ? null : $i->forceFill(['sort_order' => $pos])->save());
        $items[$index]->forceFill(['sort_order' => $target])->save();
        $items[$target]->forceFill(['sort_order' => $index])->save();
    }

    protected function uniqueKey(string $model, string $base): string
    {
        $slug = Str::limit(Str::slug($base), 70, '');
        $key = $slug;
        $i = 2;
        while ($model::where('key', $key)->exists()) {
            $key = $slug.'-'.$i++;
        }

        return $key;
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

        $new = 'library/'.uniqid('copy_', true).'.'.pathinfo($path, PATHINFO_EXTENSION);
        Storage::disk('public')->copy($path, $new);

        return $new;
    }
}
