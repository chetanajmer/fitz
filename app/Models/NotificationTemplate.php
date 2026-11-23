<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Platform-wide wording, default time and rules of an automatic member notification (Super Admin only). */
class NotificationTemplate extends Model
{
    // Written by NotificationSettings only.
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'params' => 'array',
        ];
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
