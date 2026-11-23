<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PlanDuration: string
{
    use HasOptions;

    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case HalfYearly = 'half_yearly';
    case Yearly = 'yearly';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::HalfYearly => 'Half-yearly',
            default => ucfirst($this->value),
        };
    }

    /** Calendar months for fixed plans; null for custom (day based). */
    public function months(): ?int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Quarterly => 3,
            self::HalfYearly => 6,
            self::Yearly => 12,
            self::Custom => null,
        };
    }

    public function approxDays(): ?int
    {
        return $this->months() ? (int) round($this->months() * 30.4) : null;
    }
}
