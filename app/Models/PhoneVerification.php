<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * OTP check for a phone number that is not a member yet (app sign-up). Platform-level,
 * not tenant-scoped. A verified OTP issues a single-use registration token; only hashes
 * of the code and the token are stored.
 */
class PhoneVerification extends Model
{
    protected $fillable = [];

    protected $hidden = ['code_hash', 'token_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'token_expires_at' => 'datetime',
            'token_used_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public static function hashCode(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function matches(string $code): bool
    {
        return hash_equals($this->code_hash, static::hashCode($code));
    }
}
