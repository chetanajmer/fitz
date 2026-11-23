<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum MemberStatus: string
{
    use HasOptions;

    case Active = 'active';
    case Inactive = 'inactive';

    public function color(): string
    {
        return $this === self::Active ? 'green' : 'gray';
    }
}
