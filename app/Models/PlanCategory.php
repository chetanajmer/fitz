<?php

namespace App\Models;

use App\Enums\FitnessPlanType;
use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class PlanCategory extends Model
{
    use BelongsToGym;

    protected $fillable = ['type', 'name', 'description', 'image_path', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'type' => FitnessPlanType::class,
            'is_active' => 'boolean',
        ];
    }

    public function plans(): HasMany
    {
        return $this->hasMany(FitnessPlan::class);
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    public function scopeOfType(Builder $query, FitnessPlanType|string $type): Builder
    {
        return $query->where('type', $type instanceof FitnessPlanType ? $type->value : $type);
    }
}
