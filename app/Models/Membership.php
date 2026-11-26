<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use App\Enums\PlanDuration;
use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Membership extends Model
{
    use BelongsToGym;

    // Monetary and status fields are written by MembershipService / PaymentService only.
    protected $fillable = ['notes'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'cancelled_at' => 'datetime',
            'price' => 'decimal:2',
            'discount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'joining_fee' => 'decimal:2',
            'gst_rate' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'status' => MembershipStatus::class,
            'duration_type' => PlanDuration::class,
            'max_freeze_days' => 'integer',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'membership_plan_id')->withTrashed();
    }

    public function renewedFrom(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'renewed_from_id');
    }

    public function freezes(): HasMany
    {
        return $this->hasMany(MembershipFreeze::class)->orderBy('start_date');
    }

    public function freezeDaysUsed(): int
    {
        return (int) $this->freezes->sum('days');
    }

    public function freezeDaysLeft(): int
    {
        return max(0, (int) $this->max_freeze_days - $this->freezeDaysUsed());
    }

    /** The freeze covering the day (today by default), if any. */
    public function freezeOn(?Carbon $day = null): ?MembershipFreeze
    {
        $day ??= Carbon::today();

        return $this->freezes->first(fn (MembershipFreeze $f) => $f->covers($day));
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderByDesc('paid_at');
    }

    /**
     * GST inside an amount (prices include GST): the taxable value and the CGST / SGST halves. Null without GST.
     *
     * @return array{taxable: float, cgst: float, sgst: float, rate: float}|null
     */
    public function gstBreakup(?float $amount = null): ?array
    {
        if ($this->gst_rate === null || (float) $this->gst_rate <= 0) {
            return null;
        }

        $amount ??= (float) $this->total_amount;
        $rate = (float) $this->gst_rate;
        $taxable = round($amount * 100 / (100 + $rate), 2);
        $cgst = round(($amount - $taxable) / 2, 2);

        return ['taxable' => $taxable, 'cgst' => $cgst, 'sgst' => round($amount - $taxable - $cgst, 2), 'rate' => $rate];
    }

    public function balance(): float
    {
        return max(0, round((float) $this->total_amount - (float) $this->amount_paid, 2));
    }

    public function isPaid(): bool
    {
        return $this->balance() <= 0;
    }

    public function paymentStatus(): string
    {
        return match (true) {
            $this->isPaid() => 'paid',
            (float) $this->amount_paid > 0 => 'partial',
            default => 'unpaid',
        };
    }

    public function daysRemaining(): int
    {
        return max(0, (int) Carbon::today()->diffInDays($this->end_date, false));
    }

    /** Status derived from dates (the stored status is refreshed daily by a scheduled command). */
    public function computedStatus(): MembershipStatus
    {
        if ($this->status === MembershipStatus::Cancelled) {
            return MembershipStatus::Cancelled;
        }

        $today = Carbon::today();

        return match (true) {
            $this->end_date->lt($today) => MembershipStatus::Expired,
            $this->start_date->gt($today) => MembershipStatus::Upcoming,
            default => MembershipStatus::Active,
        };
    }

    // ---- Scopes ------------------------------------------------------------

    /** Not cancelled and covering the given date. Date-based so it is correct even before the daily refresh. */
    public function scopeValid(Builder $query, $date = null): Builder
    {
        $date = Carbon::parse($date ?? Carbon::today())->toDateString();

        return $query->where('status', '!=', MembershipStatus::Cancelled->value)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date);
    }

    /** Not cancelled and overlapping the given period. */
    public function scopeOverlapping(Builder $query, $start, $end): Builder
    {
        return $query->where('status', '!=', MembershipStatus::Cancelled->value)
            ->whereDate('start_date', '<=', Carbon::parse($end)->toDateString())
            ->whereDate('end_date', '>=', Carbon::parse($start)->toDateString());
    }

    public function scopeWithBalance(Builder $query): Builder
    {
        return $query->where('status', '!=', MembershipStatus::Cancelled->value)
            ->whereColumn('amount_paid', '<', 'total_amount');
    }
}
