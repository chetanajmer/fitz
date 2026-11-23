<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Member;
use App\Services\WeightTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Member weight tracking from the app: weigh-ins, current-vs-target goal, popups and reminders.
 * Every response carries the full refreshed summary (same shape as GET /me/weight).
 */
class WeightController extends ApiController
{
    public function __construct(protected WeightTrackingService $weights) {}

    /** GET /api/v1/me/weight */
    public function show(Request $request): JsonResponse
    {
        return $this->success($this->weights->summary($this->member($request)), 'Weight progress.');
    }

    /** POST /api/v1/me/weight  { weight_kg, measured_on?, note? } */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'weight_kg' => ['required', 'numeric', 'between:'.WeightTrackingService::MIN_KG.','.WeightTrackingService::MAX_KG],
            'measured_on' => ['nullable', 'date', 'before_or_equal:today', 'after:2000-01-01'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $member = $this->member($request);
        $result = $this->weights->record($member, (float) $data['weight_kg'], $data['measured_on'] ?? null, 'app', null, $data['note'] ?? null);

        return $this->success(
            ['goal_achieved' => $result['goal_achieved']] + $this->weights->summary($member),
            $result['goal_achieved'] ? 'Congratulations! You reached your target weight.' : 'Weight saved.',
            201,
        );
    }

    /** DELETE /api/v1/me/weight/{logId} — remove a wrong reading. */
    public function destroy(Request $request, int $logId): JsonResponse
    {
        $member = $this->member($request);
        $this->weights->deleteLog($member, $logId);

        return $this->success($this->weights->summary($member), 'Weight entry deleted.');
    }

    /** POST /api/v1/me/weight/goal  { target_weight_kg, current_weight_kg?, target_date? } */
    public function setGoal(Request $request): JsonResponse
    {
        $range = 'between:'.WeightTrackingService::MIN_KG.','.WeightTrackingService::MAX_KG;
        $data = $request->validate([
            'target_weight_kg' => ['required', 'numeric', $range],
            'current_weight_kg' => ['nullable', 'numeric', $range],
            'target_date' => ['nullable', 'date', 'after:today'],
        ]);

        $member = $this->member($request);
        $this->weights->setGoal($member, (float) $data['target_weight_kg'], $data['target_date'] ?? null,
            isset($data['current_weight_kg']) ? (float) $data['current_weight_kg'] : null);

        return $this->success($this->weights->summary($member), 'Target set. Let\'s go!', 201);
    }

    /** POST /api/v1/me/weight/popup/ack  { type: goal_achieved | almost_there } — the member closed the popup. */
    public function acknowledge(Request $request): JsonResponse
    {
        $data = $request->validate(['type' => ['required', 'in:goal_achieved,almost_there']]);
        $member = $this->member($request);
        $this->weights->acknowledge($member, $data['type']);

        return $this->success($this->weights->summary($member), 'OK.');
    }

    protected function member(Request $request): Member
    {
        return $request->user('member');
    }
}
