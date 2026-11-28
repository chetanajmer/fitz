<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AiReport;
use App\Models\Member;
use App\Services\AiAccessService;
use App\Services\AiCreditRequestService;
use App\Services\AiReportService;
use App\Services\ConsentService;
use App\Services\TransformationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * AI coach in the member app: consent, credits, and AI progress reports / photo comparisons.
 * Reports are generated in the background; poll GET /me/ai/reports/{id} or wait for the push.
 */
class AiCoachController extends ApiController
{
    public function __construct(
        protected AiAccessService $access,
        protected AiReportService $reports,
        protected TransformationService $transformation,
        protected ConsentService $consents,
    ) {}

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
        $this->access->setConsent($member, (bool) $data['accepted'], $request);

        return $this->success($this->access->status($member), $data['accepted'] ? 'Thanks! AI coach is ready.' : 'Consent withdrawn. Your AI reports and progress photos were deleted.');
    }

    /** GET /api/v1/me/ai/reports — latest 50, newest first. */
    public function index(Request $request): JsonResponse
    {
        $member = $this->member($request);
        $reports = $member->aiReports()->with(['beforePhoto', 'afterPhoto'])->limit(50)->get();
        $summary = $this->transformation->summary($member);

        return $this->success(['reports' => $reports->map(fn (AiReport $r) => $this->payload($r, $member, $summary))->all()], 'AI reports.');
    }

    /** GET /api/v1/me/ai/reports/{reportId} */
    public function show(Request $request, int $reportId): JsonResponse
    {
        $member = $this->member($request);
        $report = $member->aiReports()->with(['beforePhoto', 'afterPhoto'])->findOrFail($reportId);

        return $this->success($this->payload($report, $member), 'AI report.');
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

        if ($data['type'] === AiReport::PHOTO_COMPARISON) {
            $this->consents->requirePhotoConsent($member);
        }

        $report = $this->reports->request($member, $data['type'], 'member', null, $data['before_photo_id'] ?? null, $data['after_photo_id'] ?? null);

        return $this->success(
            ['report' => $this->payload($report->refresh(), $member), 'credits' => $this->access->credits($member)],
            'Your report is being prepared. We will notify you when it is ready.',
            202,
        );
    }

    /**
     * POST /api/v1/me/ai/credit-requests  { credits: 1-20, note? } — out of credits: ask the gym admin for more.
     * Shown when GET /me/ai returns can_request_credits = true.
     */
    public function requestCredits(Request $request, AiCreditRequestService $requests): JsonResponse
    {
        $data = $request->validate([
            'credits' => ['required', 'integer', 'min:1', 'max:'.AiCreditRequestService::MEMBER_MAX],
            'note' => ['nullable', 'string', 'max:300'],
        ]);
        $member = $this->member($request);
        $requests->requestForMember($member, (int) $data['credits'], $data['note'] ?? null);

        return $this->success($this->access->status($member), 'Request sent. Your gym will review it soon.', 201);
    }

    /**
     * The report plus its highlight card and photo URLs.
     *
     * @param  array<string, mixed>|null  $summary
     * @return array<string, mixed>
     */
    protected function payload(AiReport $report, Member $member, ?array $summary = null): array
    {
        return $this->transformation->apiReport($report, $this->reports->toArray($report), $member, $summary ?? $this->transformation->summary($member));
    }

    protected function member(Request $request): Member
    {
        return $request->user('member');
    }
}
