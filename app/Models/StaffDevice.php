<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A phone a gym admin or trainer is signed in on in the staff app, with its Firebase token. Separate from member devices. */
class StaffDevice extends Model
{
    use BelongsToGym;

    public const PLATFORMS = ['android', 'ios'];

    // Written by the staff API only.
    protected $fillable = [];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
