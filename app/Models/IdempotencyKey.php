<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The stored response of a create request sent with an Idempotency-Key header (staff app).
 * Repeating the key within 24 hours returns this response instead of creating anything again.
 */
class IdempotencyKey extends Model
{
    public const TTL_HOURS = 24;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['response_status' => 'integer'];
    }
}
