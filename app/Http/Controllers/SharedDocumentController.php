<?php

namespace App\Http\Controllers;

use App\Models\Gym;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\PlatformSetting;
use App\Models\SaasInvoice;
use App\Tenancy\TenantManager;
use Illuminate\Http\Response;

/**
 * Receipts, membership invoices and the gym's Fitnix invoices opened from a signed link (the staff app's "share" / "open" buttons).
 * The signature (checked by the "signed" middleware) is the only access check: links expire after
 * 10 minutes, or after 7 days when made for sharing on WhatsApp. The document renders in its gym's context.
 */
class SharedDocumentController extends Controller
{
    public function __construct(protected TenantManager $tenant) {}

    public function receipt(int $paymentId): Response
    {
        $payment = Payment::withoutGlobalScopes()->findOrFail($paymentId);

        return $this->render($payment->gym_id, function (Gym $gym) use ($payment) {
            $payment->load(['member', 'membership', 'receiver']);

            return view('documents.receipt', ['payment' => $payment, 'gym' => $gym])->render();
        });
    }

    public function invoice(int $membershipId): Response
    {
        $membership = Membership::withoutGlobalScopes()->findOrFail($membershipId);

        return $this->render($membership->gym_id, function (Gym $gym) use ($membership) {
            $membership->load(['member', 'payments']);

            return view('documents.membership-invoice', ['membership' => $membership, 'gym' => $gym])->render();
        });
    }

    /** A Fitnix (SaaS) invoice of the gym, from the staff app's billing screen. */
    public function saasInvoice(int $invoiceId): Response
    {
        $invoice = SaasInvoice::findOrFail($invoiceId);

        return $this->render($invoice->gym_id, function () use ($invoice) {
            $invoice->load(['gym.owner', 'payments']);

            return view('documents.saas-invoice', [
                'invoice' => $invoice,
                'platform' => [
                    'name' => PlatformSetting::get('platform_name'),
                    'email' => PlatformSetting::get('support_email'),
                    'phone' => PlatformSetting::get('support_phone'),
                    'address' => PlatformSetting::get('company_address'),
                    'gst' => PlatformSetting::get('gst_number'),
                ],
            ])->render();
        });
    }

    /** @param  \Closure(Gym): string  $view */
    protected function render(int $gymId, \Closure $view): Response
    {
        $gym = Gym::findOrFail($gymId);

        return response($this->tenant->runAs($gym, fn () => $view($gym)))
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
