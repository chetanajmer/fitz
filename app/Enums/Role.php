<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Role: string
{
    use HasOptions;

    case SuperAdmin = 'super_admin';
    case GymAdmin = 'gym_admin';
    case Trainer = 'trainer';
    case Member = 'member';

    /** Permissions granted to each role. Super Admin bypasses all checks via Gate::before. */
    public function permissions(): array
    {
        return match ($this) {
            self::SuperAdmin => ['platform.manage'],
            self::GymAdmin => [
                'gym.dashboard', 'gym.settings', 'gym.billing',
                'members.view', 'members.manage', 'members.import',
                'enquiries.manage', 'plans.manage', 'memberships.manage', 'payments.manage',
                'attendance.manage', 'attendance.reports', 'trainers.manage',
                'content.manage', 'content.custom',
            ],
            self::Trainer => ['gym.dashboard', 'members.view', 'attendance.manage', 'content.custom'],
            self::Member => ['portal.access'],
        };
    }
}
