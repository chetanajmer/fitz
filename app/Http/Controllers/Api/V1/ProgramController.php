<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\FitnessPlanType;
use App\Models\Member;
use App\Services\ProgramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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
        $today = Carbon::today();
        $current = [];

        foreach ([FitnessPlanType::Workout, FitnessPlanType::Diet] as $type) {
            $assignment = $this->programs->assignmentOn($member, $type, $today)
                ?? $member->programs()->where('type', $type->value)->current()->with('program.days')->oldest('start_date')->first();
            $current[$type->value] = $assignment ? $this->programs->assignmentSummary($assignment) : null;
        }

        return $this->success([
            'workout_program' => $current['workout'],
            'diet_program' => $current['diet'],
            'today' => $this->programs->scheduleOn($member, $today),
            'week' => $this->programs->week($member, $today),
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
}
