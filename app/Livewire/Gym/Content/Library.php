<?php

namespace App\Livewire\Gym\Content;

use App\Enums\FitnessPlanType;
use App\Models\FitnessPlan;
use App\Models\Member;
use App\Models\PlanCategory;
use App\Services\FitnessLibraryImporter;
use App\Services\FitnessPlanService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Diet charts / workout plans library for one type (route param {type}).
 */
#[Layout('components.layouts.gym')]
class Library extends Component
{
    use WithFileUploads, WithPagination;

    #[Locked]
    public string $type;

    /** defaults | custom | categories */
    #[Url(except: 'defaults')]
    public string $tab = 'defaults';

    #[Url(except: '')]
    public string $category = '';

    #[Url(except: '')]
    public string $search = '';

    // Category modal
    public bool $showCategory = false;

    #[Locked]
    public ?int $categoryId = null;

    /** @var array<string, mixed> */
    public array $categoryForm = [];

    public $categoryImage = null;

    // "Send to member" modal (new custom plan, or copy of a default plan)
    public bool $showSend = false;

    #[Locked]
    public ?int $copyPlanId = null;

    public string $memberSearch = '';

    // "Import default library" modal
    public bool $showImport = false;

    /** @var list<string> library category keys to import */
    public array $importKeys = [];

    public bool $importPublish = true;

    public function mount(string $type): void
    {
        abort_unless(in_array($type, FitnessPlanType::values(), true), 404);
        Gate::authorize('viewAny', FitnessPlan::class);
        $this->type = $type;

        if ($this->tab === 'categories' && ! Auth::user()->can('content.manage')) {
            $this->tab = 'defaults';
        }
    }

    public function planType(): FitnessPlanType
    {
        return FitnessPlanType::from($this->type);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['tab', 'category', 'search'], true)) {
            $this->resetPage();
        }
    }

    // ---- Categories -------------------------------------------------------------

    public function editCategory(?int $id = null): void
    {
        Gate::authorize('manageCategories', FitnessPlan::class);
        $category = $id ? PlanCategory::ofType($this->type)->findOrFail($id) : null;
        $this->resetErrorBag();
        $this->reset('categoryImage');
        $this->categoryId = $category?->id;
        $this->categoryForm = [
            'name' => (string) $category?->name,
            'description' => (string) $category?->description,
            'sort_order' => (string) ($category?->sort_order ?? 0),
            'is_active' => $category?->is_active ?? true,
            'remove_image' => false,
        ];
        $this->showCategory = true;
    }

    public function saveCategory(FitnessPlanService $service): void
    {
        Gate::authorize('manageCategories', FitnessPlan::class);
        $data = $this->validate([
            'categoryForm.name' => 'required|string|max:80',
            'categoryForm.description' => 'nullable|string|max:500',
            'categoryForm.sort_order' => 'nullable|integer|min:0',
            'categoryForm.is_active' => 'boolean',
            'categoryForm.remove_image' => 'boolean',
            'categoryImage' => 'nullable|image|max:4096',
        ], [], ['categoryForm.name' => 'name'])['categoryForm'];

        $category = $this->categoryId ? PlanCategory::ofType($this->type)->findOrFail($this->categoryId) : null;
        $service->saveCategory($category, $this->planType(), $data, $this->categoryImage, (bool) $data['remove_image']);

        $this->showCategory = false;
        $this->dispatch('notify', message: 'Category saved.');
    }

    public function deleteCategory(int $id, FitnessPlanService $service): void
    {
        Gate::authorize('manageCategories', FitnessPlan::class);
        $service->deleteCategory(PlanCategory::ofType($this->type)->findOrFail($id));
        $this->dispatch('notify', message: 'Category deleted. Its plans are now uncategorised.');
    }

    // ---- Default library import ---------------------------------------------

    public function openImport(FitnessLibraryImporter $importer): void
    {
        Gate::authorize('manageCategories', FitnessPlan::class);
        $this->resetErrorBag();
        // Pre-select categories that still have plans to import.
        $this->importKeys = collect($importer->preview($this->planType()))
            ->filter(fn ($c) => $c['imported'] < $c['plans'] || $c['photos'] > 0)->pluck('key')->values()->all();
        $this->importPublish = true;
        $this->showImport = true;
    }

    public function runImport(FitnessLibraryImporter $importer): void
    {
        Gate::authorize('manageCategories', FitnessPlan::class);

        $valid = array_column($importer->categories($this->planType()), 'key');
        $keys = array_values(array_intersect($this->importKeys, $valid));

        if ($keys === []) {
            $this->addError('importKeys', 'Select at least one category.');

            return;
        }

        $result = $importer->import($this->planType(), $keys, $this->importPublish);
        $this->showImport = false;
        $this->tab = 'defaults';
        $this->category = '';
        $this->resetPage();

        $parts = [];
        if ($result['plans_created'] > 0) {
            $parts[] = "Imported {$result['plans_created']} plan(s)"
                .($result['categories_created'] ? " in {$result['categories_created']} new categories" : '')
                .($this->importPublish ? '' : ' as drafts');
        }
        if ($result['images_added'] > 0) {
            $parts[] = "{$result['images_added']} photo(s) added";
        }
        $message = $parts ? implode(' · ', $parts).'.' : 'Everything selected was already imported, with all available photos.';

        $this->dispatch('notify', message: $message);
    }

    public function showCategoryPlans(int $id): void
    {
        $this->category = (string) $id;
        $this->tab = 'defaults';
        $this->resetPage();
    }

    // ---- Plans ------------------------------------------------------------------

    public function togglePublished(int $id): void
    {
        $plan = FitnessPlan::ofType($this->type)->findOrFail($id);
        Gate::authorize('update', $plan);
        $plan->update(['is_published' => ! $plan->is_published]);
    }

    public function deletePlan(int $id, FitnessPlanService $service): void
    {
        $plan = FitnessPlan::ofType($this->type)->findOrFail($id);
        Gate::authorize('delete', $plan);
        $service->deletePlan($plan);
        $this->dispatch('notify', message: 'Plan deleted.');
    }

    /** Open the member picker. $planId = copy that default plan; null = start a blank custom plan. */
    public function openSend(?int $planId = null): void
    {
        $this->copyPlanId = $planId ? FitnessPlan::ofType($this->type)->defaults()->findOrFail($planId)->id : null;
        $this->memberSearch = '';
        $this->showSend = true;
    }

    public function sendTo(int $memberId, FitnessPlanService $service)
    {
        $member = Member::visibleTo(Auth::user())->findOrFail($memberId);
        Gate::authorize('create', [FitnessPlan::class, $member]);

        if ($this->copyPlanId) {
            $copy = $service->copyForMember(FitnessPlan::ofType($this->type)->defaults()->findOrFail($this->copyPlanId), $member);
            session()->flash('success', "Copied to {$member->name}. Customise it and save.");

            return $this->redirect(route('gym.content.edit', $copy));
        }

        return $this->redirect(route('gym.content.create', ['type' => $this->type, 'member' => $member->id]));
    }

    public function render(FitnessLibraryImporter $importer): View
    {
        $user = Auth::user();
        $type = $this->planType();

        $plans = FitnessPlan::ofType($type)
            ->with(['category', 'member'])
            ->withCount('sections')
            ->when($this->tab === 'defaults', fn ($q) => $q->defaults())
            ->when($this->tab === 'custom', fn ($q) => $q->whereNotNull('member_id')
                ->when(! $user->can('content.manage'), fn ($c) => $c->whereHas('member', fn ($m) => $m->where('trainer_id', $user->id))))
            ->when($this->category === 'none', fn ($q) => $q->whereNull('plan_category_id'))
            ->when($this->category !== '' && $this->category !== 'none', fn ($q) => $q->where('plan_category_id', (int) $this->category))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w->where('title', 'like', "%{$this->search}%")
                ->orWhereHas('member', fn ($m) => $m->where('name', 'like', "%{$this->search}%"))))
            ->orderBy('sort_order')->latest('updated_at')
            ->paginate(12);

        return view('livewire.gym.content.library', [
            'planType' => $type,
            'plans' => $this->tab === 'categories' ? null : $plans,
            'categories' => PlanCategory::ofType($type)->withCount(['plans' => fn ($q) => $q->whereNull('member_id')])->orderBy('sort_order')->orderBy('name')->get(),
            'canManage' => $user->can('content.manage'),
            'libraryPreview' => $this->showImport ? $importer->preview($type) : [],
            'libraryVersion' => $importer->version(),
            'memberResults' => $this->showSend && strlen(trim($this->memberSearch)) >= 1
                ? Member::visibleTo($user)->active()->search($this->memberSearch)->orderBy('name')->limit(8)->get()
                : collect(),
        ])->title($type->pluralLabel());
    }
}
