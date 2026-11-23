<?php

namespace App\Services;

use App\Enums\FitnessPlanType;
use App\Models\FitnessPlan;
use App\Models\FitnessProgram;
use App\Models\FitnessProgramDay;
use App\Models\Member;
use App\Models\MemberProgram;
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

        return DB::transaction(function () use ($member, $program, $start, $by, $notes) {
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
                'workout' => $day['workout'] ? ['title' => $day['workout']['title'], 'is_rest' => $day['workout']['is_rest']] : null,
                'diet' => $day['diet'] ? ['title' => $day['diet']['title']] : null,
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
}
