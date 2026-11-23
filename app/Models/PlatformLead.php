<?php

namespace App\Models;

use App\Enums\LeadReason;
use App\Enums\LeadStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Platform-level lead captured from the member app. Intentionally NOT tenant-scoped:
 * only the Super Admin panel reads this model (routes are behind role:super_admin).
 */
class PlatformLead extends Model
{
    protected $fillable = ['name', 'city', 'status', 'follow_up_date', 'notes', 'assigned_gym_id'];

    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'reason' => LeadReason::class,
            'follow_up_date' => 'date',
            'last_requested_at' => 'datetime',
            'contacted_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function previousGym(): BelongsTo
    {
        return $this->belongsTo(Gym::class, 'previous_gym_id');
    }

    public function assignedGym(): BelongsTo
    {
        return $this->belongsTo(Gym::class, 'assigned_gym_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', LeadStatus::openValues());
    }

    public function mapUrl(): ?string
    {
        return $this->latitude !== null && $this->longitude !== null
            ? 'https://www.google.com/maps?q='.$this->latitude.','.$this->longitude
            : null;
    }
}
