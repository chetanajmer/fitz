<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Platform → gym invoice. Not tenant-scoped via global scope: access is controlled by
 * policy (gym admins may only view their own gym's invoices).
 */
class SaasInvoice extends Model
{
    protected $fillable = ['notes'];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'issued_on' => 'date',
            'due_on' => 'date',
            'rate_per_member' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'adjustment' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'status' => InvoiceStatus::class,
            'meta' => 'array',
        ];
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SaasPayment::class)->orderByDesc('paid_at');
    }

    public function balance(): float
    {
        if ($this->status === InvoiceStatus::Void) {
            return 0;
        }

        return max(0, round((float) $this->total - (float) $this->amount_paid, 2));
    }

    public function isOverdue(): bool
    {
        return $this->balance() > 0 && $this->due_on->isPast();
    }

    public function scopeOutstanding($query)
    {
        return $query->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::Partial->value]);
    }
}
