<?php

namespace App\Models;

use App\Models\Scopes\GymScope;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Sanctum tokens are resolved before any tenant context exists, so the tokenable
 * (a tenant-scoped Member) must be loaded without the gym scope. Soft-delete
 * scoping is kept, so tokens of deleted members stop working.
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    public function tokenable(): MorphTo
    {
        return $this->morphTo('tokenable')->withoutGlobalScope(GymScope::class);
    }
}
