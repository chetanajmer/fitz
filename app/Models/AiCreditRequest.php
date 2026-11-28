<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A request for extra AI credits. A member asks their gym admin (member_id set); a gym admin asks the
 * Super Admin for more in the gym's pool (member_id null). Approved credits count for the month approved.
 */
class AiCreditRequest extends Model
{
    use BelongsToGym;

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    protected $fillable = ['member_id', 'requested_by', 'credits', 'note'];

    protected function casts(): array
    {
        return [
            'credits' => 'integer',
            'granted_credits' => 'integer',
            'applies_month' => 'date',
            'decided_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** Requests a gym admin makes to the Super Admin. */
    public function scopeForGymPool(Builder $query): Builder
    {
        return $query->whereNull('member_id');
    }

    /** Requests members make to their gym admin. */
    public function scopeFromMembers(Builder $query): Builder
    {
        return $query->whereNotNull('member_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::PENDING);
    }

    /** Approved grants that count in the month of the given date. */
    public function scopeGrantedInMonth(Builder $query, ?Carbon $date = null): Builder
    {
        return $query->where('status', self::APPROVED)
            ->whereDate('applies_month', ($date ?? Carbon::now())->copy()->startOfMonth()->toDateString());
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }
}
