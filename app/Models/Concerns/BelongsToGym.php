<?php

namespace App\Models\Concerns;

use App\Models\Gym;
use App\Models\Scopes\GymScope;
use App\Tenancy\TenantManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Tenant-owned model behaviour:
 *  - every query is constrained to the current gym (GymScope);
 *  - gym_id is stamped from the server-side tenant on create, and any mismatch is rejected;
 *  - gym_id can never be changed after creation.
 */
trait BelongsToGym
{
    public static function bootBelongsToGym(): void
    {
        static::addGlobalScope(new GymScope);

        static::creating(function ($model) {
            $tenantId = app(TenantManager::class)->id();

            if ($tenantId === null) {
                if (empty($model->gym_id)) {
                    throw new LogicException(class_basename($model).' cannot be created without a gym context.');
                }

                return; // Platform/CLI context creating for an explicit gym.
            }

            if (! empty($model->gym_id) && (int) $model->gym_id !== $tenantId) {
                throw new LogicException('Cross-tenant write blocked for '.class_basename($model).'.');
            }

            $model->gym_id = $tenantId;
        });

        static::updating(function ($model) {
            if ($model->isDirty('gym_id')) {
                throw new LogicException('gym_id of '.class_basename($model).' cannot be changed.');
            }
        });
    }

    public function initializeBelongsToGym(): void
    {
        // gym_id must never be mass assignable from user input.
        $this->guarded = array_values(array_unique(array_merge($this->guarded, ['gym_id'])));
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function scopeForGym(Builder $query, Gym|int $gym): Builder
    {
        return $query->withoutGlobalScope(GymScope::class)
            ->where($this->qualifyColumn('gym_id'), $gym instanceof Gym ? $gym->id : $gym);
    }
}
