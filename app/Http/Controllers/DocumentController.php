<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use App\Models\Payment;
use App\Models\PlatformSetting;
use App\Models\SaasInvoice;
use App\Services\MemberImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Printable documents. Route model binding runs through the tenant scope, so a gym
 * user requesting another gym's id receives a 404.
 */
class DocumentController extends Controller
{
    public function receipt(Payment $payment)
    {
        $payment->load(['member', 'membership', 'receiver']);

        return view('documents.receipt', ['payment' => $payment, 'gym' => current_gym()]);
    }

    public function memberReceipt(Request $request, Payment $payment)
    {
        $member = $request->user()->memberProfile()->firstOrFail();
        abort_unless($payment->member_id === $member->id, 404);

        $payment->load(['member', 'membership', 'receiver']);

        return view('documents.receipt', ['payment' => $payment, 'gym' => current_gym()]);
    }

    public function membershipInvoice(Membership $membership)
    {
        $membership->load(['member', 'payments']);

        return view('documents.membership-invoice', ['membership' => $membership, 'gym' => current_gym()]);
    }

    public function gymSaasInvoice(SaasInvoice $invoice)
    {
        Gate::authorize('view', $invoice);

        return $this->saasInvoice($invoice);
    }

    public function platformSaasInvoice(SaasInvoice $invoice)
    {
        return $this->saasInvoice($invoice);
    }

    public function importSample(MemberImportService $import)
    {
        return response($import->sampleCsv(), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="gymai-members-sample.csv"',
        ]);
    }

    protected function saasInvoice(SaasInvoice $invoice)
    {
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
        ]);
    }
}
