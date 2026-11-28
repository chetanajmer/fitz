<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\MembershipStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\ApiException;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\PaymentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Fees for the staff app: plans assigned to members (memberships), payments against them, dues and
 * shareable receipts / invoices. Same services and rules as the web panel's member page.
 *
 * Changing a member's fees needs the right to edit that member (members.manage, and for trainers without
 * "see all members", only their own members) plus memberships.manage or payments.manage.
 */
class FeeController extends AdminController
{
    public function __construct(protected MembershipService $memberships, protected PaymentService $payments) {}

    // ---- One member ----------------------------------------------------------

    /** GET /admin/members/{id}/fees — fee summary, every membership with its payments, and renewal hints. */
    public function memberFees(Request $request, int $memberId): JsonResponse
    {
        return $this->success($this->feeSummary($request, $this->visibleMember($request, $memberId)), 'Member fees.');
    }

    /**
     * POST /admin/members/{id}/memberships — assign or renew a plan, optionally collecting a payment.
     * { plan_id*, mode: assign|renew, start_date (required for assign), discount?, notes?, pay_now?, amount?, method?, reference? }
     */
    public function assign(Request $request, int $memberId): JsonResponse
    {
        $staff = $this->staff($request);
        $member = $this->editableMember($request, $memberId);

        $data = $request->validate([
            'plan_id' => ['required', 'integer'],
            'mode' => ['nullable', Rule::in(['assign', 'renew'])],
            'start_date' => [Rule::requiredIf(fn () => $request->input('mode', 'assign') === 'assign'), 'nullable', 'date'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
            'pay_now' => ['nullable', 'boolean'],
            'amount' => [Rule::requiredIf(fn () => $request->boolean('pay_now')), 'nullable', 'numeric', 'min:0'],
            'method' => [Rule::requiredIf(fn () => $request->boolean('pay_now') && (float) $request->input('amount') > 0), 'nullable', Rule::in(PaymentMethod::values())],
            'reference' => ['nullable', 'string', 'max:100'],
        ], [], ['plan_id' => 'plan', 'start_date' => 'start date']);

        // Tenant-scoped: a plan of another gym, or a switched-off plan, is not found.
        $plan = MembershipPlan::active()->find($data['plan_id'])
            ?? throw new ApiException('Plan not found or switched off.', 'NOT_FOUND', 404);

        $collect = ($data['pay_now'] ?? false) && (float) ($data['amount'] ?? 0) > 0;
        if ($collect && ! $staff->can('payments.manage')) {
            throw $this->forbidden('You can assign plans but not collect payments.');
        }
        $payment = $collect ? ['amount' => (float) $data['amount'], 'method' => $data['method'], 'reference' => $data['reference'] ?? null] : null;

        $discount = (float) ($data['discount'] ?? 0);
        $notes = $data['notes'] ?? null;
        $membership = ($data['mode'] ?? 'assign') === 'renew'
            ? $this->memberships->renew($member, $plan, $discount, $notes, $payment, $data['start_date'] ?? null)
            : $this->memberships->assign($member, $plan, $data['start_date'], $discount, $notes, $payment);

        $membership->load('payments');

        return $this->success([
            'membership' => $this->membershipWithPayments($membership),
            'payment' => $membership->payments->first() ? $this->paymentData($membership->payments->first()->setRelation('membership', $membership)) : null,
            'fees' => $this->feeSummary($request, $member->fresh()),
        ], "{$membership->plan_name} assigned until {$membership->end_date->format('d M Y')}.", 201);
    }

    /** POST /admin/memberships/{id}/cancel { reason? } — only while nothing has been paid on it. */
    public function cancel(Request $request, int $membershipId): JsonResponse
    {
        $membership = $this->findMembership($request, $membershipId, editable: true);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:300']]);

        if ($membership->status === MembershipStatus::Cancelled) {
            throw new ApiException('This membership is already cancelled.', 'ALREADY_CANCELLED', 409);
        }
        if ((float) $membership->amount_paid > 0) {
            throw new ApiException('Memberships with recorded payments cannot be cancelled.', 'MEMBERSHIP_HAS_PAYMENTS', 409);
        }

        $reason = trim(($data['reason'] ?? '') !== '' ? $data['reason'].' — ' : '').'Cancelled by '.$this->staff($request)->name;
        $this->memberships->cancel($membership, $reason);

        return $this->success([
            'membership' => $this->membershipWithPayments($membership->fresh('payments')),
            'fees' => $this->feeSummary($request, $membership->member),
        ], 'Membership cancelled.');
    }

    // ---- Lists ------------------------------------------------------------------

    /** GET /admin/memberships?filter=active|expiring|expired|upcoming|dues|all&search=&page= */
    public function memberships(Request $request): JsonResponse
    {
        $data = $request->validate([
            'filter' => ['nullable', Rule::in(['active', 'expiring', 'expired', 'upcoming', 'dues', 'all'])],
            'search' => ['nullable', 'string', 'max:80'],
        ]);
        $today = Carbon::today();
        $filter = $data['filter'] ?? 'active';

        $base = $this->visibleMemberships($request)->with('member')
            ->when(filled($data['search'] ?? null), fn ($q) => $q->whereHas('member', fn ($m) => $m->search($data['search'])));

        $query = match ($filter) {
            'active' => $base->valid()->orderBy('end_date'),
            'expiring' => $base->where('status', '!=', 'cancelled')->whereBetween('end_date', [$today->toDateString(), $today->copy()->addDays(15)->toDateString()])->orderBy('end_date'),
            // Each member's latest lapsed membership, only if they have not renewed.
            'expired' => $base->where('status', '!=', 'cancelled')->whereDate('end_date', '<', $today)
                ->whereNotExists(fn ($q) => $q->from('memberships as newer')->whereColumn('newer.member_id', 'memberships.member_id')
                    ->where('newer.status', '!=', 'cancelled')->whereColumn('newer.end_date', '>', 'memberships.end_date'))
                ->orderByDesc('end_date'),
            'upcoming' => $base->where('status', '!=', 'cancelled')->whereDate('start_date', '>', $today)->orderBy('start_date'),
            'dues' => $base->withBalance()->orderByDesc(DB::raw('total_amount - amount_paid')),
            default => $base->latest('start_date')->latest('id'),
        };

        $page = $query->paginate($this->perPage($request));

        return $this->success([
            'filter' => $filter,
            'memberships' => collect($page->items())->map(fn (Membership $m) => $this->membershipData($m) + ['member' => $this->memberBrief($m->member)])->values(),
            'pagination' => $this->pagination($page),
        ], 'Memberships.');
    }

    /**
     * GET /admin/pending-fees?group=membership|member&search=&page=
     * Unpaid balances, largest first. group=member adds up each member's dues.
     */
    public function pendingFees(Request $request): JsonResponse
    {
        $data = $request->validate([
            'group' => ['nullable', Rule::in(['membership', 'member'])],
            'search' => ['nullable', 'string', 'max:80'],
        ]);
        $group = $data['group'] ?? 'membership';

        $base = fn () => $this->visibleMemberships($request)->withBalance()
            ->when(filled($data['search'] ?? null), fn ($q) => $q->whereHas('member', fn ($m) => $m->search($data['search'])));

        $totals = $base()->selectRaw('COALESCE(SUM(total_amount - amount_paid), 0) as due, COUNT(*) as memberships, COUNT(DISTINCT member_id) as members')->first();

        if ($group === 'member') {
            $page = $base()
                ->selectRaw('member_id, SUM(total_amount - amount_paid) as due, SUM(total_amount) as billed, SUM(amount_paid) as paid, COUNT(*) as memberships_count, MAX(end_date) as last_end_date')
                ->groupBy('member_id')
                ->orderByDesc('due')
                ->paginate($this->perPage($request));
            $members = Member::withTrashed()->whereIn('id', collect($page->items())->pluck('member_id'))->get()->keyBy('id');

            $rows = collect($page->items())->map(fn ($row) => [
                'member' => $this->memberBrief($members->get($row->member_id)),
                'due' => round((float) $row->due, 2),
                'billed' => round((float) $row->billed, 2),
                'paid' => round((float) $row->paid, 2),
                'memberships_count' => (int) $row->memberships_count,
                'last_end_date' => $row->last_end_date ? Carbon::parse($row->last_end_date)->toDateString() : null,
            ])->values();
        } else {
            $page = $base()->with('member')->orderByDesc(DB::raw('total_amount - amount_paid'))->orderBy('end_date')->paginate($this->perPage($request));
            $rows = collect($page->items())->map(fn (Membership $m) => $this->membershipData($m) + ['member' => $this->memberBrief($m->member)])->values();
        }

        return $this->success([
            'group' => $group,
            'totals' => [
                'due' => round((float) $totals->due, 2),
                'memberships' => (int) $totals->memberships,
                'members' => (int) $totals->members,
            ],
            'rows' => $rows,
            'pagination' => $this->pagination($page),
        ], 'Pending fees.');
    }

    /** GET /admin/payments?search=&method=&from=&to=&member_id=&page= (from/to default to this month) */
    public function payments(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'method' => ['nullable', Rule::in(PaymentMethod::values())],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'member_id' => ['nullable', 'integer'],
        ]);
        $from = Carbon::parse($data['from'] ?? Carbon::today()->startOfMonth())->startOfDay();
        $to = Carbon::parse($data['to'] ?? Carbon::today())->endOfDay();
        $search = trim((string) ($data['search'] ?? ''));

        $query = Payment::query()
            ->where('status', 'completed')
            ->whereBetween('paid_at', [$from, $to])
            ->when($data['method'] ?? null, fn ($q, $method) => $q->where('method', $method))
            ->when($data['member_id'] ?? null, fn ($q, $id) => $q->where('member_id', $id))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('receipt_number', 'like', '%'.addcslashes($search, '%_\\').'%')
                ->orWhereHas('member', fn ($m) => $m->search($search))));

        $byMethod = (clone $query)->selectRaw('method, SUM(amount) as total, COUNT(*) as count')->groupBy('method')->get()
            ->mapWithKeys(fn ($row) => [$row->method->value => ['amount' => round((float) $row->total, 2), 'count' => (int) $row->count]]);

        $page = (clone $query)->with(['member', 'membership', 'receiver'])->latest('paid_at')->latest('id')->paginate($this->perPage($request));

        return $this->success([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'totals' => [
                'amount' => round((float) $byMethod->sum('amount'), 2),
                'count' => (int) $byMethod->sum('count'),
                'by_method' => collect(PaymentMethod::values())->mapWithKeys(fn (string $m) => [$m => $byMethod[$m] ?? ['amount' => 0.0, 'count' => 0]])->all(),
            ],
            'payments' => collect($page->items())->map(fn (Payment $p) => $this->paymentData($p))->values(),
            'pagination' => $this->pagination($page),
        ], 'Payments.');
    }

    /** GET /admin/payments/{id} */
    public function payment(int $paymentId): JsonResponse
    {
        $payment = Payment::with(['member', 'membership', 'receiver'])->find($paymentId)
            ?? throw new ApiException('Payment not found.', 'NOT_FOUND', 404);

        return $this->success(['payment' => $this->paymentData($payment)], 'Payment.');
    }

    /**
     * POST /admin/payments { membership_id*, amount*, method*, reference?, paid_at?, notes? } — mark a fee as received.
     * amount: at least 1 and at most the balance. The member gets the "payment received" push.
     */
    public function record(Request $request): JsonResponse
    {
        $data = $request->validate([
            'membership_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:1'],
            'method' => ['required', Rule::in(PaymentMethod::values())],
            'reference' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], ['membership_id' => 'membership', 'paid_at' => 'payment date']);

        $membership = $this->findMembership($request, (int) $data['membership_id'], editable: true);

        if ($membership->status === MembershipStatus::Cancelled) {
            throw new ApiException('This membership is cancelled.', 'MEMBERSHIP_CANCELLED', 409);
        }
        if ($membership->balance() <= 0) {
            throw new ApiException('This membership is already fully paid.', 'ALREADY_PAID', 409);
        }

        $payment = $this->payments->record($membership, (float) $data['amount'], $data['method'], $data['reference'] ?? null, $data['paid_at'] ?? null, $data['notes'] ?? null);
        $payment->load(['member', 'membership', 'receiver']);

        return $this->success([
            'payment' => $this->paymentData($payment),
            'fees' => $this->feeSummary($request, $membership->member),
        ], "Payment {$payment->receipt_number} recorded.", 201);
    }

    // ---- Documents --------------------------------------------------------------

    /** GET /admin/payments/{id}/receipt?share=0|1 */
    public function receipt(Request $request, int $paymentId): JsonResponse
    {
        $payment = Payment::find($paymentId) ?? throw new ApiException('Payment not found.', 'NOT_FOUND', 404);

        return $this->success($this->documentLink($request, 'documents.shared.receipt', ['paymentId' => $payment->id]), 'Receipt link.');
    }

    /** GET /admin/memberships/{id}/invoice?share=0|1 */
    public function invoice(Request $request, int $membershipId): JsonResponse
    {
        $membership = $this->findMembership($request, $membershipId);

        return $this->success($this->documentLink($request, 'documents.shared.invoice', ['membershipId' => $membership->id]), 'Invoice link.');
    }

    // ---- Helpers ----------------------------------------------------------------

    /** Memberships of the members this staff user can see. */
    protected function visibleMemberships(Request $request): Builder
    {
        $staff = $this->staff($request);

        return Membership::query()->when(! $staff->can('members.all'), fn ($q) => $q->whereIn('member_id', Member::visibleTo($staff)->select('id')));
    }

    protected function visibleMember(Request $request, int $memberId): Member
    {
        return Member::visibleTo($this->staff($request))->find($memberId)
            ?? throw new ApiException('Member not found.', 'NOT_FOUND', 404);
    }

    protected function editableMember(Request $request, int $memberId): Member
    {
        $member = $this->visibleMember($request, $memberId);

        if (! $this->staff($request)->can('update', $member)) {
            throw $this->forbidden('You cannot change this member\'s fees.');
        }

        return $member;
    }

    protected function findMembership(Request $request, int $membershipId, bool $editable = false): Membership
    {
        $membership = $this->visibleMemberships($request)->with('member')->find($membershipId)
            ?? throw new ApiException('Membership not found.', 'NOT_FOUND', 404);

        if ($editable && (! $membership->member || ! $this->staff($request)->can('update', $membership->member))) {
            throw $this->forbidden('You cannot change this member\'s fees.');
        }

        return $membership;
    }

    /** @return array<string, mixed> */
    protected function feeSummary(Request $request, Member $member): array
    {
        $staff = $this->staff($request);
        $memberships = $member->memberships()->with('payments')->orderByDesc('start_date')->orderByDesc('id')->get();
        $counted = $memberships->where('status', '!=', MembershipStatus::Cancelled);
        $billed = round((float) $counted->sum('total_amount'), 2);
        $paid = round((float) $counted->sum('amount_paid'), 2);
        $due = round($counted->sum(fn (Membership $m) => $m->balance()), 2);
        $current = $member->currentMembership();
        $lastPayment = $memberships->flatMap->payments->sortByDesc('paid_at')->first();

        return [
            'member' => $this->memberBrief($member) + ['status' => $member->status],
            'totals' => [
                'billed' => $billed,
                'paid' => $paid,
                'due' => $due,
                'discount' => round((float) $counted->sum('discount'), 2),
                'memberships' => $counted->count(),
            ],
            'payment_status' => $counted->isEmpty() ? 'none' : ($due <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid')),
            'current_membership' => $current ? $this->membershipData($current) : null,
            'last_payment_at' => $this->iso($lastPayment?->paid_at),
            'suggested_renewal_start' => $this->memberships->suggestedRenewalStart($member)->toDateString(),
            'can_assign' => $staff->can('memberships.manage') && $staff->can('update', $member),
            'can_collect' => $staff->can('payments.manage') && $staff->can('update', $member),
            'memberships' => $memberships->map(fn (Membership $m) => $this->membershipWithPayments($m))->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    protected function membershipWithPayments(Membership $membership): array
    {
        return $this->membershipData($membership) + [
            'notes' => $membership->notes,
            'renewed_from_id' => $membership->renewed_from_id,
            'cancelled_at' => $this->iso($membership->cancelled_at),
            'can_cancel' => $membership->status !== MembershipStatus::Cancelled && (float) $membership->amount_paid <= 0,
            'payments' => $membership->payments->map(fn (Payment $p) => [
                'id' => $p->id,
                'receipt_no' => $p->receipt_number,
                'amount' => (float) $p->amount,
                'method' => $p->method?->value,
                'reference' => $p->reference,
                'paid_at' => $this->iso($p->paid_at),
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    protected function paymentData(Payment $payment): array
    {
        /** @var User|null $receiver */
        $receiver = $payment->relationLoaded('receiver') ? $payment->receiver : $payment->receiver()->first();

        return [
            'id' => $payment->id,
            'receipt_no' => $payment->receipt_number,
            'member' => $this->memberBrief($payment->member),
            'amount' => (float) $payment->amount,
            'method' => $payment->method?->value,
            'method_label' => $payment->method?->label(),
            'reference' => $payment->reference,
            'notes' => $payment->notes,
            'paid_at' => $this->iso($payment->paid_at),
            'membership' => $payment->membership ? [
                'id' => $payment->membership->id,
                'plan_name' => $payment->membership->plan_name,
                'balance' => $payment->membership->balance(),
            ] : null,
            'collected_by' => $this->person($receiver),
        ];
    }
}
