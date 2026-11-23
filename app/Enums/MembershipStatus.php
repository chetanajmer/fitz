<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum MembershipStatus: string
{
    use HasOptions;

    case Upcoming = 'upcoming';
    case Active = 'active';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function color(): string
    {
        return match ($this) {
            self::Upcoming => 'blue',
            self::Active => 'green',
            self::Expired => 'red',
            self::Cancelled => 'gray',
        };
    }
}
