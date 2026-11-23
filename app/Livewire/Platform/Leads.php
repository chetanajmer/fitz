<?php

namespace App\Livewire\Platform;

use App\Enums\LeadReason;
use App\Enums\LeadStatus;
use App\Models\PlatformLead;
use App\Services\PlatformLeadService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Member-app numbers that are not connected to any active gym. Super Admin only
 * (route group is behind role:super_admin; PlatformLead has no gym scope).
 */
#[Layout('components.layouts.platform')]
#[Title('App leads')]
class Leads extends Component
{
    use WithPagination;

    #[Url(except: 'open')]
    public string $status = 'open';

    #[Url(except: '')]
    public string $reason = '';

    #[Url(except: '')]
    public string $search = '';

    public bool $showModal = false;

    #[Locked]
    public ?int $leadId = null;

    /** @var array<string, string> */
    public array $form = [];

    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'reason', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function open(int $id): void
    {
        $lead = PlatformLead::findOrFail($id);
        $this->resetErrorBag();
        $this->leadId = $lead->id;
        $this->form = [
            'status' => $lead->status->value,
            'name' => (string) $lead->name,
            'city' => (string) $lead->city,
            'follow_up_date' => (string) $lead->follow_up_date?->toDateString(),
            'assigned_gym_id' => (string) $lead->assigned_gym_id,
            'notes' => (string) $lead->notes,
        ];
        $this->showModal = true;
    }

    public function save(PlatformLeadService $service): void
    {
        $data = $this->validate([
            'form.status' => ['required', Rule::enum(LeadStatus::class)],
            'form.name' => 'nullable|string|max:120',
            'form.city' => 'nullable|string|max:80',
            'form.follow_up_date' => 'nullable|date|required_if:form.status,follow_up',
            'form.assigned_gym_id' => 'nullable|integer|required_if:form.status,converted',
            'form.notes' => 'nullable|string|max:3000',
        ], [
            'form.follow_up_date.required_if' => 'Set a follow-up date.',
            'form.assigned_gym_id.required_if' => 'Choose the gym this person was connected to.',
        ])['form'];

        $service->update(PlatformLead::findOrFail($this->leadId), array_map(fn ($v) => $v === '' ? null : $v, $data));

        $this->showModal = false;
        $this->dispatch('notify', message: 'Lead updated.');
    }

    public function render(PlatformLeadService $service): View
    {
        $leads = PlatformLead::query()
            ->with(['previousGym:id,name', 'assignedGym:id,name', 'handler:id,name'])
            ->when($this->status === 'open', fn ($q) => $q->open())
            ->when($this->status === 'due', fn ($q) => $q->open()->whereDate('follow_up_date', '<=', Carbon::today()))
            ->when(in_array($this->status, LeadStatus::values(), true), fn ($q) => $q->where('status', $this->status))
            ->when($this->reason !== '', fn ($q) => $q->where('reason', $this->reason))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('phone_digits', 'like', '%'.preg_replace('/\D/', '', $this->search).'%')
                ->orWhere('name', 'like', "%{$this->search}%")
                ->orWhere('city', 'like', "%{$this->search}%")))
            ->orderByRaw("CASE WHEN status = 'new' THEN 0 ELSE 1 END")
            ->latest('last_requested_at')
            ->paginate(20);

        $selected = $this->leadId ? PlatformLead::with(['previousGym', 'assignedGym'])->find($this->leadId) : null;

        return view('livewire.platform.leads', [
            'leads' => $leads,
            'stats' => [
                'new' => PlatformLead::where('status', LeadStatus::New->value)->count(),
                'open' => PlatformLead::open()->count(),
                'due' => PlatformLead::open()->whereDate('follow_up_date', '<=', Carbon::today())->count(),
                'converted' => PlatformLead::where('status', LeadStatus::Converted->value)->count(),
            ],
            'reasons' => LeadReason::options(),
            'selected' => $selected,
            'suggestedGyms' => $selected ? $service->suggestedGyms($selected, 50) : collect(),
        ]);
    }
}
