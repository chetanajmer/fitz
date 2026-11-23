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

    /** Gym admins (and trainers granted "members.all") see every member; other trainers only their assigned members. */
    public function view(User $user, Member $member): bool
    {
        if ((int) $member->gym_id !== (int) $user->gym_id || ! $user->can('members.view')) {
            return false;
        }

        return $user->can('members.all') || (int) $member->trainer_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('members.manage');
    }

    public function update(User $user, Member $member): bool
    {
        return $user->can('members.manage') && $this->view($user, $member);
    }

    public function delete(User $user, Member $member): bool
    {
        return $this->update($user, $member);
    }

    /** Record weigh-ins and set weight targets: anyone who can see the member (admins, their trainer). */
    public function trackProgress(User $user, Member $member): bool
    {
        return $this->view($user, $member);
    }

    public function checkIn(User $user, Member $member): bool
    {
        return $this->view($user, $member) && $user->can('attendance.manage');
    }
}
