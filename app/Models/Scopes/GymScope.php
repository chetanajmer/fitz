<?php

namespace App\Models\Scopes;

use App\Tenancy\TenantManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class GymScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenant = app(TenantManager::class);

        if ($gymId = $tenant->id()) {
            $builder->where($model->qualifyColumn('gym_id'), $gymId);

            return;
        }

        if (! $tenant->bypasses()) {
            // Fail closed: an actor without a gym (and without platform rights) sees nothing.
            $builder->whereRaw('1 = 0');
        }
    }
}
