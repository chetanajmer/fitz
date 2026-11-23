<?php

namespace App\Services;

use App\Enums\MembershipStatus;
use App\Enums\PlanDuration;
use App\Models\Member;
use App\Models\Membership;
use Illuminate\Support\Carbon;

/**
 * Membership data for the member mobile app (home-screen membership card and membership history).
 * Must run inside the member's gym tenant context.
 */
class MemberMembershipService
{
    /**
     * The membership card: the membership valid today, otherwise the next upcoming one,
     * otherwise the most recently ended one. Null fields when the member has never had a membership.
     *
     * @return array<string, mixed>
     */
    public function card(Member $member): array
    {
        $today = Carbon::today();
        $memberships = $member->memberships()->where('status', '!=', MembershipStatus::Cancelled->value)->get();

        $current = $memberships->first(fn (Membership $m) => $m->start_date->lte($today) && $m->end_date->gte($today));
        $upcoming = $memberships->filter(fn (Membership $m) => $m->start_date->gt($today))->sortBy('start_date')->values();
        $shown = $current ?? $upcoming->first() ?? $memberships->sortByDesc('end_date')->first();

        if (! $shown) {
            return [
                'state' => 'none',
                'state_label' => 'No membership',
                'has_membership' => false,
                'show_renew' => true,
                'message' => 'You don\'t have a membership yet. Visit the front desk to join a plan.',
            ];
        }

        $state = $this->state($shown, $current !== null);
        // A renewal already booked after the current membership: no need to nag about renewing.
        $renewal = $current ? $upcoming->first(fn (Membership $m) => $m->start_date->gt($current->end_date->copy()->subDay())) : null;

        return [
            'state' => $state,
            'state_label' => $this->stateLabel($state),
            'has_membership' => true,
            'show_renew' => in_array($state, ['expiring_soon', 'expired'], true) && ! $renewal,
            'message' => $this->message($state, $shown, $renewal),
            ...$this->details($shown),
            'renewal' => $renewal ? $this->details($renewal) : null,
        ];
    }

    /**
     * All memberships, newest first, for a "My membership" history screen.
     *
     * @return list<array<string, mixed>>
     */
    public function history(Member $member, int $limit = 20): array
    {
        return $member->memberships()->orderByDesc('start_date')->orderByDesc('id')->limit($limit)->get()
            ->map(fn (Membership $m) => [...$this->details($m), 'status' => $m->computedStatus()->value])
            ->all();
    }

    /** @return array<string, mixed> */
    protected function details(Membership $membership): array
    {
        $today = Carbon::today();
        $totalDays = (int) $membership->start_date->diffInDays($membership->end_date) + 1;
        $daysUsed = $membership->start_date->gt($today) ? 0 : min($totalDays, (int) $membership->start_date->diffInDays($today) + 1);

        return [
            'id' => $membership->id,
            'plan_name' => $membership->plan_name,
            'duration_label' => $membership->duration_type === PlanDuration::Custom
                ? $membership->duration_days.' days'
                : $membership->duration_type?->label(),
            'start_date' => $membership->start_date->toDateString(),
            'end_date' => $membership->end_date->toDateString(),
            'total_days' => $totalDays,
            'days_used' => $daysUsed,
            'days_left' => $membership->start_date->gt($today) ? $totalDays : $membership->daysRemaining(),
            'starts_in_days' => $membership->start_date->gt($today) ? (int) $today->diffInDays($membership->start_date) : 0,
            'progress_percent' => round($daysUsed / max(1, $totalDays) * 100, 1),
            'price' => (float) $membership->price,
            'discount' => (float) $membership->discount,
            'total_amount' => (float) $membership->total_amount,
            'amount_paid' => (float) $membership->amount_paid,
            'balance' => $membership->balance(),
            'payment_status' => $membership->paymentStatus(), // paid | partial | unpaid
        ];
    }

    /** active | expiring_soon | upcoming | expired */
    protected function state(Membership $membership, bool $isCurrent): string
    {
        if (! $isCurrent) {
            return $membership->start_date->isFuture() ? 'upcoming' : 'expired';
        }

        return $membership->daysRemaining() <= (int) config('gymai.membership_expiring_soon_days', 7) ? 'expiring_soon' : 'active';
    }

    protected function stateLabel(string $state): string
    {
        return match ($state) {
            'active' => 'Active',
            'expiring_soon' => 'Expiring soon',
            'upcoming' => 'Starts soon',
            'expired' => 'Expired',
            default => 'No membership',
        };
    }

    protected function message(string $state, Membership $membership, ?Membership $renewal): string
    {
        $days = $membership->daysRemaining();

        return match ($state) {
            'expiring_soon' => $renewal
                ? 'Renewed! Your next plan starts on '.$renewal->start_date->format('d M').'.'
                : ($days === 0 ? 'Your membership ends today. Renew to keep training.' : "Your membership ends in {$days} ".str('day')->plural($days).'. Renew to keep your streak going.'),
            'expired' => 'Your membership ended on '.$membership->end_date->format('d M Y').'. Renew to continue.',
            'upcoming' => 'Your membership starts on '.$membership->start_date->format('d M Y').'.',
            default => 'Valid till '.$membership->end_date->format('d M Y').'.',
        };
    }
}
