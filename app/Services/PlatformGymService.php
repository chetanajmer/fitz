<?php

namespace App\Services;

use App\Enums\GymStatus;
use App\Models\Gym;

class PlatformGymService
{
    public function changeStatus(Gym $gym, GymStatus $status, ?string $reason = null): Gym
    {
        $gym->status = $status;
        $gym->is_active = $status === GymStatus::Active;
        $gym->status_changed_at = now();
        $gym->status_reason = $status === GymStatus::Active ? null : $reason;
        $gym->save();

        return $gym;
    }
}
