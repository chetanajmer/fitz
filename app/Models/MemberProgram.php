<?php

namespace App\Models;

use App\Enums\FitnessPlanType;
use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** A program assigned to a member from a start date. */
class MemberProgram extends Model
{
    use BelongsToGym;

    // Written by ProgramService only.
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'type' => FitnessPlanType::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'stopped_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(FitnessProgram::class, 'fitness_program_id')->withTrashed();
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /** Not stopped and not yet finished. */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('status', 'active')->whereDate('end_date', '>=', Carbon::today()->toDateString());
    }

    /** completed | active | upcoming | stopped */
    public function state(): string
    {
        return match (true) {
            $this->status === 'stopped' => 'stopped',
            $this->end_date->lt(Carbon::today()) => 'completed',
            $this->start_date->gt(Carbon::today()) => 'upcoming',
            default => 'active',
        };
    }
}
