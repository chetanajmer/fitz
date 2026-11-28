<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\DietPreference;
use App\Enums\FitnessPlanType;
use App\Enums\ProgramGoal;
use App\Exceptions\ApiException;
use App\Models\FitnessPlan;
use App\Models\FitnessProgram;
use App\Models\Member;
use App\Models\MemberProgram;
use App\Services\ProgramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Diet and workout programs of a member for the staff app, like the "Diet & Workout" tab of the web member page.
 * Viewing needs members.view; assigning and stopping follow the web rule: content.manage, or content.custom for a
 * trainer's own members. Building programs (the content library) stays on the web.
 */
class MemberProgramController extends AdminController
{
    public function __construct(protected ProgramService $programs) {}

    /** GET /admin/members/{id}/programs — current workout and diet program, today, the next 7 and the last 7 days, history. */
    public function index(Request $request, int $memberId): JsonResponse
    {
        $member = $this->visibleMemberOr404($request, $memberId);

        return $this->success($this->overview($request, $member), 'Programs.');
    }

    /** GET /admin/programs?type=workout|diet&goal=&diet_preference= — active programs that can be assigned (both types when type is left out). */
    public function options(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['nullable', Rule::enum(FitnessPlanType::class)],
            'goal' => ['nullable', Rule::enum(ProgramGoal::class)],
            'diet_preference' => ['nullable', Rule::enum(DietPreference::class)],
        ]);
        $type = isset($data['type']) ? FitnessPlanType::from($data['type']) : null;

        $programs = FitnessProgram::query()->active()
            ->when($type, fn ($q) => $q->ofType($type))
            ->withCount('days')
            ->withCount(['assignments as members_count' => fn ($q) => $q->current()])
            ->when($data['goal'] ?? null, fn ($q, $goal) => $q->where('goal', $goal))
            ->when($type !== FitnessPlanType::Workout ? ($data['diet_preference'] ?? null) : null, fn ($q, $pref) => $q->where('diet_preference', $pref))
            ->orderBy('type')->orderBy('title')
            ->get();

        return $this->success([
            'type' => $type?->value,
            'goals' => collect($type ? ProgramGoal::forType($type) : ProgramGoal::cases())->map(fn (ProgramGoal $g) => ['value' => $g->value, 'label' => $g->label()])->values(),
            'diet_preferences' => $type !== FitnessPlanType::Workout
                ? collect(DietPreference::cases())->map(fn (DietPreference $p) => ['value' => $p->value, 'label' => $p->label()])->values()
                : [],
            'programs' => $programs->map(fn (FitnessProgram $p) => [
                'id' => $p->id,
                'type' => $p->type->value,
                'title' => $p->title,
                'summary' => $p->summary,
                'goal' => $p->goal?->value,
                'goal_label' => $p->goal?->label(),
                'level' => $p->level,
                'diet_preference' => $p->diet_preference?->value,
                'duration_weeks' => (int) $p->duration_weeks,
                'days_count' => (int) $p->days_count,
                'members_count' => (int) $p->members_count,
            ])->values(),
        ], 'Programs.');
    }

    /** POST /admin/members/{id}/programs { program_id, start_date?, notes? } — replaces the current program of that type. */
    public function assign(Request $request, int $memberId): JsonResponse
    {
        $member = $this->assignableMember($request, $memberId);
        $data = $request->validate([
            'program_id' => ['required', 'integer'],
            'start_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.Carbon::today()->subDays(7)->toDateString()],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], ['start_date' => 'start date']);

        $program = FitnessProgram::active()->find((int) $data['program_id'])
            ?? throw new ApiException('Program not found.', 'NOT_FOUND', 404);

        $this->programs->assign($member, $program, $data['start_date'] ?? null, $this->staff($request), $data['notes'] ?? null);

        return $this->success($this->overview($request, $member), "{$program->title} assigned to {$member->name}.", 201);
    }

    /** POST /admin/members/{id}/programs/{assignmentId}/stop */
    public function stop(Request $request, int $memberId, int $assignmentId): JsonResponse
    {
        $member = $this->assignableMember($request, $memberId);
        $assignment = MemberProgram::where('member_id', $member->id)->find($assignmentId)
            ?? throw new ApiException('Program not found.', 'NOT_FOUND', 404);

        if ($assignment->status === 'stopped') {
            throw new ApiException('This program was already stopped.', 'ALREADY_STOPPED', 409);
        }

        $this->programs->stop($assignment->setRelation('member', $member));

        return $this->success($this->overview($request, $member), 'Program stopped.');
    }

    // ---- Helpers ----------------------------------------------------------------

    protected function assignableMember(Request $request, int $memberId): Member
    {
        $member = $this->visibleMemberOr404($request, $memberId);

        if (! $this->staff($request)->can('create', [FitnessPlan::class, $member])) {
            throw $this->forbidden('You cannot assign programs to this member.');
        }

        return $member;
    }

    /** @return array<string, mixed> */
    protected function overview(Request $request, Member $member): array
    {
        $today = Carbon::today();
        $current = MemberProgram::with('program.days.plan')->where('member_id', $member->id)->current()->orderBy('start_date')->get();

        return [
            'workout_program' => ($a = $current->first(fn ($a) => $a->type === FitnessPlanType::Workout)) ? $this->programs->assignmentSummary($a) : null,
            'diet_program' => ($a = $current->first(fn ($a) => $a->type === FitnessPlanType::Diet)) ? $this->programs->assignmentSummary($a) : null,
            'today' => $this->programs->scheduleOn($member, $today, false),
            'next_7_days' => $this->programs->week($member, $today),
            'last_7_days' => $this->programs->week($member, $today->copy()->subDays(6)),
            'history' => MemberProgram::with('program:id,title', 'assigner:id,name')->where('member_id', $member->id)
                ->whereNotIn('id', $current->pluck('id'))->latest('start_date')->limit(10)->get()
                ->map(fn (MemberProgram $a) => [
                    'id' => $a->id,
                    'type' => $a->type->value,
                    'state' => $a->state(),
                    'program' => $a->program ? ['id' => $a->program->id, 'title' => $a->program->title] : null,
                    'start_date' => $a->start_date->toDateString(),
                    'end_date' => $a->end_date->toDateString(),
                    'stopped_at' => $this->iso($a->stopped_at),
                    'assigned_by' => $this->person($a->assigner),
                ])->values(),
            'can_assign' => $this->staff($request)->can('create', [FitnessPlan::class, $member]),
            'has_programs' => FitnessProgram::exists(),
        ];
    }
}
