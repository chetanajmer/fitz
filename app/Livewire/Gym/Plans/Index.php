<?php

namespace App\Livewire\Gym\Plans;

use App\Enums\PlanDuration;
use App\Models\MembershipPlan;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.gym')]
#[Title('Membership plans')]
class Index extends Component
{
    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function create(): void
    {
        $this->resetErrorBag();
        $this->editingId = null;
        $this->form = ['name' => '', 'duration_type' => PlanDuration::Monthly->value, 'duration_days' => '', 'max_freeze_days' => '0', 'price' => '', 'description' => '', 'features' => '', 'is_active' => true, 'is_public' => true, 'sort_order' => '0'];
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $plan = MembershipPlan::findOrFail($id);
        $this->resetErrorBag();
        $this->editingId = $plan->id;
        $this->form = [
            'name' => $plan->name, 'duration_type' => $plan->duration_type->value, 'duration_days' => (string) $plan->duration_days, 'max_freeze_days' => (string) $plan->max_freeze_days,
            'price' => (string) $plan->price, 'description' => (string) $plan->description, 'features' => implode("\n", $plan->features ?? []),
            'is_active' => $plan->is_active, 'is_public' => $plan->is_public, 'sort_order' => (string) $plan->sort_order,
        ];
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'form.name' => 'required|string|max:100',
            'form.duration_type' => ['required', Rule::enum(PlanDuration::class)],
            'form.duration_days' => 'nullable|required_if:form.duration_type,custom|integer|min:1|max:1825',
            'form.max_freeze_days' => 'required|integer|min:0|max:365',
            'form.price' => 'required|numeric|min:0|max:1000000',
            'form.description' => 'nullable|string|max:500',
            'form.features' => 'nullable|string|max:2000',
            'form.is_active' => 'boolean',
            'form.is_public' => 'boolean',
            'form.sort_order' => 'nullable|integer|min:0',
        ], [], ['form.name' => 'name', 'form.price' => 'price', 'form.duration_days' => 'duration (days)', 'form.max_freeze_days' => 'freeze days'])['form'];

        $type = PlanDuration::from($data['duration_type']);
        $attributes = [
            'name' => $data['name'],
            'duration_type' => $type,
            'duration_days' => $type === PlanDuration::Custom ? (int) $data['duration_days'] : $type->approxDays(),
            'max_freeze_days' => (int) $data['max_freeze_days'],
            'price' => $data['price'],
            'description' => $data['description'] ?: null,
            'features' => array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) $data['features'])))),
            'is_active' => (bool) $data['is_active'],
            'is_public' => (bool) $data['is_public'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];

        $this->editingId
            ? MembershipPlan::findOrFail($this->editingId)->update($attributes)
            : MembershipPlan::create($attributes);

        $this->showForm = false;
        $this->dispatch('notify', message: 'Plan saved.');
    }

    public function toggle(int $id): void
    {
        $plan = MembershipPlan::findOrFail($id);
        $plan->update(['is_active' => ! $plan->is_active]);
    }

    public function delete(int $id): void
    {
        MembershipPlan::findOrFail($id)->delete();
        $this->dispatch('notify', message: 'Plan archived. Existing memberships are unaffected.');
    }

    public function render(): View
    {
        return view('livewire.gym.plans.index', [
            'plans' => MembershipPlan::withCount(['memberships as active_count' => fn ($q) => $q->valid()])
                ->orderBy('sort_order')->orderBy('price')->get(),
        ]);
    }
}
