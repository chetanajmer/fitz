<?php

namespace App\Livewire\Gym\Content;

use App\Enums\FitnessPlanType;
use App\Models\FitnessPlan;
use App\Models\Member;
use App\Models\PlanCategory;
use App\Services\FitnessPlanService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Create / edit a diet chart or workout plan. Each section = title + subtitle + text + photo.
 */
#[Layout('components.layouts.gym')]
class Editor extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $type;

    #[Locked]
    public ?int $planId = null;

    #[Locked]
    public ?int $memberId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    /** @var list<array{key:string, id:?int, title:string, subtitle:string, body:string, image_url:?string, remove_image:bool}> */
    public array $sections = [];

    /** Section photo uploads keyed by section key. */
    public array $uploads = [];

    public $cover = null;

    public bool $removeCover = false;

    public function mount(?string $type = null, ?FitnessPlan $plan = null): void
    {
        if ($plan?->exists) {
            Gate::authorize('update', $plan);
            $plan->load('sections');
            $this->planId = $plan->id;
            $this->type = $plan->type->value;
            $this->memberId = $plan->member_id;
            $this->form = [
                'title' => $plan->title, 'summary' => (string) $plan->summary, 'plan_category_id' => (string) $plan->plan_category_id,
                'level' => (string) $plan->level, 'duration_weeks' => (string) $plan->duration_weeks,
                'daily_calories' => (string) $plan->daily_calories, 'is_published' => $plan->is_published, 'sort_order' => (string) $plan->sort_order,
            ];
            $this->sections = $plan->sections->map(fn ($s) => [
                'key' => (string) Str::uuid(), 'id' => $s->id, 'title' => $s->title, 'subtitle' => (string) $s->subtitle,
                'body' => (string) $s->body, 'image_url' => $s->imageUrl(), 'remove_image' => false,
            ])->all();

            return;
        }

        abort_unless(in_array($type, FitnessPlanType::values(), true), 404);
        $this->type = $type;

        $member = request()->integer('member') ? Member::visibleTo(Auth::user())->findOrFail(request()->integer('member')) : null;
        Gate::authorize('create', [FitnessPlan::class, $member]);
        $this->memberId = $member?->id;

        $this->form = [
            'title' => '', 'summary' => '', 'plan_category_id' => '', 'level' => '', 'duration_weeks' => '',
            'daily_calories' => '', 'is_published' => true, 'sort_order' => '0',
        ];
        $this->addSection();
    }

    public function planType(): FitnessPlanType
    {
        return FitnessPlanType::from($this->type);
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

        if ($index === false || ! isset($this->sections[$target])) {
            return;
        }

        [$this->sections[$index], $this->sections[$target]] = [$this->sections[$target], $this->sections[$index]];
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

    public function save(FitnessPlanService $service)
    {
        $plan = $this->planId ? FitnessPlan::findOrFail($this->planId) : null;
        $member = $this->memberId ? Member::findOrFail($this->memberId) : null;
        $plan ? Gate::authorize('update', $plan) : Gate::authorize('create', [FitnessPlan::class, $member]);

        $this->validate([
            'form.title' => 'required|string|max:150',
            'form.summary' => 'nullable|string|max:1000',
            'form.plan_category_id' => 'nullable|integer',
            'form.level' => ['nullable', Rule::in(array_keys(FitnessPlan::LEVELS))],
            'form.duration_weeks' => 'nullable|integer|min:1|max:104',
            'form.daily_calories' => 'nullable|integer|min:300|max:10000',
            'form.is_published' => 'boolean',
            'form.sort_order' => 'nullable|integer|min:0',
            'sections' => 'required|array|min:1|max:60',
            'sections.*.title' => 'required|string|max:150',
            'sections.*.subtitle' => 'nullable|string|max:150',
            'sections.*.body' => 'nullable|string|max:5000',
            'uploads.*' => 'nullable|image|max:5120',
            'cover' => 'nullable|image|max:5120',
        ], [
            'sections.required' => 'Add at least one section.',
            'sections.*.title.required' => 'Each section needs a title.',
            'uploads.*.image' => 'Section photos must be images.',
        ], ['form.title' => 'title', 'form.daily_calories' => 'daily calories']);

        $sections = array_map(fn ($s) => [
            'id' => $s['id'],
            'title' => $s['title'],
            'subtitle' => $s['subtitle'] ?: null,
            'body' => $s['body'] ?: null,
            'remove_image' => $s['remove_image'],
            'upload' => $this->uploads[$s['key']] ?? null,
        ], $this->sections);

        $data = array_map(fn ($v) => $v === '' ? null : $v, $this->form);
        $saved = $service->savePlan($plan, $this->planType(), $data, $sections, $member, $this->cover, $this->removeCover);

        session()->flash('success', $saved->isCustom() ? 'Customised plan saved — '.$saved->member->name.' can see it now.' : 'Plan saved.');

        return $this->redirect(route('gym.content.show', $saved));
    }

    public function render(): View
    {
        $plan = $this->planId ? FitnessPlan::find($this->planId) : null;

        return view('livewire.gym.content.editor', [
            'planType' => $this->planType(),
            'plan' => $plan,
            'member' => $this->memberId ? Member::find($this->memberId) : null,
            'categories' => PlanCategory::ofType($this->type)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'hints' => $this->planType()->sectionHints(),
        ])->title(($plan ? 'Edit ' : 'New ').strtolower($this->planType()->label()));
    }
}
