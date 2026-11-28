<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ApiException;
use App\Models\AiReport;
use App\Models\Member;
use App\Models\MemberMeasurement;
use App\Models\ProgressPhoto;
use App\Models\WeightGoal;
use App\Models\WeightLog;
use App\Services\AiAccessService;
use App\Services\AiReportService;
use App\Services\BodyProgressService;
use App\Services\TransformationService;
use App\Services\WeightTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A member's progress for the staff app, like the "Progress" and "AI Progress" tabs of the web member page:
 *  - weight: weigh-ins and the target weight (anyone who can see the member: members.view);
 *  - body: measurements, progress photos and AI reports (ai.view, for members they can see);
 *  - the gym-wide weight tracking lists (due for a weigh-in, close to target, achieved).
 * Weight responses use the same shape as the member app's GET /me/weight.
 */
class MemberProgressController extends AdminController
{
    public function __construct(protected WeightTrackingService $weights, protected BodyProgressService $body) {}

    // ---- Weight ----------------------------------------------------------------

    /** GET /admin/members/{id}/weight */
    public function weight(Request $request, int $memberId): JsonResponse
    {
        return $this->success($this->weights->summary($this->visibleMemberOr404($request, $memberId), 120), 'Weight progress.');
    }

    /** POST /admin/members/{id}/weight { weight_kg, measured_on?, note? } — the same day again corrects that reading. */
    public function recordWeight(Request $request, int $memberId): JsonResponse
    {
        $member = $this->visibleMemberOr404($request, $memberId);
        $data = $request->validate([
            'weight_kg' => ['required', 'numeric', 'between:'.WeightTrackingService::MIN_KG.','.WeightTrackingService::MAX_KG],
            'measured_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->weights->record($member, (float) $data['weight_kg'], $data['measured_on'] ?? null, 'staff', $this->staff($request), $data['note'] ?? null);

        return $this->success(['goal_achieved' => $result['goal_achieved']] + $this->weights->summary($member, 120),
            $result['goal_achieved'] ? "{$member->name} reached their target weight!" : 'Weight saved.', 201);
    }

    /** DELETE /admin/members/{id}/weight/{logId} */
    public function deleteWeight(Request $request, int $memberId, int $logId): JsonResponse
    {
        $member = $this->visibleMemberOr404($request, $memberId);

        if (! WeightLog::where('member_id', $member->id)->whereKey($logId)->exists()) {
            throw new ApiException('Reading not found.', 'NOT_FOUND', 404);
        }

        $this->weights->deleteLog($member, $logId);

        return $this->success($this->weights->summary($member, 120), 'Reading deleted.');
    }

    /** PUT /admin/members/{id}/weight-goal { target_weight_kg, target_date?, current_weight_kg? } — the member sees it in their app. */
    public function setGoal(Request $request, int $memberId): JsonResponse
    {
        $member = $this->visibleMemberOr404($request, $memberId);
        $range = 'between:'.WeightTrackingService::MIN_KG.','.WeightTrackingService::MAX_KG;
        $data = $request->validate([
            'target_weight_kg' => ['required', 'numeric', $range],
            'target_date' => ['nullable', 'date_format:Y-m-d', 'after:today'],
            'current_weight_kg' => ['nullable', 'numeric', $range],
        ]);

        $this->weights->setGoal($member, (float) $data['target_weight_kg'], $data['target_date'] ?? null,
            isset($data['current_weight_kg']) ? (float) $data['current_weight_kg'] : null, 'staff', $this->staff($request));

        return $this->success($this->weights->summary($member, 120), 'Target set. The member sees it in their app.');
    }

    /** GET /admin/progress?view=due|near|achieved&page= — gym-wide weight tracking (members the user can see). */
    public function tracking(Request $request): JsonResponse
    {
        $data = $request->validate(['view' => ['nullable', Rule::in(['due', 'near', 'achieved'])]]);
        $view = $data['view'] ?? 'due';
        $user = $this->staff($request);
        $every = current_gym()->weighInEveryDays();
        $visibleIds = fn () => Member::visibleTo($user)->select('id');
        $rows = [];
        $pagination = null;

        if ($view === 'due') {
            $page = $this->weights->dueQuery($every)->visibleTo($user)
                ->orderByRaw('last_weighed_on is not null')->orderBy('last_weighed_on')->orderBy('name')
                ->paginate($this->perPage($request));
            $rows = collect($page->items())->map(fn (Member $m) => [
                'member' => $this->memberBrief($m),
                'last_weighed_on' => $m->last_weighed_on ? Carbon::parse($m->last_weighed_on)->toDateString() : null,
            ])->values();
            $pagination = $this->pagination($page);
        } elseif ($view === 'achieved') {
            $page = WeightGoal::with('member')->where('status', WeightGoal::ACHIEVED)->whereIn('member_id', $visibleIds())
                ->latest('achieved_at')->paginate($this->perPage($request));
            $rows = collect($page->items())->map(fn (WeightGoal $g) => [
                'member' => $this->memberBrief($g->member),
                'start_weight_kg' => (float) $g->start_weight_kg,
                'target_weight_kg' => (float) $g->target_weight_kg,
                'achieved_weight_kg' => $g->achieved_weight_kg !== null ? (float) $g->achieved_weight_kg : null,
                'achieved_at' => $this->iso($g->achieved_at),
            ])->values();
            $pagination = $this->pagination($page);
        } else {
            $rows = WeightGoal::with('member')->where('status', WeightGoal::ACTIVE)->whereIn('member_id', $visibleIds())->get()
                ->map(fn (WeightGoal $g) => ['member' => $g->member, 'progress' => $g->member ? $this->weights->summary($g->member, 1)['goal'] : null])
                ->filter(fn ($row) => $row['progress'])
                ->sortByDesc(fn ($row) => $row['progress']['progress_percent'])
                ->take(50)
                ->map(fn ($row) => ['member' => $this->memberBrief($row['member']), 'goal' => $row['progress']])
                ->values();
        }

        return $this->success([
            'view' => $view,
            'weigh_in_every_days' => $every,
            'stats' => [
                'tracking' => WeightLog::whereIn('member_id', $visibleIds())->where('measured_on', '>=', Carbon::today()->subDays($every))->distinct()->count('member_id'),
                'due' => $this->weights->dueQuery($every)->visibleTo($user)->count(),
                'active_goals' => WeightGoal::where('status', WeightGoal::ACTIVE)->whereIn('member_id', $visibleIds())->count(),
                'achieved_last_30_days' => WeightGoal::where('status', WeightGoal::ACHIEVED)->whereIn('member_id', $visibleIds())->where('achieved_at', '>=', now()->subDays(30))->count(),
            ],
            'rows' => $rows,
            'pagination' => $pagination,
            'can_change_interval' => $user->can('gym.settings'),
        ], 'Weight tracking.');
    }

    // ---- Body progress and AI (ai.view) ---------------------------------------------

    /** GET /admin/members/{id}/ai — AI coach status, measurements, photos, transformation and AI reports. */
    public function bodyProgress(Request $request, int $memberId, AiAccessService $access, AiReportService $reports, TransformationService $transformation): JsonResponse
    {
        $member = $this->visibleMemberOr404($request, $memberId);
        $summary = $transformation->summary($member);

        return $this->success([
            'ai' => $access->status($member) + [
                'ai_access' => (bool) $member->ai_access,
                'can_toggle_access' => $this->staff($request)->can('ai.manage'),
            ],
            'fields' => MemberMeasurement::FIELDS,
            'poses' => ProgressPhoto::POSES,
            'measurements' => $this->measurements($member),
            'photos' => $this->photos($member),
            'reports' => $member->aiReports()->with(['beforePhoto', 'afterPhoto'])->limit(20)->get()
                ->map(fn (AiReport $r) => $this->reportData($r, $member, $reports, $transformation, $summary))->values(),
        ], 'Body progress.');
    }

    /** POST /admin/members/{id}/measurements { measured_on?, chest_cm?, waist_cm?, hips_cm?, arm_cm?, thigh_cm?, body_fat_percent? } */
    public function storeMeasurement(Request $request, int $memberId): JsonResponse
    {
        $member = $this->visibleMemberOr404($request, $memberId);
        $this->body->recordMeasurement($member, $request->validate(BodyProgressService::measurementRules()), 'staff', $this->staff($request));

        return $this->success(['measurements' => $this->measurements($member)], 'Measurements saved.', 201);
    }

    /** DELETE /admin/members/{id}/measurements/{measurementId} */
    public function deleteMeasurement(Request $request, int $memberId, int $measurementId): JsonResponse
    {
        $member = $this->visibleMemberOr404($request, $memberId);

        if (! $member->measurements()->whereKey($measurementId)->exists()) {
            throw new ApiException('Measurement not found.', 'NOT_FOUND', 404);
        }

        $this->body->deleteMeasurement($member, $measurementId);

        return $this->success(['measurements' => $this->measurements($member)], 'Measurement deleted.');
    }

    /** POST /admin/members/{id}/progress-photos (multipart) { photo, pose: front|side|back, taken_on? } */
    public function storePhoto(Request $request, int $memberId): JsonResponse
    {
        $member = $this->visibleMemberOr404($request, $memberId);
        $data = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
            'pose' => ['required', Rule::in(array_keys(ProgressPhoto::POSES))],
            'taken_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);

        $this->body->addPhoto($member, $request->file('photo'), $data['pose'], $data['taken_on'] ?? null, 'staff', $this->staff($request));

        return $this->success(['photos' => $this->photos($member)], 'Photo saved.', 201);
    }

    /** DELETE /admin/members/{id}/progress-photos/{photoId} */
    public function deletePhoto(Request $request, int $memberId, int $photoId): JsonResponse
    {
        $member = $this->visibleMemberOr404($request, $memberId);
        $this->findPhoto($member, $photoId);
        $this->body->deletePhoto($member, $photoId);

        return $this->success(['photos' => $this->photos($member)], 'Photo deleted.');
    }

    /** GET /admin/members/{id}/progress-photos/{photoId}/image — the private image (send the same bearer token). */
    public function photoImage(Request $request, int $memberId, int $photoId): StreamedResponse
    {
        return $this->body->photoResponse($this->findPhoto($this->visibleMemberOr404($request, $memberId), $photoId));
    }

    /**
     * POST /admin/members/{id}/ai/reports { type: progress|photo_comparison, before_photo_id?, after_photo_id? }
     * The report is made in the background: poll GET /admin/members/{id}/ai/reports/{reportId}.
     */
    public function requestReport(Request $request, int $memberId, AiReportService $reports, TransformationService $transformation): JsonResponse
    {
        $member = $this->visibleMemberOr404($request, $memberId);
        $data = $request->validate([
            'type' => ['required', Rule::in([AiReport::PROGRESS, AiReport::PHOTO_COMPARISON])],
            'before_photo_id' => ['nullable', 'required_if:type,'.AiReport::PHOTO_COMPARISON, 'integer'],
            'after_photo_id' => ['nullable', 'required_if:type,'.AiReport::PHOTO_COMPARISON, 'integer', 'different:before_photo_id'],
        ]);
        $comparison = $data['type'] === AiReport::PHOTO_COMPARISON;

        $report = $reports->request($member, $data['type'], 'staff', $this->staff($request),
            $comparison ? (int) $data['before_photo_id'] : null, $comparison ? (int) $data['after_photo_id'] : null);
        $report = $member->aiReports()->with(['beforePhoto', 'afterPhoto'])->findOrFail($report->id);

        return $this->success(['report' => $this->reportData($report, $member, $reports, $transformation)],
            $report->status === AiReport::FAILED ? 'The AI report failed. No credits were used.' : 'AI report requested.', 201);
    }

    /** GET /admin/members/{id}/ai/reports/{reportId} */
    public function report(Request $request, int $memberId, int $reportId, AiReportService $reports, TransformationService $transformation): JsonResponse
    {
        $member = $this->visibleMemberOr404($request, $memberId);
        $report = $member->aiReports()->with(['beforePhoto', 'afterPhoto'])->find($reportId)
            ?? throw new ApiException('Report not found.', 'NOT_FOUND', 404);

        return $this->success(['report' => $this->reportData($report, $member, $reports, $transformation)], 'AI report.');
    }

    /** POST /admin/members/{id}/ai/access { enabled? } — hand-pick the member for AI coach (needs ai.manage). */
    public function toggleAiAccess(Request $request, int $memberId): JsonResponse
    {
        if (! $this->staff($request)->can('ai.manage')) {
            throw $this->forbidden('Only gym admins choose who gets AI coach.');
        }

        $member = $this->visibleMemberOr404($request, $memberId);
        $data = $request->validate(['enabled' => ['nullable', 'boolean']]);
        $member->forceFill(['ai_access' => isset($data['enabled']) ? (bool) $data['enabled'] : ! $member->ai_access])->save();

        return $this->success(['ai_access' => (bool) $member->ai_access], $member->ai_access ? 'AI coach switched on for this member.' : 'AI coach switched off for this member.');
    }

    // ---- Helpers ----------------------------------------------------------------

    protected function findPhoto(Member $member, int $photoId): ProgressPhoto
    {
        return $member->progressPhotos()->find($photoId) ?? throw new ApiException('Photo not found.', 'NOT_FOUND', 404);
    }

    /** @return list<array<string, mixed>> */
    protected function measurements(Member $member): array
    {
        return $member->measurements()->get()->map(fn (MemberMeasurement $m) => $this->body->measurementArray($m))->all();
    }

    /** @return list<array<string, mixed>> */
    protected function photos(Member $member): array
    {
        return $member->progressPhotos()->get()
            ->map(fn (ProgressPhoto $p) => $this->body->photoArray($p) + ['image_url' => route('api.v1.admin.members.progress-photos.image', [$member->id, $p->id])])
            ->all();
    }

    /**
     * Same report shape as the member app.
     *
     * @param  array<string, mixed>|null  $summary
     * @return array<string, mixed>
     */
    protected function reportData(AiReport $report, Member $member, AiReportService $reports, TransformationService $transformation, ?array $summary = null): array
    {
        $data = $transformation->apiReport($report, $reports->toArray($report), $member, $summary ?? $transformation->summary($member));
        $photoUrl = fn (?int $id) => $id ? route('api.v1.admin.members.progress-photos.image', [$member->id, $id]) : null;

        // The member app's photo links need a member token; the staff app gets its own.
        return array_merge($data, [
            'before_photo_url' => $photoUrl($report->before_photo_id),
            'after_photo_url' => $photoUrl($report->after_photo_id),
        ]);
    }
}
