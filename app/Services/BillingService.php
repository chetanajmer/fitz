<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\MemberStatus;
use App\Enums\PaymentMethod;
use App\Models\Gym;
use App\Models\GymPricingOverride;
use App\Models\Member;
use App\Models\PlatformSetting;
use App\Models\PricingRate;
use App\Models\SaasInvoice;
use App\Models\SaasPayment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SaaS billing: price per active member per month (default ₹20).
 *
 * Billable member = member with status "active" who holds a non-cancelled membership
 * whose dates overlap the billing month. Enquiries are never members, so they are
 * excluded by construction; inactive members and memberships that ended before the
 * month (expired) are excluded by the query.
 */
class BillingService
{
    public function billableMembersQuery(Gym $gym, Carbon $periodStart, Carbon $periodEnd)
    {
        return Member::forGym($gym)
            ->where('status', MemberStatus::Active->value)
            ->whereHas('memberships', fn ($q) => $q->withoutGlobalScopes()->overlapping($periodStart, $periodEnd));
    }

    public function billableMemberCount(Gym $gym, Carbon $periodStart, Carbon $periodEnd): int
    {
        return $this->billableMembersQuery($gym, $periodStart, $periodEnd)->count();
    }

    public function currentRate(): float
    {
        return PricingRate::rateOn(Carbon::today());
    }

    /**
     * Price per active member for a gym on a date: the gym's custom price if one covers
     * that date, otherwise the platform rate.
     *
     * @return array{rate: float, custom: bool, override: ?GymPricingOverride}
     */
    public function rateForGym(Gym $gym, $date = null): array
    {
        $date = Carbon::parse($date ?? Carbon::today());
        $override = GymPricingOverride::where('gym_id', $gym->id)->activeOn($date)
            ->orderByDesc('effective_from')->orderByDesc('id')->first();

        return $override
            ? ['rate' => (float) $override->price_per_member, 'custom' => true, 'override' => $override]
            : ['rate' => PricingRate::rateOn($date), 'custom' => false, 'override' => null];
    }

    /** Estimate for the running month (not an invoice). */
    public function currentMonthEstimate(Gym $gym): array
    {
        $start = Carbon::today()->startOfMonth();
        $end = Carbon::today()->endOfMonth();
        $count = $this->billableMemberCount($gym, $start, $end);
        $pricing = $this->rateForGym($gym, $start);
        $rate = $pricing['rate'];

        return ['members' => $count, 'rate' => $rate, 'custom' => $pricing['custom'], 'amount' => round($count * $rate, 2), 'period_start' => $start, 'period_end' => $end];
    }

    /**
     * Generate (or return the existing) invoice for a gym and month.
     * Values are snapshotted on the invoice so later price changes never alter it.
     */
    public function generateInvoice(Gym $gym, Carbon $month): SaasInvoice
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        if ($start->gte(Carbon::today()->startOfMonth())) {
            throw ValidationException::withMessages(['month' => 'Invoices can only be generated for completed months.']);
        }

        return DB::transaction(function () use ($gym, $start, $end) {
            $existing = SaasInvoice::where('gym_id', $gym->id)->whereDate('period_start', $start)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            $count = $this->billableMemberCount($gym, $start, $end);
            $pricing = $this->rateForGym($gym, $start);
            $rate = $pricing['rate'];
            $subtotal = round($count * $rate, 2);

            $invoice = new SaasInvoice;
            $invoice->gym_id = $gym->id;
            $invoice->invoice_number = $this->nextInvoiceNumber($start);
            $invoice->period_start = $start;
            $invoice->period_end = $end;
            $invoice->active_members = $count;
            $invoice->rate_per_member = $rate;
            $invoice->subtotal = $subtotal;
            $invoice->adjustment = 0;
            $invoice->total = $subtotal;
            $invoice->amount_paid = 0;
            $invoice->status = $subtotal > 0 ? InvoiceStatus::Unpaid : InvoiceStatus::Paid;
            $invoice->issued_on = Carbon::today();
            $invoice->due_on = Carbon::today()->addDays((int) PlatformSetting::get('invoice_due_days', 15));
            $invoice->meta = [
                'gym_name' => $gym->name,
                'gym_city' => $gym->city,
                'owner_email' => $gym->owner?->email,
                'billing_rule' => 'Active members with a valid membership during the period',
                'pricing' => $pricing['custom'] ? 'custom' : 'standard',
                'standard_rate' => PricingRate::rateOn($start),
            ];
            $invoice->save();

            return $invoice;
        });
    }

    /** @return array{created:int, skipped:int} */
    public function generateForAllGyms(Carbon $month): array
    {
        $created = 0;
        $skipped = 0;

        Gym::where('created_at', '<=', $month->copy()->endOfMonth())
            ->where('status', '!=', 'deactivated')
            ->each(function (Gym $gym) use ($month, &$created, &$skipped) {
                $invoice = $this->generateInvoice($gym, $month);
                $invoice->wasRecentlyCreated ? $created++ : $skipped++;
            });

        return compact('created', 'skipped');
    }

    public function recordPayment(SaasInvoice $invoice, float $amount, string $method, ?string $reference = null, ?string $paidAt = null, ?string $notes = null): SaasPayment
    {
        $amount = round($amount, 2);

        return DB::transaction(function () use ($invoice, $amount, $method, $reference, $paidAt, $notes) {
            $locked = SaasInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === InvoiceStatus::Void) {
                throw ValidationException::withMessages(['amount' => 'Cannot pay a void invoice.']);
            }
            if ($amount <= 0 || $amount > $locked->balance() + 0.001) {
                throw ValidationException::withMessages(['amount' => 'Amount must be between 0 and the outstanding '.money($locked->balance()).'.']);
            }

            $payment = new SaasPayment;
            $payment->gym_id = $locked->gym_id;
            $payment->saas_invoice_id = $locked->id;
            $payment->amount = $amount;
            $payment->method = PaymentMethod::from($method);
            $payment->reference = $reference ?: null;
            $payment->paid_at = $paidAt ? Carbon::parse($paidAt) : now();
            $payment->notes = $notes ?: null;
            $payment->recorded_by = Auth::id();
            $payment->save();

            $locked->amount_paid = round((float) $locked->amount_paid + $amount, 2);
            $locked->status = $locked->balance() <= 0 ? InvoiceStatus::Paid : InvoiceStatus::Partial;
            $locked->save();

            return $payment;
        });
    }

    public function adjust(SaasInvoice $invoice, float $adjustment, ?string $notes = null): SaasInvoice
    {
        $invoice->adjustment = round($adjustment, 2);
        $invoice->total = max(0, round((float) $invoice->subtotal + $invoice->adjustment, 2));
        $invoice->notes = $notes ?? $invoice->notes;
        $invoice->status = match (true) {
            (float) $invoice->amount_paid >= (float) $invoice->total => InvoiceStatus::Paid,
            (float) $invoice->amount_paid > 0 => InvoiceStatus::Partial,
            default => InvoiceStatus::Unpaid,
        };
        $invoice->save();

        return $invoice;
    }

    public function void(SaasInvoice $invoice): SaasInvoice
    {
        if ((float) $invoice->amount_paid > 0) {
            throw ValidationException::withMessages(['invoice' => 'Invoices with payments cannot be voided.']);
        }

        $invoice->status = InvoiceStatus::Void;
        $invoice->save();

        return $invoice;
    }

    public function outstandingFor(Gym $gym): float
    {
        return (float) SaasInvoice::where('gym_id', $gym->id)->outstanding()
            ->selectRaw('COALESCE(SUM(total - amount_paid), 0) as due')->value('due');
    }

    public function setRate(float $price, string $effectiveFrom, ?string $notes = null): PricingRate
    {
        $rate = new PricingRate(['price_per_member' => round($price, 2), 'effective_from' => $effectiveFrom, 'notes' => $notes]);
        $rate->created_by = Auth::id();
        $rate->save();

        return $rate;
    }

    /**
     * Give a gym a custom price from a date. Any open-ended earlier custom price is closed
     * the day before, so price history stays continuous and past invoices are untouched.
     */
    public function setCustomPrice(Gym $gym, float $price, string $effectiveFrom, ?string $endsOn = null, ?string $notes = null): GymPricingOverride
    {
        $from = Carbon::parse($effectiveFrom)->startOfDay();

        if ($from->lt(Carbon::today()->startOfMonth())) {
            throw ValidationException::withMessages(['customFrom' => 'Custom pricing cannot start before the current month — past invoices must stay unchanged.']);
        }

        return DB::transaction(function () use ($gym, $price, $from, $endsOn, $notes) {
            GymPricingOverride::where('gym_id', $gym->id)
                ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $from))
                ->whereDate('effective_from', '<', $from)
                ->update(['ends_on' => $from->copy()->subDay()->toDateString(), 'updated_at' => now()]);

            // A later-starting override would be superseded by this one.
            GymPricingOverride::where('gym_id', $gym->id)->whereDate('effective_from', '>=', $from)->delete();

            $override = new GymPricingOverride([
                'price_per_member' => round($price, 2),
                'effective_from' => $from->toDateString(),
                'ends_on' => $endsOn ?: null,
                'notes' => $notes,
            ]);
            $override->gym_id = $gym->id;
            $override->created_by = Auth::guard('web')->id();
            $override->save();

            return $override;
        });
    }

    /** Return a gym to the standard platform price from a date (default: start of next month). */
    public function endCustomPrice(Gym $gym, ?string $from = null): int
    {
        $from = Carbon::parse($from ?? Carbon::today()->addMonthNoOverflow()->startOfMonth())->startOfDay();

        if ($from->lt(Carbon::today()->startOfMonth())) {
            throw ValidationException::withMessages(['customFrom' => 'Cannot change pricing before the current month.']);
        }

        return DB::transaction(function () use ($gym, $from) {
            GymPricingOverride::where('gym_id', $gym->id)->whereDate('effective_from', '>=', $from)->delete();

            return GymPricingOverride::where('gym_id', $gym->id)
                ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $from))
                ->update(['ends_on' => $from->copy()->subDay()->toDateString(), 'updated_at' => now()]);
        });
    }

    protected function nextInvoiceNumber(Carbon $periodStart): string
    {
        $prefix = PlatformSetting::get('invoice_prefix', 'GYMAI').'-'.$periodStart->format('Ym').'-';
        $last = SaasInvoice::where('invoice_number', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('invoice_number')->value('invoice_number');
        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
