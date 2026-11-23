<?php

namespace App\Livewire\Gym\Members;

use App\Enums\MemberStatus;
use App\Enums\PaymentMethod;
use App\Models\Attendance;
use App\Models\FitnessPlan;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\MemberService;
use App\Services\MembershipService;
use App\Services\PaymentService;
use App\Services\StreakService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.gym')]
class Show extends Component
{
    use WithPagination;

    #[Locked]
    public Member $member;

    #[Url(except: 'overview')]
    public string $tab = 'overview';

    // ---- Assign / renew plan modal ----
    public bool $showPlanModal = false;

    public string $planMode = 'assign';

    /** @var array{plan_id: string, start_date: string, discount: string, notes: string, pay_now: bool, amount: string, method: string, reference: string} */
    public array $planForm = [];

    // ---- Payment modal ----
    public bool $showPaymentModal = false;

    /** @var array{membership_id: string, amount: string, method: string, reference: string, paid_at: string, notes: string} */
    public array $paymentForm = [];

    public function mount(Member $member): void
    {
        Gate::authorize('view', $member);
        $this->member = $member;
    }

    // ---- Memberships ---------------------------------------------------------

    public function openPlanModal(string $mode = 'assign'): void
    {
        $memberships = app(MembershipService::class);
        Gate::authorize('update', $this->member);
        abort_unless(Auth::user()->can('memberships.manage'), 403);
        $this->resetErrorBag();
        $this->planMode = $mode;
        $this->planForm = [
            'plan_id' => '',
            'start_date' => $memberships->suggestedRenewalStart($this->member)->toDateString(),
            'discount' => '0',
            'notes' => '',
            'pay_now' => true,
            'amount' => '',
            'method' => PaymentMethod::Cash->value,
            'reference' => '',
        ];
        $this->showPlanModal = true;
    }

    public function updatedPlanFormPlanId(): void
    {
        $plan = $this->planForm['plan_id'] ? MembershipPlan::find($this->planForm['plan_id']) : null;
        $this->planForm['amount'] = $plan ? (string) max(0, (float) $plan->price - (float) ($this->planForm['discount'] ?: 0)) : '';
    }

    public function updatedPlanFormDiscount(): void
    {
        $this->updatedPlanFormPlanId();
    }

    public function savePlan(MembershipService $memberships): void
    {
        Gate::authorize('update', $this->member);
        abort_unless(Auth::user()->can('memberships.manage'), 403);

        $this->validate([
            'planForm.plan_id' => 'required|integer',
            'planForm.start_date' => 'required|date',
            'planForm.discount' => 'nullable|numeric|min:0',
            'planForm.notes' => 'nullable|string|max:500',
            'planForm.amount' => 'nullable|required_if:planForm.pay_now,true|numeric|min:0',
            'planForm.method' => 'required_if:planForm.pay_now,true|in:'.implode(',', PaymentMethod::values()),
            'planForm.reference' => 'nullable|string|max:100',
        ], [], ['planForm.plan_id' => 'plan', 'planForm.start_date' => 'start date', 'planForm.amount' => 'amount']);

        // Tenant-scoped lookup: a plan id from another gym resolves to 404.
        $plan = MembershipPlan::active()->findOrFail($this->planForm['plan_id']);

        $payment = $this->planForm['pay_now'] && Auth::user()->can('payments.manage') && (float) $this->planForm['amount'] > 0
            ? ['amount' => (float) $this->planForm['amount'], 'method' => $this->planForm['method'], 'reference' => $this->planForm['reference']]
            : null;

        $membership = $this->planMode === 'renew'
            ? $memberships->renew($this->member, $plan, (float) $this->planForm['discount'], $this->planForm['notes'] ?: null, $payment, $this->planForm['start_date'])
            : $memberships->assign($this->member, $plan, $this->planForm['start_date'], (float) $this->planForm['discount'], $this->planForm['notes'] ?: null, $payment);

        $this->showPlanModal = false;
        $this->dispatch('notify', message: "{$membership->plan_name} assigned until {$membership->end_date->format('d M Y')}.");
    }

    public function cancelMembership(int $membershipId): void
    {
        $memberships = app(MembershipService::class);
        Gate::authorize('update', $this->member);
        abort_unless(Auth::user()->can('memberships.manage'), 403);
        $membership = $this->member->memberships()->findOrFail($membershipId);
        $memberships->cancel($membership, 'Cancelled by '.Auth::user()->name);
        $this->dispatch('notify', message: 'Membership cancelled.');
    }

    // ---- Payments ---------------------------------------------------------

    public function openPaymentModal(?int $membershipId = null): void
    {
        Gate::authorize('update', $this->member);
        abort_unless(Auth::user()->can('payments.manage'), 403);
        $this->resetErrorBag();

        $membership = $membershipId
            ? $this->member->memberships()->find($membershipId)
            : $this->member->memberships()->withBalance()->first();

        $this->paymentForm = [
            'membership_id' => (string) ($membership?->id ?? ''),
            'amount' => $membership ? (string) $membership->balance() : '',
            'method' => PaymentMethod::Cash->value,
            'reference' => '',
            'paid_at' => now()->format('Y-m-d\TH:i'),
            'notes' => '',
        ];
        $this->showPaymentModal = true;
    }

    public function savePayment(PaymentService $payments): void
    {
        Gate::authorize('update', $this->member);
        abort_unless(Auth::user()->can('payments.manage'), 403);

        $this->validate([
            'paymentForm.membership_id' => 'required|integer',
            'paymentForm.amount' => 'required|numeric|min:1',
            'paymentForm.method' => 'required|in:'.implode(',', PaymentMethod::values()),
            'paymentForm.reference' => 'nullable|string|max:100',
            'paymentForm.paid_at' => 'required|date|before_or_equal:now',
            'paymentForm.notes' => 'nullable|string|max:500',
        ], [], ['paymentForm.membership_id' => 'membership', 'paymentForm.amount' => 'amount', 'paymentForm.paid_at' => 'payment date']);

        $membership = $this->member->memberships()->findOrFail($this->paymentForm['membership_id']);

        $payment = $payments->record(
            $membership,
            (float) $this->paymentForm['amount'],
            $this->paymentForm['method'],
            $this->paymentForm['reference'],
            $this->paymentForm['paid_at'],
            $this->paymentForm['notes'],
        );

        $this->showPaymentModal = false;
        $this->dispatch('notify', message: "Payment {$payment->receipt_number} recorded.");
    }

    // ---- Attendance & profile actions ---------------------------------------

    public function checkIn(AttendanceService $attendance): void
    {
        Gate::authorize('checkIn', $this->member);
        $record = $attendance->checkIn($this->member);
        $this->dispatch('notify', message: "Checked in at {$record->check_in_at->format('h:i A')}.");
    }

    public function checkOut(AttendanceService $attendance): void
    {
        Gate::authorize('checkIn', $this->member);
        $record = Attendance::where('member_id', $this->member->id)->whereDate('date', today())->firstOrFail();
        $attendance->checkOut($record);
        $this->dispatch('notify', message: 'Checked out.');
    }

    public function toggleStatus(): void
    {
        Gate::authorize('update', $this->member);
        $wasPending = $this->member->status === MemberStatus::Pending;
        $this->member->status = $this->member->status === MemberStatus::Active ? MemberStatus::Inactive : MemberStatus::Active;
        $this->member->save();
        $this->dispatch('notify', message: $wasPending ? 'Registration approved. They can now log in to the app.' : "Member marked {$this->member->status->label()}.");
    }

    public function assignTrainer(?string $trainerId = null): void
    {
        $members = app(MemberService::class);
        Gate::authorize('update', $this->member);
        abort_unless(Auth::user()->can('members.all'), 403);
        $members->assignTrainer($this->member, $trainerId ? (int) $trainerId : null);
        $this->dispatch('notify', message: 'Trainer updated.');
    }

    public function delete(MemberService $members)
    {
        Gate::authorize('delete', $this->member);
        $members->delete($this->member);
        session()->flash('success', 'Member deleted.');

        return $this->redirect(route('gym.members.index'));
    }

    public function render(): View
    {
        $member = $this->member->fresh(['trainer', 'enquiry', 'user']);
        $user = Auth::user();

        return view('livewire.gym.members.show', [
            'm' => $member,
            'canManage' => $user->can('update', $member),
            'canPay' => $user->can('payments.manage'),
            'canMembership' => $user->can('update', $member) && $user->can('memberships.manage'),
            'state' => $member->membershipState(),
            'current' => $member->currentMembership(),
            'latest' => $member->latestMembership()->first(),
            'pending' => $member->pendingAmount(),
            'memberships' => $member->memberships()->withCount('payments')->get(),
            'payments' => $this->tab === 'payments' ? $member->payments()->with('membership')->paginate(10, pageName: 'payments') : null,
            'attendance' => $this->tab === 'attendance' ? $member->attendances()->paginate(15, pageName: 'attendance') : null,
            'customPlans' => $this->tab === 'plans' ? FitnessPlan::where('member_id', $member->id)->with('category')->withCount('sections')->latest('updated_at')->get() : collect(),
            'canCustomPlans' => $user->can('create', [FitnessPlan::class, $member]),
            'streak' => app(StreakService::class)->summary($member),
            'attendanceCount30' => $member->attendances()->where('date', '>=', now()->subDays(30))->count(),
            'todayAttendance' => Attendance::where('member_id', $member->id)->whereDate('date', today())->first(),
            'plans' => MembershipPlan::active()->orderBy('sort_order')->orderBy('price')->get(),
            'trainers' => $user->can('members.all') ? User::trainersOf($user->gym_id)->where('status', 'active')->orderBy('name')->get(['id', 'name']) : collect(),
            'openMemberships' => $member->memberships()->withBalance()->get(),
        ])->title($member->name);
    }
}
