<?php

namespace App\Enums\Concerns;

trait HasOptions
{
    /** @return array<string, string> value => label */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }

    public function color(): string
    {
        return 'gray';
    }
}
