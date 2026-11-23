<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cached streak numbers for a member. Written only by StreakService. */
class MemberStreak extends Model
{
    use BelongsToGym;

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'current_started_on' => 'date',
            'longest_started_on' => 'date',
            'longest_ended_on' => 'date',
            'last_visit_on' => 'date',
            'milestone_reached_at' => 'datetime',
            'calculated_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }
}
