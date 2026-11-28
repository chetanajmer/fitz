<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A gym admin or trainer asked (from the staff app) for their account to be deleted. Handled by people, never automatically. */
class StaffDeletionRequest extends Model
{
    use BelongsToGym;

    public const PENDING = 'pending';

    // Written by the staff API only.
    protected $fillable = [];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
