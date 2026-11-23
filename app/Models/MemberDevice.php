<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A phone the member is signed in on, with its Firebase Cloud Messaging token. */
class MemberDevice extends Model
{
    use BelongsToGym;

    public const PLATFORMS = ['android', 'ios'];

    // Written by PushNotificationService only.
    protected $fillable = [];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
