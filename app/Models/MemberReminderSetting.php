<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A member's personal reminders (water, diet meals, workout). Written by ReminderService only. */
class MemberReminderSetting extends Model
{
    use BelongsToGym;

    public const HYDRATION_INTERVALS = [30, 45, 60, 90, 120, 180];

    public const DIET_LEADS = [0, 10, 15, 30];

    public const DEFAULTS = [
        'hydration_enabled' => false,
        'hydration_interval_minutes' => 60,
        'hydration_start' => '08:00',
        'hydration_end' => '21:00',
        'hydration_goal_ml' => 2500,
        'glass_ml' => 250,
        'diet_enabled' => false,
        'diet_lead_minutes' => 0,
        'workout_enabled' => false,
        'workout_time' => '18:00',
        'workout_skip_rest_days' => true,
    ];

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'hydration_enabled' => 'boolean',
            'hydration_interval_minutes' => 'integer',
            'hydration_goal_ml' => 'integer',
            'glass_ml' => 'integer',
            'hydration_next_at' => 'datetime',
            'hydration_goal_met_on' => 'date',
            'diet_enabled' => 'boolean',
            'diet_lead_minutes' => 'integer',
            'workout_enabled' => 'boolean',
            'workout_skip_rest_days' => 'boolean',
            'workout_next_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** "08:00:00" (database) → "08:00". */
    public static function clock(?string $time): ?string
    {
        return $time === null ? null : substr($time, 0, 5);
    }
}
