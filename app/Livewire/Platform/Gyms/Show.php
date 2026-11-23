<?php

namespace App\Livewire\Platform\Gyms;

use App\Enums\GymStatus;
use App\Models\Enquiry;
use App\Models\Gym;
use App\Models\Member;
use App\Models\SaasInvoice;
use App\Services\BillingService;
use App\Services\PlatformGymService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.platform')]
class Show extends Component
{
    use WithPagination;

    #[Locked]
    public Gym $gym;

    #[Url(except: 'members')]
    public string $tab = 'members';

    public string $search = '';

    public string $memberFilter = '';

    public bool $showStatusModal = false;

    public string $newStatus = '';

    public string $reason = '';

    public function mount(Gym $gym): void
    {
        $this->gym = $gym;
    }

    public function updatedSearch(): void
    {
        $this->resetPage('members');
    }

    public function updatedMemberFilter(): void
    {
        $this->resetPage('members');
    }

    public function openStatus(string $status): void
    {
        $this->newStatus = $status;
        $this->reason = '';
        $this->showStatusModal = true;
    }

    public function changeStatus(PlatformGymService $service): void
    {
        $this->validate([
            'newStatus' => ['required', Rule::enum(GymStatus::class)],
            'reason' => 'nullable|required_unless:newStatus,active|string|max:255',
        ]);

        $service->changeStatus($this->gym, GymStatus::from($this->newStatus), $this->reason ?: null);
        $this->showStatusModal = false;
        $this->dispatch('notify', message: "Gym is now {$this->gym->status->label()}.");
    }

    public function generateInvoice(BillingService $billing): void
    {
        $invoice = $billing->generateInvoice($this->gym, Carbon::today()->subMonthNoOverflow());
        $this->dispatch('notify', message: $invoice->wasRecentlyCreated ? "Invoice {$invoice->invoice_number} generated." : 'Invoice for last month already exists.');
    }

    public function render(BillingService $billing): View
    {
        $gym = $this->gym->fresh(['owner']);
        $today = Carbon::today()->toDateString();

        // Platform view: explicitly constrained to this gym.
        $members = Member::forGym($gym)
            ->with(['latestMembership' => fn ($q) => $q->withoutGlobalScopes()])
            ->search($this->search)
            ->when($this->memberFilter === 'billable', fn ($q) => $q->where('status', 'active')->whereHas('memberships', fn ($m) => $m->withoutGlobalScopes()->valid($today)))
            ->when($this->memberFilter === 'inactive', fn ($q) => $q->where('status', 'inactive'))
            ->latest('id')
            ->paginate(20, pageName: 'members');

        return view('livewire.platform.gyms.show', [
            'g' => $gym,
            'members' => $members,
            'counts' => [
                'members' => Member::forGym($gym)->count(),
                'billable' => Member::forGym($gym)->where('status', 'active')->whereHas('memberships', fn ($m) => $m->withoutGlobalScopes()->valid($today))->count(),
                'trainers' => $gym->users()->role('trainer')->count(),
                'enquiries' => Enquiry::forGym($gym)->count(),
            ],
            'estimate' => $billing->currentMonthEstimate($gym),
            'outstanding' => $billing->outstandingFor($gym),
            'invoices' => $this->tab === 'invoices' ? SaasInvoice::where('gym_id', $gym->id)->latest('period_start')->paginate(12, pageName: 'invoices') : null,
        ])->title($gym->name);
    }
}
