<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A one-time website sign-in code (platform-level, not tenant-scoped). Only its hash is stored. */
class LoginCode extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public static function hashCode(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    public function matches(string $code): bool
    {
        return hash_equals($this->code_hash, static::hashCode($code));
    }
}
