<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Audit record + single-use token for Super Admin "login as" magic links. */
class ImpersonationToken extends Model
{
    protected $fillable = [];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function status(): string
    {
        return match (true) {
            $this->used_at !== null => 'used',
            $this->expires_at->isPast() => 'expired',
            default => 'active',
        };
    }
}
