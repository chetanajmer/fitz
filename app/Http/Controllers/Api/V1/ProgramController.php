<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\FitnessPlanType;
use App\Models\Member;
use App\Models\ProgramDayLog;
use App\Services\ProgramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** The member's assigned workout / diet programs and their day-by-day schedule. */
class ProgramController extends ApiController
{
    public function __construct(protected ProgramService $programs) {}

    /**
     * GET /api/v1/me/programs — current workout & diet programs, today's full plan and the next 7 days.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Member $member */
        $member = $request->user('member');
        $overview = $this->programs->overview($member);

        return $this->success([
            'workout_program' => $overview['workout'],
            'diet_program' => $overview['diet'],
            'today' => $overview['today'],
            'week' => $overview['week'],
        ], 'Programs.');
    }

    /**
     * GET /api/v1/me/schedule?date=YYYY-MM-DD — that day's workout (exercises) and diet (meals).
     */
    public function schedule(Request $request): JsonResponse
    {
        $data = $request->validate(['date' => ['nullable', 'date', 'after:2000-01-01', 'before:2100-01-01']]);
        $date = Carbon::parse($data['date'] ?? Carbon::today())->startOfDay();

        return $this->success($this->programs->scheduleOn($request->user('member'), $date), 'Schedule.');
    }

    /**
     * POST /api/v1/me/schedule/log  { type: workout|diet, status: done|skipped, date?, note? }
     * Marks that day's workout / diet as followed or skipped. Returns the refreshed day.
     */
    public function logDay(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(FitnessPlanType::values())],
            'status' => ['required', Rule::in(ProgramDayLog::STATUSES)],
            'date' => ['nullable', 'date', 'before_or_equal:today', 'after:2000-01-01'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $member = $request->user('member');
        $date = Carbon::parse($data['date'] ?? Carbon::today())->startOfDay();
        $this->programs->logDay($member, FitnessPlanType::from($data['type']), $date, $data['status'], $data['note'] ?? null);

        return $this->success(
            $this->programs->scheduleOn($member, $date),
            $data['status'] === 'done' ? 'Great job! Day marked as done.' : 'Day marked as skipped.',
            201,
        );
    }

    /** DELETE /api/v1/me/schedule/log  { type, date? } — undo a day's log. */
    public function clearDay(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(FitnessPlanType::values())],
            'date' => ['nullable', 'date', 'before_or_equal:today', 'after:2000-01-01'],
        ]);

        $member = $request->user('member');
        $date = Carbon::parse($data['date'] ?? Carbon::today())->startOfDay();
        $this->programs->clearDay($member, FitnessPlanType::from($data['type']), $date);

        return $this->success($this->programs->scheduleOn($member, $date), 'Log removed.');
    }
}
