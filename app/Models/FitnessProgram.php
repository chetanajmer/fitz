<?php

namespace App\Models;

use App\Enums\DietPreference;
use App\Enums\FitnessPlanType;
use App\Enums\ProgramGoal;
use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A day-by-day master schedule of workout plans or diet charts, assignable to members. */
class FitnessProgram extends Model
{
    use BelongsToGym, SoftDeletes;

    protected $fillable = ['type', 'title', 'summary', 'goal', 'level', 'diet_preference', 'duration_weeks', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'type' => FitnessPlanType::class,
            'goal' => ProgramGoal::class,
            'diet_preference' => DietPreference::class,
            'is_active' => 'boolean',
        ];
    }

    public function days(): HasMany
    {
        return $this->hasMany(FitnessProgramDay::class)->orderBy('day_number');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(MemberProgram::class);
    }

    /** Days in one cycle (usually 7; the cycle repeats for the whole duration). */
    public function cycleLength(): int
    {
        return max(1, (int) ($this->relationLoaded('days') ? $this->days->max('day_number') : $this->days()->reorder()->max('day_number')));
    }

    public function scopeOfType(Builder $query, FitnessPlanType|string $type): Builder
    {
        return $query->where('type', $type instanceof FitnessPlanType ? $type->value : $type);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
