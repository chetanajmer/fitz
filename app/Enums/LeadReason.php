<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Why a member-app login attempt became a platform lead. */
enum LeadReason: string
{
    use HasOptions;

    case NotRegistered = 'not_registered';
    case MemberInactive = 'member_inactive';
    case MemberRemoved = 'member_removed';
    case GymInactive = 'gym_inactive';

    public function label(): string
    {
        return match ($this) {
            self::NotRegistered => 'Not registered with any gym',
            self::MemberInactive => 'Deactivated by their gym',
            self::MemberRemoved => 'Removed by their gym',
            self::GymInactive => 'Their gym is suspended / closed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NotRegistered => 'blue',
            self::MemberInactive => 'amber',
            self::MemberRemoved => 'red',
            self::GymInactive => 'purple',
        };
    }
}
