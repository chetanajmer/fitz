<?php

namespace App\Services;

use App\Enums\MembershipStatus;
use App\Enums\PaymentMethod;
use App\Models\Gym;
use App\Models\Membership;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records manual payments (cash / UPI / bank transfer / card) against memberships.
 *
 * Gateway integration: the payments table already carries status, gateway,
 * gateway_payment_id and gateway_payload. A future gateway driver can store a
 * 'pending' Payment at checkout and apply it to the membership balance from its
 * webhook once confirmed. Today every payment is recorded as 'completed'.
 */
class PaymentService
{
    public function record(Membership $membership, float $amount, string|PaymentMethod $method, ?string $reference = null, ?string $paidAt = null, ?string $notes = null): Payment
    {
        $method = $method instanceof PaymentMethod ? $method : PaymentMethod::from($method);
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
        }

        return DB::transaction(function () use ($membership, $amount, $method, $reference, $paidAt, $notes) {
            /** @var Membership $locked */
            $locked = Membership::whereKey($membership->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === MembershipStatus::Cancelled) {
                throw ValidationException::withMessages(['amount' => 'Cannot record payments for a cancelled membership.']);
            }

            if ($amount > $locked->balance() + 0.001) {
                throw ValidationException::withMessages(['amount' => 'Amount exceeds the pending balance of '.money($locked->balance()).'.']);
            }

            // Serialise receipt numbering per gym.
            $gym = Gym::whereKey($locked->gym_id)->lockForUpdate()->first();
            $sequence = (int) Payment::where('gym_id', $gym->id)->max('sequence') + 1;

            $payment = new Payment;
            $payment->gym_id = $locked->gym_id;
            $payment->member_id = $locked->member_id;
            $payment->membership_id = $locked->id;
            $payment->sequence = $sequence;
            $payment->receipt_number = 'RCPT-'.$gym->id.'-'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
            $payment->amount = $amount;
            $payment->method = $method;
            $payment->reference = $reference ?: null;
            $payment->paid_at = $paidAt ? Carbon::parse($paidAt) : now();
            $payment->notes = $notes ?: null;
            $payment->status = 'completed';
            $payment->received_by = Auth::id();
            $payment->save();

            $locked->amount_paid = round((float) $locked->amount_paid + $amount, 2);
            $locked->save();

            return $payment;
        });
    }
}
