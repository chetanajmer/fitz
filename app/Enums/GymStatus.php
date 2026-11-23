<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum GymStatus: string
{
    use HasOptions;

    case Active = 'active';
    case Suspended = 'suspended';
    case Deactivated = 'deactivated';

    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Suspended => 'amber',
            self::Deactivated => 'red',
        };
    }
}
