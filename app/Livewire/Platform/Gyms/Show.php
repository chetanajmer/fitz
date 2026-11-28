<?php

namespace App\Livewire\Platform\Gyms;

use App\Enums\GymStatus;
use App\Models\Enquiry;
use App\Models\Gym;
use App\Models\GymPricingOverride;
use App\Models\ImpersonationToken;
use App\Models\Member;
use App\Models\PricingRate;
use App\Models\SaasInvoice;
use App\Models\User;
use App\Services\AccountPasswordService;
use App\Services\BillingService;
use App\Services\ImpersonationService;
use App\Services\PlatformGymService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
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
        $this->customFrom = Carbon::today()->addMonthNoOverflow()->startOfMonth()->toDateString();
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

    // ---- Magic links ("login as") ---------------------------------------------

    public bool $showLink = false;

    public string $linkUrl = '';

    public string $linkFor = '';

    public function magicLink(int $userId, ImpersonationService $impersonation): void
    {
        // Only accounts belonging to this gym.
        $user = User::where('gym_id', $this->gym->id)->findOrFail($userId);
        $link = $impersonation->createLink($user, Auth::user());

        $this->linkUrl = $link['url'];
        $this->linkFor = $user->name.' ('.str_replace('_', ' ', (string) $user->getRoleNames()->first()).')';
        $this->showLink = true;
    }

    // ---- Set a password (gym admins and trainers) ------------------------------

    public bool $showPassword = false;

    #[Locked]
    public ?int $passwordUserId = null;

    public string $passwordFor = '';

    public string $newPassword = '';

    public string $newPassword_confirmation = '';

    public function openPassword(int $userId): void
    {
        $user = $this->staffAccount($userId);

        $this->resetValidation();
        $this->reset('newPassword', 'newPassword_confirmation');
        $this->passwordUserId = $user->id;
        $this->passwordFor = $user->name.' ('.($user->isGymAdmin() ? 'gym admin' : 'trainer').')';
        $this->showPassword = true;
    }

    public function savePassword(AccountPasswordService $passwords): void
    {
        $this->validate(['newPassword' => ['required', 'confirmed', Password::min(8)]], [], ['newPassword' => 'password']);

        $user = $this->staffAccount((int) $this->passwordUserId);
        $passwords->setFor($user, $this->newPassword);

        $this->reset('newPassword', 'newPassword_confirmation', 'passwordUserId', 'showPassword');
        $this->dispatch('notify', message: "Password set for {$user->name}. They were signed out everywhere and sign in with the new password.");
    }

    /** A gym admin or trainer of this gym. Members are left to their gym (OTP app, or the gym's member form). */
    protected function staffAccount(int $userId): User
    {
        $user = User::where('gym_id', $this->gym->id)->findOrFail($userId);
        abort_unless($user->isGymAdmin() || $user->isTrainer(), 403);

        return $user;
    }

    // ---- Custom pricing --------------------------------------------------------

    public string $customPrice = '';

    public string $customFrom = '';

    public string $customUntil = '';

    public string $customNotes = '';

    public function setCustomPrice(BillingService $billing): void
    {
        $this->validate([
            'customPrice' => 'required|numeric|min:0|max:10000',
            'customFrom' => 'required|date',
            'customUntil' => 'nullable|date|after_or_equal:customFrom',
            'customNotes' => 'nullable|string|max:255',
        ], [], ['customPrice' => 'price', 'customFrom' => 'start date', 'customUntil' => 'end date']);

        $billing->setCustomPrice($this->gym, (float) $this->customPrice, $this->customFrom, $this->customUntil ?: null, $this->customNotes ?: null);
        $this->reset('customPrice', 'customUntil', 'customNotes');
        $this->dispatch('notify', message: 'Custom price saved for '.$this->gym->name.'.');
    }

    public function endCustomPrice(BillingService $billing): void
    {
        $this->validate(['customFrom' => 'required|date']);
        $billing->endCustomPrice($this->gym, $this->customFrom);
        $this->dispatch('notify', message: $this->gym->name.' returns to standard pricing from '.Carbon::parse($this->customFrom)->format('d M Y').'.');
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
            'accounts' => $this->tab === 'access'
                ? User::where('gym_id', $gym->id)->with('roles')->get()->reject(fn (User $u) => $u->isSuperAdmin())
                    ->sortBy(fn (User $u) => [$u->id === $gym->owner_id ? 0 : ($u->isGymAdmin() ? 1 : ($u->isTrainer() ? 2 : 3)), $u->name])->values()
                : collect(),
            'recentLinks' => $this->tab === 'access' ? ImpersonationToken::with(['user', 'creator'])->where('gym_id', $gym->id)->latest()->limit(15)->get() : collect(),
            'pricing' => $billing->rateForGym($gym),
            'standardRate' => PricingRate::rateOn(),
            'priceHistory' => $this->tab === 'pricing' ? GymPricingOverride::with('creator')->where('gym_id', $gym->id)->latest('effective_from')->latest('id')->get() : collect(),
        ])->title($gym->name);
    }
}
