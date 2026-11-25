<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum MemberStatus: string
{
    use HasOptions;

    case Active = 'active';
    case Inactive = 'inactive';
    /** Signed up from the app; waits for the gym to approve. Not billed and cannot log in. */
    case Pending = 'pending';

    public function label(): string
    {
        return $this === self::Pending ? 'Pending approval' : ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Pending => 'amber',
            self::Inactive => 'gray',
        };
    }
}
