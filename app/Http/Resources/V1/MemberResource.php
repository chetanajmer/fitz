<?php

namespace App\Http\Resources\V1;

use App\Models\Member;
use App\Services\MemberMembershipService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Member */
class MemberResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'gym_id' => $this->gym_id,
            'member_code' => $this->member_code,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'gender' => $this->gender,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'joining_date' => $this->joining_date?->toDateString(),
            'fitness_goal' => $this->fitness_goal,
            'photo_url' => $this->photoUrl(),
            'status' => $this->status->value,
            'membership_state' => $this->membershipState(), // active | upcoming | expired | none
            // Home-screen membership card (plan, dates, days left, progress, renew CTA).
            'membership' => app(MemberMembershipService::class)->card($this->resource),
        ];
    }
}
