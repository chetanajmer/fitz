<?php

namespace App\Services;

use App\Enums\LeadReason;
use App\Enums\LeadStatus;
use App\Enums\MemberStatus;
use App\Models\Gym;
use App\Models\Member;
use App\Models\PlatformLead;
use App\Models\Scopes\GymScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PlatformLeadService
{
    /**
     * Record a member-app login attempt from a number that is not an active member of any
     * active gym. Repeat attempts update the existing open lead instead of duplicating it.
     *
     * @param  array{name?:?string, city?:?string, latitude?:?float, longitude?:?float, app_platform?:?string, app_version?:?string}  $info
     */
    public function captureFromApp(string $phone, array $info = [], ?string $ip = null): PlatformLead
    {
        $digits = Member::normalizePhone($phone);
        [$reason, $previousGymId] = $this->diagnose($digits);

        return DB::transaction(function () use ($phone, $digits, $info, $ip, $reason, $previousGymId) {
            $lead = PlatformLead::where('phone_digits', $digits)->open()->lockForUpdate()->latest('id')->first();

            if ($lead) {
                $lead->request_count++;
            } else {
                $lead = new PlatformLead;
                $lead->phone_digits = $digits;
                $lead->status = LeadStatus::New;
                $lead->source = 'member_app';
                $lead->request_count = 1;
            }

            $lead->phone = $phone;
            $lead->reason = $reason;
            $lead->previous_gym_id = $previousGymId ?? $lead->previous_gym_id;
            $lead->last_requested_at = now();
            $lead->ip_address = $ip;

            // Only fill details the support team hasn't already captured.
            foreach (['name', 'city', 'latitude', 'longitude', 'app_platform', 'app_version'] as $field) {
                if (! empty($info[$field]) && empty($lead->{$field})) {
                    $lead->{$field} = $info[$field];
                }
            }

            $lead->save();

            return $lead;
        });
    }

    /**
     * Why this number has no usable member account, plus the gym it was last linked to.
     *
     * @return array{0: LeadReason, 1: ?int}
     */
    public function diagnose(?string $digits): array
    {
        $members = Member::withoutGlobalScope(GymScope::class)->withTrashed()
            ->with('gym')
            ->where('phone_digits', $digits)
            ->latest('updated_at')
            ->get();

        if ($match = $members->first(fn (Member $m) => ! $m->trashed() && $m->status === MemberStatus::Active && ! $m->gym?->isOperational())) {
            return [LeadReason::GymInactive, $match->gym_id];
        }

        if ($match = $members->first(fn (Member $m) => ! $m->trashed() && $m->status === MemberStatus::Inactive)) {
            return [LeadReason::MemberInactive, $match->gym_id];
        }

        if ($match = $members->first(fn (Member $m) => $m->trashed())) {
            return [LeadReason::MemberRemoved, $match->gym_id];
        }

        return [LeadReason::NotRegistered, null];
    }

    /**
     * @param  array{status:string, name?:?string, city?:?string, follow_up_date?:?string, notes?:?string, assigned_gym_id?:?int}  $data
     */
    public function update(PlatformLead $lead, array $data): PlatformLead
    {
        $status = LeadStatus::from($data['status']);

        if (! empty($data['assigned_gym_id']) && ! Gym::operational()->whereKey($data['assigned_gym_id'])->exists()) {
            $data['assigned_gym_id'] = null;
        }

        $lead->fill($data);

        if ($status !== LeadStatus::New && $lead->contacted_at === null) {
            $lead->contacted_at = now();
        }

        $lead->handled_by = Auth::id();
        $lead->save();

        return $lead;
    }

    /** Active gyms to suggest to the lead — same city first. */
    public function suggestedGyms(PlatformLead $lead, int $limit = 8)
    {
        return Gym::operational()
            ->when($lead->city, fn ($q) => $q->orderByRaw('CASE WHEN LOWER(city) = ? THEN 0 ELSE 1 END', [mb_strtolower(trim($lead->city))]))
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'city', 'phone']);
    }
}
