<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One-time login code for the member mobile app. Only a keyed hash of the code is stored.
 * Not tenant-scoped: OTP login happens before any gym context exists.
 */
class MemberOtp extends Model
{
    protected $fillable = [];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withoutGlobalScope(Scopes\GymScope::class);
    }

    public static function hashCode(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    public function matches(string $code): bool
    {
        return hash_equals($this->code_hash, static::hashCode($code));
    }

    public function isUsable(): bool
    {
        return $this->consumed_at === null
            && $this->expires_at->isFuture()
            && $this->attempts < (int) config('gymai.otp.max_attempts', 5);
    }
}
