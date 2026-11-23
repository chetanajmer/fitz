<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum DietPreference: string
{
    use HasOptions;

    case Veg = 'veg';
    case Eggetarian = 'eggetarian';
    case NonVeg = 'non_veg';
    case Vegan = 'vegan';

    public function label(): string
    {
        return match ($this) {
            self::Veg => 'Vegetarian',
            self::Eggetarian => 'Eggetarian',
            self::NonVeg => 'Non-vegetarian',
            self::Vegan => 'Vegan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Veg, self::Vegan => 'green',
            self::Eggetarian => 'amber',
            self::NonVeg => 'red',
        };
    }
}
