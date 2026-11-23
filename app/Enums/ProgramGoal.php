<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** What a workout or diet program is designed for. */
enum ProgramGoal: string
{
    use HasOptions;

    case WeightLoss = 'weight_loss';
    case MuscleGain = 'muscle_gain';
    case Lean = 'lean';
    case Maintenance = 'maintenance';
    case Strength = 'strength';
    case GeneralFitness = 'general_fitness';
    case Endurance = 'endurance';
    case Flexibility = 'flexibility';
    case Home = 'home';
    case LowImpact = 'low_impact';
    case Diabetes = 'diabetes';
    case Pcos = 'pcos';

    public function label(): string
    {
        return match ($this) {
            self::WeightLoss => 'Weight loss',
            self::MuscleGain => 'Muscle / weight gain',
            self::Lean => 'Lean & toning',
            self::Maintenance => 'Maintenance',
            self::Strength => 'Strength',
            self::GeneralFitness => 'General fitness',
            self::Endurance => 'Endurance & cardio',
            self::Flexibility => 'Flexibility & mobility',
            self::Home => 'Home workout',
            self::LowImpact => 'Seniors & low impact',
            self::Diabetes => 'Diabetes-friendly',
            self::Pcos => 'PCOS-friendly',
        };
    }

    /** @return list<self> */
    public static function forType(FitnessPlanType $type): array
    {
        return $type === FitnessPlanType::Diet
            ? [self::WeightLoss, self::MuscleGain, self::Lean, self::Maintenance, self::Diabetes, self::Pcos]
            : [self::WeightLoss, self::MuscleGain, self::Lean, self::Strength, self::GeneralFitness, self::Endurance, self::Flexibility, self::Home, self::LowImpact];
    }

    /** @return array<string, string> */
    public static function optionsFor(FitnessPlanType $type): array
    {
        return collect(self::forType($type))->mapWithKeys(fn (self $g) => [$g->value => $g->label()])->all();
    }
}
