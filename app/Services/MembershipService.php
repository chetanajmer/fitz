<?php

namespace App\Services;

use App\Enums\MembershipStatus;
use App\Enums\PlanDuration;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipFreeze;
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
    public function assign(Member $member, MembershipPlan $plan, ?string $startDate = null, float $discount = 0, ?string $notes = null, ?array $payment = null, ?Membership $renewedFrom = null, bool $chargeJoiningFee = false): Membership
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

        $fees = $member->gym->feeSettings();
        $joiningFee = $chargeJoiningFee ? $fees['joining_fee'] : 0.0;

        return DB::transaction(function () use ($member, $plan, $start, $end, $price, $discount, $notes, $payment, $renewedFrom, $fees, $joiningFee) {
            $membership = new Membership(['notes' => $notes]);
            $membership->gym_id = $member->gym_id;
            $membership->member_id = $member->id;
            $membership->membership_plan_id = $plan->id;
            $membership->renewed_from_id = $renewedFrom?->id;
            $membership->plan_name = $plan->name;
            $membership->duration_type = $plan->duration_type;
            $membership->duration_days = (int) $start->diffInDays($end) + 1;
            $membership->max_freeze_days = (int) $plan->max_freeze_days;
            $membership->start_date = $start;
            $membership->end_date = $end;
            $membership->price = $price;
            $membership->discount = $discount;
            $membership->joining_fee = $joiningFee;
            $membership->total_amount = round($price - $discount + $joiningFee, 2);
            $membership->gst_rate = $fees['gst_enabled'] ? $fees['gst_rate'] : null;
            $membership->gstin = $fees['gst_enabled'] ? $fees['gstin'] : null;
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

    /**
     * Pause a membership for $days days from $startDate; its end date moves out by the same number of days.
     * Allowed up to the plan's freeze days, on memberships that have not ended or been cancelled.
     */
    public function freeze(Membership $membership, string $startDate, int $days, ?string $reason = null): MembershipFreeze
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = $start->copy()->addDays($days - 1);
        $membership->load('freezes');

        $error = match (true) {
            $membership->status === MembershipStatus::Cancelled => 'A cancelled membership cannot be frozen.',
            $membership->end_date->lt(Carbon::today()) => 'This membership has already ended.',
            $days < 1 => 'Freeze for at least one day.',
            $start->lt(Carbon::today()) => 'A freeze cannot start in the past.',
            $start->lt($membership->start_date) || $start->gt($membership->end_date) => 'The freeze must start between '.$membership->start_date->format('d M Y').' and '.$membership->end_date->format('d M Y').'.',
            $membership->max_freeze_days === 0 => 'This plan does not allow freezing. Set freeze days on the plan for new memberships.',
            $days > $membership->freezeDaysLeft() => 'Only '.$membership->freezeDaysLeft().' freeze '.str('day')->plural($membership->freezeDaysLeft()).' left on this membership.',
            $membership->freezes->contains(fn (MembershipFreeze $f) => $f->start_date->lte($end) && $f->end_date->gte($start)) => 'These dates overlap another freeze.',
            $membership->member->memberships()->where('id', '!=', $membership->id)->whereDate('start_date', '>', $membership->end_date)->where('status', '!=', MembershipStatus::Cancelled->value)->exists() => 'The member already has a later membership booked. Cancel or move it before freezing this one.',
            default => null,
        };

        if ($error) {
            throw ValidationException::withMessages(['freeze' => $error]);
        }

        return DB::transaction(function () use ($membership, $start, $end, $days, $reason) {
            $freeze = new MembershipFreeze;
            $freeze->gym_id = $membership->gym_id;
            $freeze->membership_id = $membership->id;
            $freeze->start_date = $start;
            $freeze->end_date = $end;
            $freeze->days = $days;
            $freeze->reason = $reason;
            $freeze->created_by = Auth::id();
            $freeze->save();

            $membership->end_date = $membership->end_date->copy()->addDays($days);
            $membership->save();

            return $freeze;
        });
    }

    /** End a freeze today (the member is back) or, before it starts, remove it. Unused days go back. */
    public function endFreeze(MembershipFreeze $freeze): void
    {
        $today = Carbon::today();
        $membership = $freeze->membership;

        if ($freeze->end_date->lt($today)) {
            throw ValidationException::withMessages(['freeze' => 'This freeze is already over.']);
        }

        DB::transaction(function () use ($freeze, $membership, $today) {
            $used = $freeze->start_date->lt($today) ? (int) $freeze->start_date->diffInDays($today) : 0;
            $membership->end_date = $membership->end_date->copy()->subDays($freeze->days - $used);
            $membership->save();

            if ($used === 0) {
                $freeze->delete();
            } else {
                $freeze->end_date = $today->copy()->subDay();
                $freeze->days = $used;
                $freeze->save();
            }
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
