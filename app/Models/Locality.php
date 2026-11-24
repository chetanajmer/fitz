<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An area inside a city (e.g. Jagatpura, Jaipur). Platform-level, not tenant-scoped. */
class Locality extends Model
{
    protected $fillable = ['name'];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function gyms(): HasMany
    {
        return $this->hasMany(Gym::class);
    }
}
