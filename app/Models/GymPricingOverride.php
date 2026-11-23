<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** Custom SaaS price per active member for one gym (Super Admin managed). */
class GymPricingOverride extends Model
{
    protected $fillable = ['price_per_member', 'effective_from', 'ends_on', 'notes'];

    protected function casts(): array
    {
        return [
            'price_per_member' => 'decimal:2',
            'effective_from' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Overrides covering a given date. */
    public function scopeActiveOn(Builder $query, $date): Builder
    {
        $date = Carbon::parse($date)->toDateString();

        return $query->whereDate('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $date));
    }

    public function isCurrent(): bool
    {
        return $this->effective_from->lte(Carbon::today()) && ($this->ends_on === null || $this->ends_on->gte(Carbon::today()));
    }
}
