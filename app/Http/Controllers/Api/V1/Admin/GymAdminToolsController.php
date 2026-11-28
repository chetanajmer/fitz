<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ApiException;
use App\Models\AiReport;
use App\Models\GymAiSetting;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\SaasInvoice;
use App\Models\SaasPayment;
use App\Services\AiAccessService;
use App\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Gym-admin pages of the web panel for the staff app:
 *  - AI coach settings (ai.manage): switch it on, who may use it, credits per member;
 *  - Fitnix billing (gym.billing): this month's estimate, the gym's Fitnix invoices and a link to each one.
 */
class GymAdminToolsController extends AdminController
{
    // ---- AI coach --------------------------------------------------------------

    /** GET /admin/ai-coach */
    public function aiCoach(AiAccessService $access): JsonResponse
    {
        return $this->success($this->aiCoachData($access), 'AI coach.');
    }

    /** PUT /admin/ai-coach — partial: { enabled?, eligibility?, plan_ids?, member_limit?, auto_monthly_report? } */
    public function updateAiCoach(Request $request, AiAccessService $access): JsonResponse
    {
        $gym = current_gym();
        $settings = $access->settingsFor($gym);
        $eligibility = $request->input('eligibility', $settings->eligibility);

        $data = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'eligibility' => ['sometimes', Rule::in(array_keys(GymAiSetting::ELIGIBILITY))],
            'plan_ids' => [Rule::requiredIf($eligibility === 'plans' && empty($settings->eligible_plan_ids)), 'array', $eligibility === 'plans' ? 'min:1' : 'nullable'],
            'plan_ids.*' => ['integer', Rule::exists('membership_plans', 'id')->where('gym_id', $gym->id)],
            'member_limit' => ['sometimes', 'integer', 'min:1', 'max:'.$settings->member_ceiling],
            'auto_monthly_report' => ['sometimes', 'boolean'],
        ], ['plan_ids.min' => 'Pick at least one plan.', 'plan_ids.required' => 'Pick at least one plan.', 'member_limit.max' => 'Fitnix allows at most :max credits per member per month.'],
            ['member_limit' => 'credits per member']);

        $settings->fill(array_filter([
            'enabled' => $data['enabled'] ?? null,
            'eligibility' => $data['eligibility'] ?? null,
            'eligible_plan_ids' => array_key_exists('plan_ids', $data) ? array_map('intval', $data['plan_ids'] ?? []) : null,
            'member_limit' => $data['member_limit'] ?? null,
            'auto_monthly_report' => $data['auto_monthly_report'] ?? null,
        ], fn ($value) => $value !== null))->save();

        return $this->success($this->aiCoachData($access), 'AI coach settings saved.');
    }

    // ---- Fitnix billing --------------------------------------------------------------

    /** GET /admin/billing?page= — the running month's estimate, what is outstanding, and invoices (newest first). */
    public function billing(Request $request, BillingService $billing): JsonResponse
    {
        $gym = current_gym();
        $estimate = $billing->currentMonthEstimate($gym);
        $page = SaasInvoice::where('gym_id', $gym->id)->with('payments')->latest('period_start')->paginate($this->perPage($request));

        return $this->success([
            'estimate' => [
                'period_start' => $estimate['period_start']->toDateString(),
                'period_end' => $estimate['period_end']->toDateString(),
                'members' => $estimate['members'],
                'rate_per_member' => (float) $estimate['rate'],
                'custom_rate' => (bool) $estimate['custom'],
                'amount' => (float) $estimate['amount'],
            ],
            'outstanding' => (float) $billing->outstandingFor($gym),
            'invoices' => collect($page->items())->map(fn (SaasInvoice $invoice) => $this->invoiceData($invoice))->values(),
            'pagination' => $this->pagination($page),
        ], 'Fitnix billing.');
    }

    /** GET /admin/billing/invoices/{id}/document?share=0|1 — a signed link to the printable Fitnix invoice. */
    public function invoiceDocument(Request $request, int $invoiceId): JsonResponse
    {
        $invoice = SaasInvoice::where('gym_id', current_gym()->id)->find($invoiceId)
            ?? throw new ApiException('Invoice not found.', 'NOT_FOUND', 404);

        return $this->success($this->documentLink($request, 'documents.shared.saas-invoice', ['invoiceId' => $invoice->id]), 'Invoice link.');
    }

    /** @return array<string, mixed> */
    protected function aiCoachData(AiAccessService $access): array
    {
        $gym = current_gym();
        $settings = $access->settingsFor($gym);

        return [
            'settings' => [
                'enabled' => (bool) $settings->enabled,
                'eligibility' => $settings->eligibility,
                'plan_ids' => array_map('intval', $settings->eligible_plan_ids ?? []),
                'member_limit' => (int) $settings->member_limit,
                'auto_monthly_report' => (bool) $settings->auto_monthly_report,
            ],
            'fitnix' => [
                'available' => $access->platformEnabled() && $settings->allowed,
                'platform_enabled' => $access->platformEnabled(),
                'allowed_for_gym' => (bool) $settings->allowed,
                'photos_allowed' => (bool) $settings->photos_allowed,
                'monthly_credits' => (int) $settings->monthly_credits,
                'member_ceiling' => (int) $settings->member_ceiling,
            ],
            'usage' => [
                'credits_used_this_month' => $access->gymUsed($gym),
                'credits_left_this_month' => max(0, (int) $settings->monthly_credits - $access->gymUsed($gym)),
            ],
            'costs' => [AiReport::PROGRESS => $access->cost(AiReport::PROGRESS), AiReport::PHOTO_COMPARISON => $access->cost(AiReport::PHOTO_COMPARISON)],
            'eligibility_options' => collect(GymAiSetting::ELIGIBILITY)->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values(),
            'plans' => MembershipPlan::orderBy('name')->get(['id', 'name'])->map(fn (MembershipPlan $p) => ['id' => $p->id, 'name' => $p->name])->values(),
            'hand_picked_members' => Member::where('ai_access', true)->count(),
            'consented_members' => Member::whereNotNull('ai_consent_at')->count(),
            'recent_reports' => AiReport::where('status', '!=', AiReport::ERASED)->with('member:id,gym_id,trainer_id,name,member_code,phone,photo_path')
                ->latest('id')->limit(15)->get()->map(fn (AiReport $r) => [
                    'id' => $r->id,
                    'type' => $r->type,
                    'type_label' => AiReport::TYPES[$r->type] ?? $r->type,
                    'status' => $r->status,
                    'credits' => (int) $r->credits,
                    'requested_by' => $r->requested_by,
                    'member' => $this->memberBrief($r->member),
                    'created_at' => $this->iso($r->created_at),
                ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    protected function invoiceData(SaasInvoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'period_start' => $invoice->period_start->toDateString(),
            'period_end' => $invoice->period_end->toDateString(),
            'active_members' => (int) $invoice->active_members,
            'rate_per_member' => (float) $invoice->rate_per_member,
            'subtotal' => (float) $invoice->subtotal,
            'adjustment' => (float) $invoice->adjustment,
            'total' => (float) $invoice->total,
            'amount_paid' => (float) $invoice->amount_paid,
            'balance' => $invoice->balance(),
            'status' => $invoice->status->value,
            'overdue' => $invoice->isOverdue(),
            'issued_on' => $invoice->issued_on->toDateString(),
            'due_on' => $invoice->due_on->toDateString(),
            'payments' => $invoice->payments->map(fn (SaasPayment $p) => [
                'id' => $p->id,
                'amount' => (float) $p->amount,
                'method' => $p->method?->value,
                'reference' => $p->reference,
                'paid_at' => $this->iso($p->paid_at),
            ])->values(),
        ];
    }
}
