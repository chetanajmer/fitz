<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PaymentMethod: string
{
    use HasOptions;

    case Cash = 'cash';
    case Upi = 'upi';
    case BankTransfer = 'bank_transfer';
    case Card = 'card';

    public function label(): string
    {
        return match ($this) {
            self::Upi => 'UPI',
            default => ucwords(str_replace('_', ' ', $this->value)),
        };
    }
}
