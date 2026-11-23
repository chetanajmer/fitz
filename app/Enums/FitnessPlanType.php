<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum FitnessPlanType: string
{
    use HasOptions;

    case Diet = 'diet';
    case Workout = 'workout';

    public function label(): string
    {
        return match ($this) {
            self::Diet => 'Diet chart',
            self::Workout => 'Workout plan',
        };
    }

    public function pluralLabel(): string
    {
        return match ($this) {
            self::Diet => 'Diet charts',
            self::Workout => 'Workout plans',
        };
    }

    /** Placeholder hints for section fields in the editor. */
    public function sectionHints(): array
    {
        return match ($this) {
            self::Diet => ['title' => 'Breakfast', 'subtitle' => '8:00 AM · 450 kcal', 'body' => "2 boiled eggs\n1 bowl oats with milk\n1 banana"],
            self::Workout => ['title' => 'Bench press', 'subtitle' => '4 sets × 10 reps · rest 90s', 'body' => 'Keep shoulder blades retracted. Lower the bar to mid-chest, press up explosively.'],
        };
    }
}
