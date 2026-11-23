<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum InvoiceStatus: string
{
    use HasOptions;

    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';
    case Void = 'void';

    public function color(): string
    {
        return match ($this) {
            self::Unpaid => 'red',
            self::Partial => 'amber',
            self::Paid => 'green',
            self::Void => 'gray',
        };
    }
}
