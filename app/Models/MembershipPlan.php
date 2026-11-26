<?php

namespace App\Models;

use App\Enums\PlanDuration;
use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MembershipPlan extends Model
{
    use BelongsToGym, SoftDeletes;

    protected $fillable = [
        'name', 'duration_type', 'duration_days', 'max_freeze_days', 'price', 'description', 'features', 'is_active', 'is_public', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'duration_type' => PlanDuration::class,
            'price' => 'decimal:2',
            'features' => 'array',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function durationLabel(): string
    {
        return $this->duration_type === PlanDuration::Custom
            ? $this->duration_days.' days'
            : $this->duration_type->label();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
