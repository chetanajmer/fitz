<?php

namespace App\Policies;

use App\Models\FitnessPlan;
use App\Models\Member;
use App\Models\User;

/**
 * Gym admins (content.manage) manage categories, default plans and any custom plan.
 * Trainers (content.custom) may only create/edit custom plans for their assigned members.
 */
class FitnessPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('content.manage') || $user->can('content.custom');
    }

    public function view(User $user, FitnessPlan $plan): bool
    {
        if ((int) $plan->gym_id !== (int) $user->gym_id) {
            return false;
        }

        return $plan->isCustom() ? $this->canManageForMember($user, $plan->member) : $this->viewAny($user);
    }

    /** $member = null → creating a default plan. */
    public function create(User $user, ?Member $member = null): bool
    {
        return $member ? $this->canManageForMember($user, $member) : $user->can('content.manage');
    }

    public function update(User $user, FitnessPlan $plan): bool
    {
        if ((int) $plan->gym_id !== (int) $user->gym_id) {
            return false;
        }

        return $plan->isCustom() ? $this->canManageForMember($user, $plan->member) : $user->can('content.manage');
    }

    public function delete(User $user, FitnessPlan $plan): bool
    {
        return $this->update($user, $plan);
    }

    public function manageCategories(User $user): bool
    {
        return $user->can('content.manage');
    }

    protected function canManageForMember(User $user, ?Member $member): bool
    {
        if (! $member || (int) $member->gym_id !== (int) $user->gym_id) {
            return false;
        }

        return $user->can('content.manage')
            || ($user->can('content.custom') && $user->isTrainer() && (int) $member->trainer_id === (int) $user->id);
    }
}
