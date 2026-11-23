<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class PricingRate extends Model
{
    protected $fillable = ['price_per_member', 'effective_from', 'notes'];

    protected function casts(): array
    {
        return [
            'price_per_member' => 'decimal:2',
            'effective_from' => 'date',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Rate in force on a date: the latest rate effective on/before it, falling back to the configured default. */
    public static function rateOn($date = null): float
    {
        $date = Carbon::parse($date ?? Carbon::today())->toDateString();

        $rate = static::whereDate('effective_from', '<=', $date)
            ->orderByDesc('effective_from')->orderByDesc('id')
            ->value('price_per_member');

        return (float) ($rate ?? config('gymai.default_price_per_member'));
    }
}
