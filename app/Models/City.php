<?php

namespace App\Models;

use Database\Factories\CityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A city Gymai operates in. Platform-level (not tenant-scoped): managed by the Super Admin,
 * shown to gyms when they pick their city and to members when they sign up from the app.
 */
class City extends Model
{
    /** @use HasFactory<CityFactory> */
    use HasFactory;

    protected $fillable = ['name', 'state', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function gyms(): HasMany
    {
        return $this->hasMany(Gym::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Case-insensitive lookup by name, so "pune " and "Pune" are the same city. */
    public static function findByName(string $name): ?self
    {
        return static::whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])->first();
    }
}
