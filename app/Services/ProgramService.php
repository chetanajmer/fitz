<?php

namespace App\Services;

use App\Enums\FitnessPlanType;
use App\Models\FitnessPlan;
use App\Models\FitnessProgram;
use App\Models\FitnessProgramDay;
use App\Models\Member;
use App\Models\MemberProgram;
use App\Models\ProgramDayLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Programs: day-by-day workout / diet schedules built from the gym's plans, assigned to members.
 * Runs inside the gym tenant context.
 */
class ProgramService
{
    /** Members can log today and this many days back; staff can log any day of the program. */
    public const MEMBER_LOG_DAYS_BACK = 2;

    /** @var array<int, Collection<string, ProgramDayLog>> day logs per assignment, keyed by date */
    protected array $logCache = [];

    public function __construct(protected FitnessLibraryImporter $library) {}

    /** @return array<string, list<array<string, mixed>>> */
    public function defaults(): array
    {
        return require database_path('data/fitness-programs.php');
    }

    /**
     * Default programs for a type with whether this gym already has them.
     *
     * @return list<array<string, mixed>>
     */
    public function catalogue(FitnessPlanType $type): array
    {
        $imported = FitnessProgram::withTrashed()->ofType($type)->whereNotNull('library_key')->pluck('library_key')->all();

        return array_map(fn (array $p) => $p + ['imported' => in_array($p['key'], $imported, true)], $this->defaults()[$type->value] ?? []);
    }

    /**
     * Import default programs (and the library plans they use) into the gym. Idempotent by key.
     *
     * @param  list<string>|null  $keys  null = all
     * @return array{programs_created: int, programs_skipped: int, plans_created: int}
     */
    public function importDefaults(FitnessPlanType $type, ?array $keys = null): array
    {
        $programs = collect($this->defaults()[$type->value] ?? [])
            ->when($keys !== null, fn (Collection $c) => $c->whereIn('key', $keys))
            ->values();
        $result = ['programs_created' => 0, 'programs_skipped' => 0, 'plans_created' => 0];

        if ($programs->isEmpty()) {
            return $result;
        }

        return DB::transaction(function () use ($type, $programs, $result) {
            // Bring in the library categories that contain the plans these programs use.
            $planKeys = $programs->flatMap(fn ($p) => collect($p['days'])->pluck(0))->filter()->unique()->values()->all();
            $categoryKeys = collect($this->library->categories($type))
                ->filter(fn ($c) => array_intersect(array_column($c['plans'], 'key'), $planKeys))
                ->pluck('key')->all();
            if ($categoryKeys) {
                $result['plans_created'] = $this->library->import($type, $categoryKeys)['plans_created'];
            }

            $plans = FitnessPlan::ofType($type)->defaults()->whereIn('library_key', $planKeys)->pluck('id', 'library_key');
            $existing = FitnessProgram::withTrashed()->ofType($type)->whereNotNull('library_key')->pluck('library_key')->all();
            $order = (int) FitnessProgram::ofType($type)->max('sort_order');

            foreach ($programs as $data) {
                if (in_array($data['key'], $existing, true)) {
                    $result['programs_skipped']++;

                    continue;
                }

                $program = new FitnessProgram([
                    'type' => $type,
                    'title' => $data['title'],
                    'summary' => $data['summary'] ?? null,
                    'goal' => $data['goal'],
                    'level' => $data['level'] ?? null,
                    'diet_preference' => $data['diet_preference'] ?? null,
                    'duration_weeks' => $data['duration_weeks'] ?? 4,
                    'is_active' => true,
                    'sort_order' => ++$order,
                ]);
                $program->library_key = $data['key'];
                $program->created_by = Auth::guard('web')->id();
                $program->save();

                foreach (array_values($data['days']) as $i => $day) {
                    $program->days()->create([
                        'day_number' => $i + 1,
                        'title' => $day[1] ?? null,
                        'is_rest' => $day[0] === null,
                        'fitness_plan_id' => $day[0] ? ($plans[$day[0]] ?? null) : null,
                        'notes' => $day[2] ?? null,
                    ]);
                }

                $result['programs_created']++;
            }

            return $result;
        });
    }

    /**
     * Create or update a program and replace its days.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array{title?: ?string, is_rest?: bool, fitness_plan_id?: ?int, notes?: ?string}>  $days
     */
    public function save(?FitnessProgram $program, FitnessPlanType $type, array $data, array $days): FitnessProgram
    {
        if ($days === []) {
            throw ValidationException::withMessages(['days' => 'Add at least one day.']);
        }

        $planIds = FitnessPlan::ofType($type)->defaults()->pluck('id')->all();

        return DB::transaction(function () use ($program, $type, $data, $days, $planIds) {
            $program ??= new FitnessProgram(['type' => $type]);
            $program->fill(collect($data)->only(['title', 'summary', 'goal', 'level', 'diet_preference', 'duration_weeks', 'is_active'])->all());
            $program->type = $type;
            $program->created_by ??= Auth::guard('web')->id();
            $program->save();

            $program->days()->delete();
            foreach (array_values($days) as $i => $day) {
                $planId = ! empty($day['fitness_plan_id']) ? (int) $day['fitness_plan_id'] : null;
                $isRest = (bool) ($day['is_rest'] ?? false) || ! $planId;

                $program->days()->create([
                    'day_number' => $i + 1,
                    'title' => ($day['title'] ?? null) ?: null,
                    'is_rest' => $isRest,
                    // Only this gym's default plans of the same type can be used.
                    'fitness_plan_id' => ! $isRest && in_array($planId, $planIds, true) ? $planId : null,
                    'notes' => ($day['notes'] ?? null) ?: null,
                ]);
            }

            return $program->load('days');
        });
    }

    /** Assign a program to a member from a date. Any current program of the same type is stopped. */
    public function assign(Member $member, FitnessProgram $program, ?string $startDate = null, ?User $by = null, ?string $notes = null): MemberProgram
    {
        if ((int) $program->gym_id !== (int) $member->gym_id || ! $program->is_active) {
            throw ValidationException::withMessages(['program' => 'Choose an active program from this gym.']);
        }

        $start = Carbon::parse($startDate ?? Carbon::today())->startOfDay();

        if ($start->lt(Carbon::today()->subDays(7))) {
            throw ValidationException::withMessages(['start_date' => 'The start date can be at most 7 days in the past.']);
        }

        $assignment = DB::transaction(function () use ($member, $program, $start, $by, $notes) {
            MemberProgram::where('member_id', $member->id)->where('type', $program->type->value)->current()
                ->update(['status' => 'stopped', 'stopped_at' => now()]);

            $assignment = new MemberProgram;
            $assignment->member_id = $member->id;
            $assignment->fitness_program_id = $program->id;
            $assignment->type = $program->type;
            $assignment->start_date = $start;
            $assignment->end_date = $start->copy()->addWeeks(max(1, (int) $program->duration_weeks))->subDay();
            $assignment->status = 'active';
            $assignment->notes = $notes;
            $assignment->assigned_by = $by?->id;
            $assignment->save();

            return $assignment;
        });

        app(PushNotificationService::class)->programAssigned($assignment->setRelation('member', $member)->setRelation('program', $program));

        return $assignment;
    }

    public function stop(MemberProgram $assignment): void
    {
        $assignment->forceFill(['status' => 'stopped', 'stopped_at' => now()])->save();
    }

    /** The member's program of a type that covers a date (newest wins). */
    public function assignmentOn(Member $member, FitnessPlanType $type, Carbon $date): ?MemberProgram
    {
        return MemberProgram::with('program.days.plan')
            ->where('member_id', $member->id)->where('type', $type->value)->where('status', 'active')
            ->whereDate('start_date', '<=', $date->toDateString())->whereDate('end_date', '>=', $date->toDateString())
            ->latest('id')->first();
    }

    /** The program day for a date (cycle repeats), or null outside the assignment. */
    public function dayOn(MemberProgram $assignment, Carbon $date): ?array
    {
        $offset = (int) $assignment->start_date->diffInDays($date->copy()->startOfDay(), false);

        if ($offset < 0 || $date->gt($assignment->end_date)) {
            return null;
        }

        $program = $assignment->program;
        $cycle = $program->cycleLength();
        $dayNumber = $offset % $cycle + 1;

        return [
            'date' => $date->toDateString(),
            'program_day' => $offset + 1,
            'week' => intdiv($offset, 7) + 1,
            'cycle_day' => $dayNumber,
            'day' => $program->days->firstWhere('day_number', $dayNumber),
        ];
    }

    /**
     * Record whether the member followed (done) or skipped a day's workout / diet.
     * $by = staff user logging for the member; null when the member logs it themselves.
     */
    public function logDay(Member $member, FitnessPlanType $type, Carbon $date, string $status, ?string $note = null, ?User $by = null): ProgramDayLog
    {
        if (! in_array($status, ProgramDayLog::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'Choose done or skipped.']);
        }

        [$assignment, $slot] = $this->loggableSlot($member, $type, $date, $by);
        $day = $slot['day'];

        $log = $this->findLog($member, $type, $date) ?? new ProgramDayLog;
        $log->forceFill([
            'member_id' => $member->id,
            'member_program_id' => $assignment->id,
            'type' => $type,
            'date' => $date->toDateString(),
            'status' => $status,
            'program_day' => $slot['program_day'],
            'fitness_plan_id' => $day->fitness_plan_id,
            'title' => mb_substr($day->title ?: ($day->plan?->title ?? ucfirst($type->value)), 0, 150),
            'note' => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 500) : null,
            'logged_by' => $by ? 'staff' : 'member',
            'recorded_by' => $by?->id,
        ])->save();

        $this->logCache = [];

        return $log;
    }

    /** Undo a day's log (back to "not logged"). */
    public function clearDay(Member $member, FitnessPlanType $type, Carbon $date, ?User $by = null): void
    {
        $this->loggableSlot($member, $type, $date, $by);
        $this->findLog($member, $type, $date)?->delete();
        $this->logCache = [];
    }

    /**
     * How well the member has followed an assignment so far.
     * Planned = training / diet days (not rest days) up to yesterday, plus today once logged.
     *
     * @return array{planned_days: int, done_days: int, skipped_days: int, missed_days: int, percent: float}
     */
    public function adherence(MemberProgram $assignment): array
    {
        $today = Carbon::today();
        $logs = $this->logsFor($assignment);
        $last = $assignment->end_date->lt($today) ? $assignment->end_date->copy() : $today->copy();
        $days = $assignment->program->days->keyBy('day_number');
        $cycle = $assignment->program->cycleLength();
        $planned = 0;

        if ($assignment->start_date->lte($last)) {
            $total = (int) $assignment->start_date->diffInDays($last);
            for ($offset = 0; $offset <= $total; $offset++) {
                $day = $days->get($offset % $cycle + 1);
                if (! $day || $day->is_rest || ! $day->fitness_plan_id) {
                    continue;
                }
                $date = $assignment->start_date->copy()->addDays($offset)->toDateString();
                if ($date < $today->toDateString() || $logs->has($date)) {
                    $planned++;
                }
            }
        }

        $done = $logs->where('status', 'done')->count();
        $skipped = $logs->where('status', 'skipped')->count();

        return [
            'planned_days' => $planned,
            'done_days' => $done,
            'skipped_days' => $skipped,
            'missed_days' => max(0, $planned - $done - $skipped),
            'percent' => $planned ? round(min(100, $done / $planned * 100), 1) : 0.0,
        ];
    }

    /**
     * The assignment and program day a log may be written for, or a validation error.
     *
     * @return array{0: MemberProgram, 1: array<string, mixed>}
     */
    protected function loggableSlot(Member $member, FitnessPlanType $type, Carbon $date, ?User $by): array
    {
        $date = $date->copy()->startOfDay();

        if ($date->gt(Carbon::today())) {
            throw ValidationException::withMessages(['date' => 'You can only log today or earlier days.']);
        }

        if (! $by && $date->lt(Carbon::today()->subDays(self::MEMBER_LOG_DAYS_BACK))) {
            throw ValidationException::withMessages(['date' => 'Days older than '.self::MEMBER_LOG_DAYS_BACK.' days can no longer be changed.']);
        }

        $assignment = $this->assignmentOn($member, $type, $date);
        $slot = $assignment ? $this->dayOn($assignment, $date) : null;

        if (! $slot) {
            throw ValidationException::withMessages(['type' => 'There is no '.$type->value.' program on this day.']);
        }

        if (! $slot['day'] || $slot['day']->is_rest || ! $slot['day']->fitness_plan_id) {
            throw ValidationException::withMessages(['type' => 'This is a rest day, nothing to log.']);
        }

        return [$assignment, $slot];
    }

    protected function findLog(Member $member, FitnessPlanType $type, Carbon $date): ?ProgramDayLog
    {
        return ProgramDayLog::where('member_id', $member->id)->where('type', $type->value)
            ->whereDate('date', $date->toDateString())->first();
    }

    /** @return Collection<string, ProgramDayLog> */
    protected function logsFor(MemberProgram $assignment): Collection
    {
        return $this->logCache[$assignment->id] ??= ProgramDayLog::where('member_program_id', $assignment->id)->get()
            ->keyBy(fn (ProgramDayLog $log) => $log->date->toDateString());
    }

    /**
     * The member's current programs (or the next upcoming one), today's full plans and the next 7 days.
     *
     * @return array{workout: ?array, diet: ?array, today: array, week: list<array<string, mixed>>}
     */
    public function overview(Member $member, ?Carbon $today = null): array
    {
        $today ??= Carbon::today();
        $current = [];

        foreach ([FitnessPlanType::Workout, FitnessPlanType::Diet] as $type) {
            $assignment = $this->assignmentOn($member, $type, $today)
                ?? $member->programs()->where('type', $type->value)->current()->with('program.days')->oldest('start_date')->first();
            $current[$type->value] = $assignment ? $this->assignmentSummary($assignment) : null;
        }

        return [
            'workout' => $current['workout'],
            'diet' => $current['diet'],
            'today' => $this->scheduleOn($member, $today),
            'week' => $this->week($member, $today),
        ];
    }

    /**
     * What the member should do / eat on a date, for both program types.
     *
     * @return array{date: string, workout: ?array, diet: ?array}
     */
    public function scheduleOn(Member $member, Carbon $date, bool $withSections = true): array
    {
        $out = ['date' => $date->toDateString(), 'workout' => null, 'diet' => null];

        foreach ([FitnessPlanType::Workout, FitnessPlanType::Diet] as $type) {
            $assignment = $this->assignmentOn($member, $type, $date);
            $slot = $assignment ? $this->dayOn($assignment, $date) : null;

            if ($slot) {
                $out[$type->value] = $this->formatSlot($assignment, $slot, $withSections);
            }
        }

        return $out;
    }

    /**
     * Seven days from a date (titles only), for a week strip.
     *
     * @return list<array<string, mixed>>
     */
    public function week(Member $member, Carbon $from): array
    {
        return collect(range(0, 6))->map(function (int $i) use ($member, $from) {
            $date = $from->copy()->addDays($i);
            $day = $this->scheduleOn($member, $date, false);

            return [
                'date' => $date->toDateString(),
                'weekday' => $date->format('D'),
                'is_today' => $date->isToday(),
                'workout' => $day['workout'] ? ['title' => $day['workout']['title'], 'is_rest' => $day['workout']['is_rest'], 'status' => $day['workout']['status']] : null,
                'diet' => $day['diet'] ? ['title' => $day['diet']['title'], 'is_rest' => $day['diet']['is_rest'], 'status' => $day['diet']['status']] : null,
            ];
        })->all();
    }

    /** @return array<string, mixed> */
    public function assignmentSummary(MemberProgram $assignment): array
    {
        $program = $assignment->program;
        $today = Carbon::today();
        $total = (int) $assignment->start_date->diffInDays($assignment->end_date) + 1;
        $done = $assignment->start_date->gt($today) ? 0 : min($total, (int) $assignment->start_date->diffInDays($today) + 1);

        return [
            'id' => $assignment->id,
            'type' => $assignment->type->value,
            'state' => $assignment->state(),
            'program' => [
                'id' => $program->id,
                'title' => $program->title,
                'summary' => $program->summary,
                'goal' => $program->goal->value,
                'goal_label' => $program->goal->label(),
                'level' => $program->level,
                'diet_preference' => $program->diet_preference?->value,
                'diet_preference_label' => $program->diet_preference?->label(),
                'duration_weeks' => $program->duration_weeks,
                'cycle_days' => $program->cycleLength(),
            ],
            'start_date' => $assignment->start_date->toDateString(),
            'end_date' => $assignment->end_date->toDateString(),
            'total_days' => $total,
            'current_day' => $done,
            'current_week' => $done ? intdiv($done - 1, 7) + 1 : 0,
            'progress_percent' => round($done / max(1, $total) * 100, 1),
            'adherence' => $this->adherence($assignment),
            'notes' => $assignment->notes,
        ];
    }

    /** @return array<string, mixed> */
    protected function formatSlot(MemberProgram $assignment, array $slot, bool $withSections): array
    {
        /** @var FitnessProgramDay|null $day */
        $day = $slot['day'];
        $plan = $day && ! $day->is_rest && $day->plan && $day->plan->is_published ? $day->plan : null;

        if ($plan && $withSections) {
            $plan->loadMissing('sections');
        }

        return [
            'assignment_id' => $assignment->id,
            'program_id' => $assignment->fitness_program_id,
            'program_title' => $assignment->program->title,
            'program_day' => $slot['program_day'],
            'week' => $slot['week'],
            'cycle_day' => $slot['cycle_day'],
            'title' => $day?->title ?: ($plan?->title ?? 'Rest & recovery'),
            'is_rest' => ! $plan,
            'notes' => $day?->notes,
            'status' => $this->dayStatus($assignment, $slot['date'], ! $plan),
            'log' => ($log = $this->logsFor($assignment)->get($slot['date'])) ? [
                'status' => $log->status,
                'note' => $log->note,
                'logged_by' => $log->logged_by,
                'logged_at' => $log->updated_at?->toIso8601String(),
            ] : null,
            'can_log' => (bool) $plan && $slot['date'] <= Carbon::today()->toDateString()
                && $slot['date'] >= Carbon::today()->subDays(self::MEMBER_LOG_DAYS_BACK)->toDateString(),
            'plan' => $plan ? array_filter([
                'id' => $plan->id,
                'title' => $plan->title,
                'summary' => $plan->summary,
                'level' => $plan->level,
                'daily_calories' => $plan->daily_calories,
                'cover_url' => $plan->coverUrl(),
                'sections' => $withSections ? $plan->sections->map(fn ($s) => [
                    'id' => $s->id,
                    'title' => $s->title,
                    'subtitle' => $s->subtitle,
                    'body' => $s->body,
                    'image_url' => $s->imageUrl(),
                ])->all() : null,
            ], fn ($v) => $v !== null) : null,
        ];
    }

    /** done | skipped | missed (past, not logged) | pending (today, not logged) | upcoming | rest */
    protected function dayStatus(MemberProgram $assignment, string $date, bool $isRest): string
    {
        if ($isRest) {
            return 'rest';
        }

        $today = Carbon::today()->toDateString();

        return $this->logsFor($assignment)->get($date)?->status
            ?? ($date < $today ? 'missed' : ($date === $today ? 'pending' : 'upcoming'));
    }
}
