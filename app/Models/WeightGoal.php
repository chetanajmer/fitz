<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeightGoal extends Model
{
    use BelongsToGym;

    public const ACTIVE = 'active';

    public const ACHIEVED = 'achieved';

    public const REPLACED = 'replaced';

    protected $table = 'member_weight_goals';

    // Everything is written by WeightTrackingService.
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'start_weight_kg' => 'decimal:2',
            'target_weight_kg' => 'decimal:2',
            'achieved_weight_kg' => 'decimal:2',
            'start_date' => 'date',
            'target_date' => 'date',
            'achieved_at' => 'datetime',
            'celebrated_at' => 'datetime',
            'near_notified_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }

    /** lose | gain | maintain */
    public function direction(): string
    {
        $diff = (float) $this->target_weight_kg - (float) $this->start_weight_kg;

        return match (true) {
            $diff < 0 => 'lose',
            $diff > 0 => 'gain',
            default => 'maintain',
        };
    }

    public function isReachedBy(float $weight): bool
    {
        return match ($this->direction()) {
            'lose' => $weight <= (float) $this->target_weight_kg,
            'gain' => $weight >= (float) $this->target_weight_kg,
            default => abs($weight - (float) $this->target_weight_kg) <= 0.5,
        };
    }
}
