<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Models\Member;
use App\Models\MemberWaterLog;
use App\Services\ReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Personal reminders (water, diet meals, workout) and water tracking in the member app. */
class ReminderController extends ApiController
{
    public function __construct(protected ReminderService $reminders) {}

    /** GET /api/v1/me/reminders — the reminder settings plus what the Home screen needs. */
    public function show(Request $request): JsonResponse
    {
        return $this->success($this->reminders->payload($this->member($request)), 'Reminders.');
    }

    /** PUT /api/v1/me/reminders — partial, e.g. { "hydration": { "enabled": true, "interval_minutes": 45 } }. */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(ReminderService::rules());
        $member = $this->member($request);
        $this->reminders->update($member, $data);

        return $this->success($this->reminders->payload($member), 'Reminders saved.');
    }

    /** GET /api/v1/me/hydration — today's water plus the last 7 days. */
    public function hydration(Request $request): JsonResponse
    {
        $member = $this->member($request);

        return $this->success($this->reminders->hydrationToday($member) + ['week' => $this->reminders->hydrationWeek($member)], 'Water today.');
    }

    /** POST /api/v1/me/hydration { ml?: 50–2000 (default one glass), source?: app|push } */
    public function logWater(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ml' => ['nullable', 'integer', 'between:50,2000'],
            'source' => ['nullable', Rule::in(MemberWaterLog::SOURCES)],
        ]);

        $today = $this->reminders->logWater($this->member($request), $data['ml'] ?? null, $data['source'] ?? 'app');

        return $this->success($today, $today['goal_reached_now'] ? 'Goal reached. Well done!' : 'Water logged.', 201);
    }

    /** DELETE /api/v1/me/hydration/last — undo today's latest entry. */
    public function undoWater(Request $request): JsonResponse
    {
        $today = $this->reminders->undoWater($this->member($request));

        if ($today === null) {
            throw new ApiException('Nothing to undo today.', 'NOTHING_TO_UNDO', 409);
        }

        return $this->success($today, 'Removed.');
    }

    protected function member(Request $request): Member
    {
        return $request->user('member');
    }
}
