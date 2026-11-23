<?php

namespace App\Policies;

use App\Models\Member;
use App\Models\User;

class MemberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('members.view');
    }

    /** Gym admins see every member of their gym; trainers only their assigned members. */
    public function view(User $user, Member $member): bool
    {
        if ((int) $member->gym_id !== (int) $user->gym_id) {
            return false;
        }

        if ($user->can('members.manage')) {
            return true;
        }

        return $user->isTrainer() && (int) $member->trainer_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('members.manage');
    }

    public function update(User $user, Member $member): bool
    {
        return (int) $member->gym_id === (int) $user->gym_id && $user->can('members.manage');
    }

    public function delete(User $user, Member $member): bool
    {
        return $this->update($user, $member);
    }

    public function checkIn(User $user, Member $member): bool
    {
        return $this->view($user, $member) && $user->can('attendance.manage');
    }
}
