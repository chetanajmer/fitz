<?php

namespace App\Services;

use App\Enums\MembershipStatus;
use App\Enums\PlanDuration;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MembershipService
{
    public function __construct(protected PaymentService $payments) {}

    public function calculateEndDate(MembershipPlan $plan, Carbon $start): Carbon
    {
        if ($plan->duration_type === PlanDuration::Custom) {
            return $start->copy()->addDays(max(1, (int) $plan->duration_days) - 1);
        }

        return $start->copy()->addMonthsNoOverflow($plan->duration_type->months())->subDay();
    }

    /**
     * Assign a plan to a member. Optionally records an initial (full or partial) payment.
     *
     * @param  array{amount:float, method:string, reference?:?string, paid_at?:?string, notes?:?string}|null  $payment
     */
    public function assign(Member $member, MembershipPlan $plan, ?string $startDate = null, float $discount = 0, ?string $notes = null, ?array $payment = null, ?Membership $renewedFrom = null): Membership
    {
        if ((int) $plan->gym_id !== (int) $member->gym_id) {
            throw ValidationException::withMessages(['plan_id' => 'Plan does not belong to this gym.']);
        }

        $start = Carbon::parse($startDate ?: Carbon::today())->startOfDay();
        $end = $this->calculateEndDate($plan, $start);
        $price = (float) $plan->price;
        $discount = round(max(0, min($discount, $price)), 2);

        $overlap = $member->memberships()->overlapping($start, $end)->first();
        if ($overlap) {
            throw ValidationException::withMessages([
                'start_date' => "Dates overlap with the existing membership \"{$overlap->plan_name}\" ({$overlap->start_date->format('d M Y')} – {$overlap->end_date->format('d M Y')}). Choose a start date after {$overlap->end_date->format('d M Y')}.",
            ]);
        }

        return DB::transaction(function () use ($member, $plan, $start, $end, $price, $discount, $notes, $payment, $renewedFrom) {
            $membership = new Membership(['notes' => $notes]);
            $membership->gym_id = $member->gym_id;
            $membership->member_id = $member->id;
            $membership->membership_plan_id = $plan->id;
            $membership->renewed_from_id = $renewedFrom?->id;
            $membership->plan_name = $plan->name;
            $membership->duration_type = $plan->duration_type;
            $membership->duration_days = (int) $start->diffInDays($end) + 1;
            $membership->start_date = $start;
            $membership->end_date = $end;
            $membership->price = $price;
            $membership->discount = $discount;
            $membership->total_amount = round($price - $discount, 2);
            $membership->amount_paid = 0;
            $membership->created_by = Auth::id();
            $membership->status = $start->isFuture() ? MembershipStatus::Upcoming : MembershipStatus::Active;
            $membership->save();

            if ($payment && (float) ($payment['amount'] ?? 0) > 0) {
                $this->payments->record($membership, (float) $payment['amount'], $payment['method'], $payment['reference'] ?? null, $payment['paid_at'] ?? null, $payment['notes'] ?? null);
            }

            return $membership->refresh();
        });
    }

    /** Renew: next membership starts the day after the latest one ends (or today if already lapsed). */
    public function renew(Member $member, MembershipPlan $plan, float $discount = 0, ?string $notes = null, ?array $payment = null, ?string $startDate = null): Membership
    {
        $latest = $member->latestMembership()->first();

        if (! $startDate) {
            $startDate = $latest && $latest->end_date->gte(Carbon::today())
                ? $latest->end_date->copy()->addDay()->toDateString()
                : Carbon::today()->toDateString();
        }

        return $this->assign($member, $plan, $startDate, $discount, $notes, $payment, $latest);
    }

    public function suggestedRenewalStart(Member $member): Carbon
    {
        $latest = $member->latestMembership()->first();

        return $latest && $latest->end_date->gte(Carbon::today())
            ? $latest->end_date->copy()->addDay()
            : Carbon::today();
    }

    public function cancel(Membership $membership, ?string $reason = null): Membership
    {
        if ((float) $membership->amount_paid > 0) {
            throw ValidationException::withMessages(['membership' => 'Memberships with recorded payments cannot be cancelled.']);
        }

        $membership->status = MembershipStatus::Cancelled;
        $membership->cancelled_at = now();
        $membership->notes = trim(($membership->notes ? $membership->notes."\n" : '').'Cancelled: '.($reason ?: 'no reason given'));
        $membership->save();

        return $membership;
    }

    /**
     * Sync stored statuses with dates. Runs daily from the scheduler across all gyms.
     *
     * @return array{expired:int, activated:int}
     */
    public function refreshStatuses(): array
    {
        $today = Carbon::today()->toDateString();

        $expired = Membership::withoutGlobalScopes()
            ->whereIn('status', [MembershipStatus::Active->value, MembershipStatus::Upcoming->value])
            ->whereDate('end_date', '<', $today)
            ->update(['status' => MembershipStatus::Expired->value, 'updated_at' => now()]);

        $activated = Membership::withoutGlobalScopes()
            ->where('status', MembershipStatus::Upcoming->value)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->update(['status' => MembershipStatus::Active->value, 'updated_at' => now()]);

        return compact('expired', 'activated');
    }
}
