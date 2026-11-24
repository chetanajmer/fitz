<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AiReport;
use App\Models\Member;
use App\Services\AiAccessService;
use App\Services\AiReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * AI coach in the member app: consent, credits, and AI progress reports / photo comparisons.
 * Reports are generated in the background; poll GET /me/ai/reports/{id} or wait for the push.
 */
class AiCoachController extends ApiController
{
    public function __construct(protected AiAccessService $access, protected AiReportService $reports) {}

    /** GET /api/v1/me/ai — availability, consent, credits left, features and their cost. */
    public function status(Request $request): JsonResponse
    {
        return $this->success($this->access->status($this->member($request)), 'AI coach.');
    }

    /** POST /api/v1/me/ai/consent  { accepted: true|false } */
    public function consent(Request $request): JsonResponse
    {
        $data = $request->validate(['accepted' => ['required', 'boolean']]);
        $member = $this->member($request);
        $this->access->setConsent($member, (bool) $data['accepted']);

        return $this->success($this->access->status($member), $data['accepted'] ? 'Thanks! AI coach is ready.' : 'Consent withdrawn.');
    }

    /** GET /api/v1/me/ai/reports — latest 50, newest first. */
    public function index(Request $request): JsonResponse
    {
        $reports = $this->member($request)->aiReports()->limit(50)->get();

        return $this->success(['reports' => $reports->map(fn (AiReport $r) => $this->reports->toArray($r))->all()], 'AI reports.');
    }

    /** GET /api/v1/me/ai/reports/{reportId} */
    public function show(Request $request, int $reportId): JsonResponse
    {
        $report = $this->member($request)->aiReports()->findOrFail($reportId);

        return $this->success($this->reports->toArray($report), 'AI report.');
    }

    /** POST /api/v1/me/ai/reports  { type: progress | photo_comparison, before_photo_id?, after_photo_id? } */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(AiReport::TYPES))],
            'before_photo_id' => ['required_if:type,'.AiReport::PHOTO_COMPARISON, 'nullable', 'integer'],
            'after_photo_id' => ['required_if:type,'.AiReport::PHOTO_COMPARISON, 'nullable', 'integer'],
        ]);

        $member = $this->member($request);
        $report = $this->reports->request($member, $data['type'], 'member', null, $data['before_photo_id'] ?? null, $data['after_photo_id'] ?? null);

        return $this->success(
            ['report' => $this->reports->toArray($report->refresh()), 'credits' => $this->access->credits($member)],
            'Your report is being prepared. We will notify you when it is ready.',
            202,
        );
    }

    protected function member(Request $request): Member
    {
        return $request->user('member');
    }
}
