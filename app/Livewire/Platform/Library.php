<?php

namespace App\Livewire\Platform;

use App\Enums\FitnessPlanType;
use App\Models\FitnessPlan;
use App\Models\LibraryCategory;
use App\Models\LibraryPlan;
use App\Services\FitnessLibraryService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Super Admin: edit the Fitnix default diet & workout library that gyms can import.
 * Gyms keep their own imported copies, so edits here affect future imports only
 * (plus empty photo slots being filled on re-import).
 */
#[Layout('components.layouts.platform')]
#[Title('Default library')]
class Library extends Component
{
    use WithFileUploads;

    #[Url(except: 'diet')]
    public string $type = 'diet';

    /** Selected library plan id ('' = none, 'new' = creating). */
    #[Url(except: '')]
    public string $plan = '';

    // ---- Plan editor state ----
    /** @var array<string, mixed> */
    public array $form = [];

    /** @var list<array{key:string, id:?int, title:string, subtitle:string, body:string, image_url:?string, remove_image:bool}> */
    public array $sections = [];

    public array $uploads = [];

    public $cover = null;

    public bool $removeCover = false;

    // ---- Category modal ----
    public bool $showCategory = false;

    #[Locked]
    public ?int $categoryId = null;

    /** @var array<string, mixed> */
    public array $categoryForm = [];

    public $categoryImage = null;

    public function mount(): void
    {
        $this->type = in_array($this->type, FitnessPlanType::values(), true) ? $this->type : 'diet';

        if ($this->plan !== '' && $this->plan !== 'new' && $this->loadPlan((int) $this->plan)) {
            return;
        }

        $first = LibraryPlan::where('library_plans.type', $this->type)->join('library_categories', 'library_categories.id', '=', 'library_plans.library_category_id')
            ->orderBy('library_categories.sort_order')->orderBy('library_plans.sort_order')->value('library_plans.id');
        $first ? $this->loadPlan($first) : $this->plan = '';
    }

    public function planType(): FitnessPlanType
    {
        return FitnessPlanType::from($this->type);
    }

    public function updatedType(): void
    {
        $this->plan = '';
        $this->mount();
    }

    // ---- Plan editor ----------------------------------------------------------

    public function selectPlan(int $id): void
    {
        $this->loadPlan($id);
    }

    protected function loadPlan(int $id): bool
    {
        $plan = LibraryPlan::where('type', $this->type)->with('sections')->find($id);

        if (! $plan) {
            return false;
        }

        $this->resetErrorBag();
        $this->reset('uploads', 'cover', 'removeCover');
        $this->plan = (string) $plan->id;
        $this->form = [
            'title' => $plan->title, 'summary' => (string) $plan->summary, 'library_category_id' => (string) $plan->library_category_id,
            'level' => (string) $plan->level, 'duration_weeks' => (string) $plan->duration_weeks,
            'daily_calories' => (string) $plan->daily_calories, 'is_published' => $plan->is_published,
        ];
        $this->sections = $plan->sections->map(fn ($s) => [
            'key' => (string) Str::uuid(), 'id' => $s->id, 'title' => $s->title, 'subtitle' => (string) $s->subtitle,
            'body' => (string) $s->body, 'image_url' => $s->imageUrl(), 'remove_image' => false,
        ])->all();

        return true;
    }

    public function newPlan(int $categoryId): void
    {
        $category = LibraryCategory::where('type', $this->type)->findOrFail($categoryId);
        $this->resetErrorBag();
        $this->reset('uploads', 'cover', 'removeCover');
        $this->plan = 'new';
        $this->form = [
            'title' => '', 'summary' => '', 'library_category_id' => (string) $category->id, 'level' => '',
            'duration_weeks' => '', 'daily_calories' => '', 'is_published' => false,
        ];
        $this->sections = [];
        $this->addSection();
    }

    public function addSection(): void
    {
        $this->sections[] = ['key' => (string) Str::uuid(), 'id' => null, 'title' => '', 'subtitle' => '', 'body' => '', 'image_url' => null, 'remove_image' => false];
    }

    public function removeSection(string $key): void
    {
        $this->sections = array_values(array_filter($this->sections, fn ($s) => $s['key'] !== $key));
        unset($this->uploads[$key]);
    }

    public function moveSection(string $key, int $direction): void
    {
        $index = collect($this->sections)->search(fn ($s) => $s['key'] === $key);
        $target = $index + ($direction < 0 ? -1 : 1);
        if ($index !== false && isset($this->sections[$target])) {
            [$this->sections[$index], $this->sections[$target]] = [$this->sections[$target], $this->sections[$index]];
        }
    }

    public function removeSectionImage(string $key): void
    {
        foreach ($this->sections as &$section) {
            if ($section['key'] === $key) {
                $section['remove_image'] = true;
                $section['image_url'] = null;
            }
        }
        unset($this->uploads[$key]);
    }

    public function savePlan(FitnessLibraryService $library): void
    {
        $this->validate([
            'form.title' => 'required|string|max:150',
            'form.summary' => 'nullable|string|max:1000',
            'form.library_category_id' => ['required', Rule::exists('library_categories', 'id')->where('type', $this->type)],
            'form.level' => ['nullable', Rule::in(array_keys(FitnessPlan::LEVELS))],
            'form.duration_weeks' => 'nullable|integer|min:1|max:104',
            'form.daily_calories' => 'nullable|integer|min:300|max:10000',
            'form.is_published' => 'boolean',
            'sections' => 'required|array|min:1|max:60',
            'sections.*.title' => 'required|string|max:150',
            'sections.*.subtitle' => 'nullable|string|max:150',
            'sections.*.body' => 'nullable|string|max:5000',
            'uploads.*' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'cover' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ], [
            'sections.required' => 'Add at least one section.',
            'sections.*.title.required' => 'Each section needs a title.',
        ], ['form.title' => 'title', 'form.library_category_id' => 'category']);

        $existing = $this->plan !== 'new' ? LibraryPlan::where('type', $this->type)->findOrFail((int) $this->plan) : null;

        $saved = $library->savePlan(
            $existing,
            $this->planType(),
            array_map(fn ($v) => $v === '' ? null : $v, $this->form),
            array_map(fn ($s) => [
                'id' => $s['id'], 'title' => $s['title'], 'subtitle' => $s['subtitle'] ?: null, 'body' => $s['body'] ?: null,
                'remove_image' => $s['remove_image'], 'upload' => $this->uploads[$s['key']] ?? null,
            ], $this->sections),
            $this->cover,
            $this->removeCover,
        );

        $this->loadPlan($saved->id);
        $this->dispatch('notify', message: 'Saved. Gyms get this version on their next import.');
    }

    public function duplicatePlan(FitnessLibraryService $library): void
    {
        $copy = $library->duplicatePlan(LibraryPlan::where('type', $this->type)->with('sections')->findOrFail((int) $this->plan));
        $this->loadPlan($copy->id);
        $this->dispatch('notify', message: 'Copy created as a draft.');
    }

    public function deletePlan(FitnessLibraryService $library): void
    {
        $library->deletePlan(LibraryPlan::where('type', $this->type)->with('sections')->findOrFail((int) $this->plan));
        $this->plan = '';
        $this->mount();
        $this->dispatch('notify', message: 'Plan deleted from the library. Gyms keep the copies they already imported.');
    }

    public function movePlan(int $id, int $direction, FitnessLibraryService $library): void
    {
        $library->movePlan(LibraryPlan::where('type', $this->type)->findOrFail($id), $direction);
    }

    // ---- Categories ------------------------------------------------------------

    public function editCategory(?int $id = null): void
    {
        $category = $id ? LibraryCategory::where('type', $this->type)->findOrFail($id) : null;
        $this->resetErrorBag();
        $this->reset('categoryImage');
        $this->categoryId = $category?->id;
        $this->categoryForm = ['name' => (string) $category?->name, 'description' => (string) $category?->description, 'remove_image' => false];
        $this->showCategory = true;
    }

    public function saveCategory(FitnessLibraryService $library): void
    {
        $data = $this->validate([
            'categoryForm.name' => 'required|string|max:80',
            'categoryForm.description' => 'nullable|string|max:500',
            'categoryForm.remove_image' => 'boolean',
            'categoryImage' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ], [], ['categoryForm.name' => 'name'])['categoryForm'];

        $category = $this->categoryId ? LibraryCategory::where('type', $this->type)->findOrFail($this->categoryId) : null;
        $library->saveCategory($category, $this->planType(), $data, $this->categoryImage, (bool) $data['remove_image']);

        $this->showCategory = false;
        $this->dispatch('notify', message: 'Category saved.');
    }

    public function deleteCategory(FitnessLibraryService $library): void
    {
        $category = LibraryCategory::where('type', $this->type)->with('plans.sections')->findOrFail($this->categoryId);
        $library->deleteCategory($category);
        $this->showCategory = false;
        $this->plan = '';
        $this->mount();
        $this->dispatch('notify', message: 'Category and its plans deleted from the library.');
    }

    public function moveCategory(int $id, int $direction, FitnessLibraryService $library): void
    {
        $library->moveCategory(LibraryCategory::where('type', $this->type)->findOrFail($id), $direction);
    }

    public function render(): View
    {
        $categories = LibraryCategory::where('type', $this->type)
            ->with(['plans' => fn ($q) => $q->withCount(['sections', 'sections as photo_count' => fn ($s) => $s->whereNotNull('image_path')])])
            ->orderBy('sort_order')->orderBy('id')->get();

        $plans = $categories->flatMap->plans;

        return view('livewire.platform.library', [
            'planType' => $this->planType(),
            'categories' => $categories,
            'selected' => is_numeric($this->plan) ? LibraryPlan::with('editor')->find((int) $this->plan) : null,
            'stats' => [
                'plans' => $plans->count(),
                'published' => $plans->where('is_published', true)->count(),
                'gymsImported' => FitnessPlan::withoutGlobalScopes()->whereNull('deleted_at')->where('type', $this->type)->whereNotNull('library_key')->distinct()->count('gym_id'),
            ],
            'hints' => $this->planType()->sectionHints(),
        ]);
    }
}
