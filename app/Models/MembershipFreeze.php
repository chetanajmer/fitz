<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** A pause of a membership. Written by MembershipService only. */
class MembershipFreeze extends Model
{
    use BelongsToGym;

    protected $fillable = [];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'days' => 'integer'];
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function covers(Carbon $day): bool
    {
        return $this->start_date->lte($day) && $this->end_date->gte($day);
    }
}
