<?php

namespace App\Services;

use App\Enums\FitnessPlanType;
use App\Enums\MemberStatus;
use App\Jobs\SendReminderPush;
use App\Models\Attendance;
use App\Models\FitnessPlan;
use App\Models\FitnessProgram;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberMealReminder;
use App\Models\MemberProgram;
use App\Models\MemberReminderSetting;
use App\Models\MemberWaterLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The member's personal reminders (water, diet meals, workout) and water tracking.
 *
 * These pushes are chosen by the member, so they are not written to the notification inbox and are
 * not held back by the platform quiet hours. They stop when the member switches off "reminders"
 * in the notification settings, and go only to active members with a valid membership and a phone.
 * Everything runs in the gym tenant context; times are the app timezone (Asia/Kolkata).
 */
class ReminderService
{
    /** Sent in turn; {first_name} is filled in. */
    public const HYDRATION_TITLES = [
        'Time for a glass of water',
        'Sip break!',
        'Stay hydrated, {first_name}',
        'Water check 💧',
        'Your body wants a glass of water',
        'Quick water break, {first_name}?',
        'Hydration time',
        'Keep the water coming',
        'A glass of water keeps you going',
    ];

    /** Meal reminders older than this are not sent any more. */
    public const MEAL_STALE_MINUTES = 30;

    /** A workout reminder that is this late (server was down) is skipped until tomorrow. */
    public const WORKOUT_STALE_MINUTES = 60;

    public function __construct(protected ProgramService $programs, protected PushNotificationService $notifications) {}

    // ---- Settings ---------------------------------------------------------------------------

    public function settingsFor(Member $member): MemberReminderSetting
    {
        $settings = MemberReminderSetting::forGym($member->gym_id)->where('member_id', $member->id)->first();

        if (! $settings) {
            $settings = new MemberReminderSetting;
            $settings->forceFill(MemberReminderSetting::DEFAULTS + ['gym_id' => $member->gym_id, 'member_id' => $member->id])->save();
        }

        return $settings->setRelation('member', $member);
    }

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'hydration' => ['sometimes', 'array'],
            'hydration.enabled' => ['sometimes', 'boolean'],
            'hydration.interval_minutes' => ['sometimes', 'integer', Rule::in(MemberReminderSetting::HYDRATION_INTERVALS)],
            'hydration.start_time' => ['sometimes', 'date_format:H:i'],
            'hydration.end_time' => ['sometimes', 'date_format:H:i'],
            'hydration.goal_ml' => ['sometimes', 'integer', 'between:1000,6000'],
            'hydration.glass_ml' => ['sometimes', 'integer', 'between:100,1000'],
            'diet' => ['sometimes', 'array'],
            'diet.enabled' => ['sometimes', 'boolean'],
            'diet.lead_minutes' => ['sometimes', 'integer', Rule::in(MemberReminderSetting::DIET_LEADS)],
            'workout' => ['sometimes', 'array'],
            'workout.enabled' => ['sometimes', 'boolean'],
            'workout.time' => ['sometimes', 'date_format:H:i'],
            'workout.skip_rest_days' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Apply a partial, validated update and reschedule what it affects.
     *
     * @param  array<string, array<string, mixed>>  $data
     */
    public function update(Member $member, array $data, ?Carbon $now = null): MemberReminderSetting
    {
        $now ??= now();
        $settings = $this->settingsFor($member);
        $map = [
            'hydration' => ['enabled' => 'hydration_enabled', 'interval_minutes' => 'hydration_interval_minutes', 'start_time' => 'hydration_start',
                'end_time' => 'hydration_end', 'goal_ml' => 'hydration_goal_ml', 'glass_ml' => 'glass_ml'],
            'diet' => ['enabled' => 'diet_enabled', 'lead_minutes' => 'diet_lead_minutes'],
            'workout' => ['enabled' => 'workout_enabled', 'time' => 'workout_time', 'skip_rest_days' => 'workout_skip_rest_days'],
        ];

        foreach ($map as $group => $fields) {
            foreach ($fields as $key => $column) {
                if (array_key_exists($key, $data[$group] ?? [])) {
                    $settings->{$column} = $data[$group][$key];
                }
            }
        }

        $start = $this->minutes(MemberReminderSetting::clock($settings->hydration_start));
        $end = $this->minutes(MemberReminderSetting::clock($settings->hydration_end));
        if ($end - $start < 60) {
            throw ValidationException::withMessages(['hydration.end_time' => 'The end time must be at least 1 hour after the start time.']);
        }

        // Reschedule only what changed, so saving one card does not move the other reminders.
        $hydrationColumns = array_values($map['hydration']);
        if ($settings->isDirty($hydrationColumns) || ($settings->hydration_enabled && ! $settings->hydration_next_at)) {
            $settings->hydration_next_at = $settings->hydration_enabled ? $this->firstHydrationAt($settings, $now) : null;
        }
        if ($settings->isDirty(array_values($map['workout'])) || ($settings->workout_enabled && ! $settings->workout_next_at)) {
            $settings->workout_next_at = $settings->workout_enabled ? $this->nextWorkoutAt($settings, $now) : null;
        }
        $settings->save();

        $this->buildMealReminders($member, $now->copy()->startOfDay(), $settings);

        return $settings;
    }

    /** GET /me/reminders. @return array<string, mixed> */
    public function payload(Member $member, ?Carbon $now = null): array
    {
        $now ??= now();
        $settings = $this->settingsFor($member);
        $schedule = $this->programs->scheduleOn($member, $now->copy()->startOfDay());
        $meals = $this->mealsFor($schedule, $now->copy()->startOfDay(), $settings);
        $sent = MemberMealReminder::where('member_id', $member->id)->whereDate('date', $now->toDateString())->whereNotNull('sent_at')->pluck('plan_section_id')->all();
        $workout = $schedule['workout'];
        $paused = ! $this->remindersOn($member);
        $hydration = $this->hydrationToday($member, $settings, $now);

        $diet = [
            'enabled' => $settings->diet_enabled,
            'lead_minutes' => $settings->diet_lead_minutes,
            'allowed_leads' => MemberReminderSetting::DIET_LEADS,
            'has_plan' => $schedule['diet'] !== null,
            'today' => array_map(fn (array $m) => [
                'section_id' => $m['section_id'],
                'title' => $m['title'],
                'time' => $m['time'],
                'remind_at' => $m['remind_at']->toIso8601String(),
                'sent' => in_array($m['section_id'], $sent, true),
            ], $meals),
        ];

        return [
            'paused_by_master_switch' => $paused,
            'home' => [
                'hydration' => $hydration,
                'highlight' => $this->highlight($settings, $hydration, $meals, $sent, $workout, $paused, $now),
            ],
            'hydration' => [
                'enabled' => $settings->hydration_enabled,
                'interval_minutes' => $settings->hydration_interval_minutes,
                'start_time' => MemberReminderSetting::clock($settings->hydration_start),
                'end_time' => MemberReminderSetting::clock($settings->hydration_end),
                'goal_ml' => $settings->hydration_goal_ml,
                'glass_ml' => $settings->glass_ml,
                'next_at' => $hydration['next_at'],
                'allowed_intervals' => MemberReminderSetting::HYDRATION_INTERVALS,
            ],
            'diet' => $diet,
            'workout' => [
                'enabled' => $settings->workout_enabled,
                'time' => MemberReminderSetting::clock($settings->workout_time),
                'skip_rest_days' => $settings->workout_skip_rest_days,
                'has_plan' => $workout !== null,
                'next_at' => $settings->workout_enabled ? $settings->workout_next_at?->toIso8601String() : null,
                'today' => $workout ? [
                    'title' => $workout['title'],
                    'is_rest' => $workout['is_rest'],
                    'status' => $workout['status'],
                    'remind_at' => $settings->workout_enabled && ! ($workout['is_rest'] && $settings->workout_skip_rest_days)
                        ? $this->at($now, MemberReminderSetting::clock($settings->workout_time))->toIso8601String() : null,
                ] : null,
            ],
            'timezone' => config('app.timezone'),
        ];
    }

    // ---- Water ------------------------------------------------------------------------------

    /** @return array<string, mixed> */
    public function hydrationToday(Member $member, ?MemberReminderSetting $settings = null, ?Carbon $now = null): array
    {
        $now ??= now();
        $settings ??= $this->settingsFor($member);
        $today = $this->mlOn($member, $now);
        $goal = $settings->hydration_goal_ml;
        $glass = max(1, $settings->glass_ml);

        return [
            'enabled' => $settings->hydration_enabled,
            'today_ml' => $today,
            'goal_ml' => $goal,
            'glass_ml' => $settings->glass_ml,
            'glasses' => intdiv($today, $glass),
            'goal_glasses' => (int) ceil($goal / $glass),
            'percent' => (int) min(100, round($today / max(1, $goal) * 100)),
            'goal_met' => $today >= $goal,
            'next_at' => $settings->hydration_enabled && $today < $goal ? $settings->hydration_next_at?->toIso8601String() : null,
        ];
    }

    /** Last 7 days, oldest first. @return list<array{date: string, ml: int, goal_ml: int, met: bool}> */
    public function hydrationWeek(Member $member, ?Carbon $now = null): array
    {
        $now ??= now();
        $goal = $this->settingsFor($member)->hydration_goal_ml;
        $from = $now->copy()->startOfDay()->subDays(6);
        $totals = MemberWaterLog::where('member_id', $member->id)->whereDate('date', '>=', $from->toDateString())
            ->selectRaw('date, SUM(ml) as total')->groupBy('date')->pluck('total', 'date')
            ->mapWithKeys(fn ($total, $date) => [Carbon::parse($date)->toDateString() => (int) $total]);

        return collect(range(0, 6))->map(function (int $i) use ($from, $totals, $goal) {
            $date = $from->copy()->addDays($i)->toDateString();
            $ml = $totals[$date] ?? 0;

            return ['date' => $date, 'ml' => $ml, 'goal_ml' => $goal, 'met' => $ml >= $goal];
        })->all();
    }

    /** Log water. The block gets "goal_reached_now" the first time today's goal is reached. @return array<string, mixed> */
    public function logWater(Member $member, ?int $ml = null, string $source = 'app', ?Carbon $now = null): array
    {
        $now ??= now();

        return DB::transaction(function () use ($member, $ml, $source, $now) {
            $settings = MemberReminderSetting::whereKey($this->settingsFor($member)->id)->lockForUpdate()->first();

            $log = new MemberWaterLog;
            $log->forceFill([
                'gym_id' => $member->gym_id,
                'member_id' => $member->id,
                'date' => $now->toDateString(),
                'ml' => $ml ?? $settings->glass_ml,
                'logged_at' => $now,
                'source' => $source,
            ])->save();

            $reachedNow = $this->mlOn($member, $now) >= $settings->hydration_goal_ml && ! $settings->hydration_goal_met_on?->isSameDay($now);

            if ($reachedNow) {
                $settings->hydration_goal_met_on = $now->toDateString();
            }
            if ($settings->hydration_enabled && $this->mlOn($member, $now) >= $settings->hydration_goal_ml) {
                $settings->hydration_next_at = $this->windowStart($settings, $now->copy()->addDay());
            }
            $settings->save();

            return $this->hydrationToday($member, $settings, $now) + ['goal_reached_now' => $reachedNow];
        });
    }

    /** Remove today's latest entry. Null when there is none. @return array<string, mixed>|null */
    public function undoWater(Member $member, ?Carbon $now = null): ?array
    {
        $now ??= now();
        $last = MemberWaterLog::where('member_id', $member->id)->whereDate('date', $now->toDateString())->latest('logged_at')->latest('id')->first();

        if (! $last) {
            return null;
        }

        $last->delete();
        $settings = $this->settingsFor($member);

        // Below the goal again: reminders resume.
        if ($settings->hydration_enabled && $this->mlOn($member, $now) < $settings->hydration_goal_ml
            && (! $settings->hydration_next_at || ! $settings->hydration_next_at->isSameDay($now))) {
            $settings->hydration_next_at = $this->firstHydrationAt($settings, $now);
            $settings->save();
        }

        return $this->hydrationToday($member, $settings, $now);
    }

    // ---- Meal reminders ---------------------------------------------------------------------

    /**
     * Rebuild the member's meal reminders for a day from their diet program. Reminders already sent
     * are kept; unsent ones are updated or removed. Returns how many reminders the day has.
     */
    public function buildMealReminders(Member $member, ?Carbon $date = null, ?MemberReminderSetting $settings = null): int
    {
        $date = ($date ?? Carbon::today())->copy()->startOfDay();
        $settings ??= $this->settingsFor($member);
        $existing = MemberMealReminder::where('member_id', $member->id)->whereDate('date', $date->toDateString())->get()->keyBy('plan_section_id');
        $meals = $settings->diet_enabled ? $this->mealsFor($this->programs->scheduleOn($member, $date), $date, $settings) : [];
        $keep = [];

        foreach ($meals as $meal) {
            $keep[] = $meal['section_id'];
            $reminder = $existing->get($meal['section_id']) ?? new MemberMealReminder;

            if ($reminder->sent_at) {
                continue;
            }

            $reminder->forceFill([
                'gym_id' => $member->gym_id,
                'member_id' => $member->id,
                'date' => $date->toDateString(),
                'plan_section_id' => $meal['section_id'],
                'title' => Str::limit($meal['title'], 150, ''),
                'meal_time' => $meal['time'],
                'send_at' => $meal['remind_at'],
            ])->save();
        }

        MemberMealReminder::whereKey($existing->except($keep)->whereNull('sent_at')->modelKeys())->delete();

        return count($meals);
    }

    /** After a diet plan changed: today's reminders of every member following a program that uses it. */
    public function rebuildForPlan(FitnessPlan $plan): int
    {
        if ($plan->type !== FitnessPlanType::Diet) {
            return 0;
        }

        return $this->rebuildForAssignments(MemberProgram::whereHas('program.days', fn ($q) => $q->where('fitness_plan_id', $plan->id)));
    }

    /** After a diet program's days changed. */
    public function rebuildForProgram(FitnessProgram $program): int
    {
        return $program->type === FitnessPlanType::Diet
            ? $this->rebuildForAssignments(MemberProgram::where('fitness_program_id', $program->id))
            : 0;
    }

    protected function rebuildForAssignments($assignments): int
    {
        $today = Carbon::today()->toDateString();
        $memberIds = $assignments->where('status', 'active')->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today)
            ->whereHas('member.reminderSettings', fn ($q) => $q->where('diet_enabled', true))
            ->pluck('member_id')->unique();

        $count = 0;
        foreach (Member::whereKey($memberIds)->get() as $member) {
            $this->buildMealReminders($member);
            $count++;
        }

        return $count;
    }

    /**
     * Today's timed meals from a schedule (sections without a time, such as guidelines, are skipped).
     *
     * @param  array<string, mixed>  $schedule  ProgramService::scheduleOn()
     * @return list<array{section_id: int, title: string, time: string, remind_at: Carbon}>
     */
    protected function mealsFor(array $schedule, Carbon $date, MemberReminderSetting $settings): array
    {
        $diet = $schedule['diet'];

        if (! $diet || $diet['is_rest'] || empty($diet['plan']['id'])) {
            return [];
        }

        $sections = FitnessPlan::query()->find($diet['plan']['id'])?->sections()->whereNotNull('meal_time')->get() ?? collect();

        return $sections->map(fn ($section) => [
            'section_id' => $section->id,
            'title' => $section->title,
            'time' => $section->mealClock(),
            'remind_at' => $this->at($date, $section->mealClock())->subMinutes($settings->diet_lead_minutes),
        ])->sortBy('remind_at')->values()->all();
    }

    // ---- Senders (run per gym) --------------------------------------------------------------

    /** Water reminders that are due. Returns how many were queued. */
    public function sendHydration(Gym $gym, ?Carbon $now = null): int
    {
        $now ??= now();
        $sent = 0;

        MemberReminderSetting::with('member')->where('hydration_enabled', true)->where('hydration_next_at', '<=', $now)
            ->chunkById(500, function ($rows) use ($now, &$sent) {
                foreach ($rows as $settings) {
                    $member = $settings->member;
                    $settings->hydration_next_at = $this->nextHydrationAt($settings, $now);
                    $within = $this->withinWindow($settings, $now);
                    $today = $member ? $this->mlOn($member, $now) : 0;

                    if ($within && $today >= $settings->hydration_goal_ml) {
                        $settings->hydration_next_at = $this->windowStart($settings, $now->copy()->addDay());
                    }
                    $settings->save();

                    if (! $within || ! $member || $today >= $settings->hydration_goal_ml || ! $this->canReceive($member)) {
                        continue;
                    }

                    $glass = max(1, $settings->glass_ml);
                    $glasses = intdiv($today, $glass);
                    $goal = (int) ceil($settings->hydration_goal_ml / $glass);
                    $titles = self::HYDRATION_TITLES;
                    $title = strtr($titles[($member->id + $now->dayOfYear * 7 + $now->hour) % count($titles)], ['{first_name}' => $this->firstName($member)]);

                    $this->push($member, $title, $glasses === 0 ? "Start with a glass now. Goal: {$goal} glasses today." : "{$glasses} of {$goal} glasses today. One more now?",
                        ['type' => 'hydration', 'screen' => 'home'], '1800s', 'water');
                    $sent++;
                }
            });

        return $sent;
    }

    /** Meal reminders that are due (not older than 30 minutes). */
    public function sendMeals(Gym $gym, ?Carbon $now = null): int
    {
        $now ??= now();
        $sent = 0;

        MemberMealReminder::with(['member', 'section'])->whereNull('sent_at')
            ->where('send_at', '<=', $now)->where('send_at', '>=', $now->copy()->subMinutes(self::MEAL_STALE_MINUTES))
            ->chunkById(500, function ($rows) use ($now, &$sent) {
                foreach ($rows as $reminder) {
                    $reminder->forceFill(['sent_at' => $now])->save();
                    $member = $reminder->member;

                    if (! $member || ! $reminder->section || ! $this->settingsFor($member)->diet_enabled || ! $this->canReceive($member)) {
                        continue;
                    }

                    $time = Carbon::createFromFormat('H:i', substr($reminder->meal_time, 0, 5))->format('g:i A');
                    $this->push($member, "{$reminder->title} · {$time}", $this->mealBody($reminder->section->body, $reminder->title), [
                        'type' => 'diet_meal', 'screen' => 'diet', 'date' => $reminder->date->toDateString(), 'section_id' => $reminder->plan_section_id,
                    ], '3600s');
                    $sent++;
                }
            });

        return $sent;
    }

    /** Workout reminders that are due. */
    public function sendWorkouts(Gym $gym, ?Carbon $now = null): int
    {
        $now ??= now();
        $sent = 0;

        MemberReminderSetting::with('member')->where('workout_enabled', true)->where('workout_next_at', '<=', $now)
            ->chunkById(500, function ($rows) use ($now, &$sent) {
                foreach ($rows as $settings) {
                    $due = $settings->workout_next_at;
                    $settings->workout_next_at = $this->at($now->copy()->addDay(), MemberReminderSetting::clock($settings->workout_time));
                    $settings->save();
                    $member = $settings->member;

                    if (! $member || $due->lt($now->copy()->subMinutes(self::WORKOUT_STALE_MINUTES)) || ! $this->canReceive($member)) {
                        continue;
                    }

                    $message = $this->workoutMessage($member, $settings, $now);
                    if ($message) {
                        $this->push($member, $message[0], $message[1], ['type' => 'workout_reminder', 'screen' => 'workout', 'date' => $now->toDateString()], '3600s');
                        $sent++;
                    }
                }
            });

        return $sent;
    }

    /** Build today's meal reminders for every member of the gym who has them on (daily, just after midnight). */
    public function buildMealRemindersForGym(Gym $gym, ?Carbon $date = null): int
    {
        $count = 0;

        MemberReminderSetting::with('member')->where('diet_enabled', true)->chunkById(500, function ($rows) use ($date, &$count) {
            foreach ($rows as $settings) {
                if ($settings->member) {
                    $this->buildMealReminders($settings->member, $date, $settings);
                    $count++;
                }
            }
        });

        return $count;
    }

    /** [title, body] for today's workout, or null when no reminder should go out. @return array{0: string, 1: string}|null */
    protected function workoutMessage(Member $member, MemberReminderSetting $settings, Carbon $now): ?array
    {
        $workout = $this->programs->scheduleOn($member, $now->copy()->startOfDay())['workout'];

        if (! $workout || in_array($workout['status'], ['done', 'skipped'], true)
            || Attendance::where('member_id', $member->id)->whereDate('date', $now->toDateString())->exists()) {
            return null;
        }

        if ($workout['is_rest']) {
            return $settings->workout_skip_rest_days ? null : ['Today: rest & recovery', 'Rest day. A light walk and some stretching will help you recover.'];
        }

        $sections = collect($workout['plan']['sections'] ?? []);
        $exercises = $sections->reject(fn ($s) => $this->isWarmUpOrCoolDown($s['title']));
        $minutes = $sections->sum(fn ($s) => $this->isWarmUpOrCoolDown($s['title'])
            ? (int) (preg_match('/(\d+)\s*(?:–|-|to)?\s*\d*\s*min/i', (string) $s['subtitle'], $m) ? $m[1] : 5)
            : (preg_match('/(\d+)\s*sets?/i', (string) $s['subtitle'], $m) ? (int) $m[1] : 3) * 2);
        $minutes = max(10, (int) round($minutes / 5) * 5);
        $count = $exercises->count();

        return [
            Str::limit('Today: '.$workout['title'], 65, '…'),
            $count ? "{$count} ".Str::plural('exercise', $count)." · about {$minutes} min. Let's go!" : "Your workout is ready. Let's go!",
        ];
    }

    protected function isWarmUpOrCoolDown(string $title): bool
    {
        return (bool) preg_match('/warm[\s-]?up|cool[\s-]?down/i', $title);
    }

    /** First two lines of the meal, "2 chillas + Mint chutney", at most 120 characters. */
    protected function mealBody(?string $body, string $title): string
    {
        $lines = collect(preg_split('/\R/', (string) $body))
            ->map(fn ($line) => trim(preg_replace('/^[\s•\-*·]+/u', '', $line)))->filter()->take(2);

        return $lines->isEmpty() ? "Time for your {$title}." : Str::limit($lines->implode(' + '), 120, '…');
    }

    /** Active member with a valid membership, a phone, and reminders switched on. */
    protected function canReceive(Member $member): bool
    {
        return $member->status === MemberStatus::Active
            && $this->remindersOn($member)
            && $member->devices()->exists()
            && $member->memberships()->valid()->exists();
    }

    protected function remindersOn(Member $member): bool
    {
        return $this->notifications->settings($member)['reminders'] ?? true;
    }

    /** @param  array<string, scalar>  $data */
    protected function push(Member $member, string $title, string $body, array $data, string $ttl, string $channel = 'reminders'): void
    {
        SendReminderPush::dispatch((int) $member->gym_id, $member->id, $title, $body, $data + ['channel' => $channel], $ttl)->afterCommit();
    }

    // ---- Time helpers -----------------------------------------------------------------------

    /** When water reminders start after switching them on (or changing them). */
    public function firstHydrationAt(MemberReminderSetting $settings, Carbon $now): Carbon
    {
        $start = $this->windowStart($settings, $now);

        if ($now->lt($start)) {
            return $start;
        }

        return $this->clampToWindow($settings, $now->copy()->addMinutes($settings->hydration_interval_minutes)->startOfMinute());
    }

    /** The reminder after one that was due: one interval later, or tomorrow's start after the end time. */
    public function nextHydrationAt(MemberReminderSetting $settings, Carbon $now): Carbon
    {
        $base = $settings->hydration_next_at && $settings->hydration_next_at->copy()->addMinutes($settings->hydration_interval_minutes)->gt($now)
            ? $settings->hydration_next_at->copy()
            : $now->copy()->startOfMinute();

        if (! $this->withinWindow($settings, $now)) {
            return $now->lt($this->windowStart($settings, $now)) ? $this->windowStart($settings, $now) : $this->windowStart($settings, $now->copy()->addDay());
        }

        return $this->clampToWindow($settings, $base->addMinutes($settings->hydration_interval_minutes));
    }

    public function nextWorkoutAt(MemberReminderSetting $settings, Carbon $now): Carbon
    {
        $today = $this->at($now, MemberReminderSetting::clock($settings->workout_time));

        return $today->gt($now) ? $today : $today->addDay();
    }

    protected function clampToWindow(MemberReminderSetting $settings, Carbon $at): Carbon
    {
        return $at->gt($this->windowEnd($settings, $at)) ? $this->windowStart($settings, $at->copy()->addDay()) : $at;
    }

    /** Inside today's water window (a few minutes of grace for the 5-minute scheduler). */
    protected function withinWindow(MemberReminderSetting $settings, Carbon $now): bool
    {
        return $now->gte($this->windowStart($settings, $now)) && $now->lte($this->windowEnd($settings, $now)->addMinutes(10));
    }

    protected function windowStart(MemberReminderSetting $settings, Carbon $day): Carbon
    {
        return $this->at($day, MemberReminderSetting::clock($settings->hydration_start));
    }

    protected function windowEnd(MemberReminderSetting $settings, Carbon $day): Carbon
    {
        return $this->at($day, MemberReminderSetting::clock($settings->hydration_end));
    }

    protected function at(Carbon $day, string $time): Carbon
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $day->copy()->setTime($hour, $minute);
    }

    protected function minutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $hour * 60 + $minute;
    }

    protected function mlOn(Member $member, Carbon $day): int
    {
        return (int) MemberWaterLog::where('member_id', $member->id)->whereDate('date', $day->toDateString())->sum('ml');
    }

    protected function firstName(Member $member): string
    {
        return Str::before(trim($member->name), ' ') ?: 'there';
    }

    /**
     * The Home "Reminders" card: how many are on, the soonest one, and one worth switching on.
     *
     * @param  array<string, mixed>  $hydration
     * @param  list<array<string, mixed>>  $meals
     * @param  list<int>  $sent
     * @param  array<string, mixed>|null  $workout
     * @return array<string, mixed>
     */
    protected function highlight(MemberReminderSetting $settings, array $hydration, array $meals, array $sent, ?array $workout, bool $paused, Carbon $now): array
    {
        $upcoming = collect();

        if (! $paused) {
            if ($hydration['next_at']) {
                $at = Carbon::parse($hydration['next_at']);
                $upcoming->push(['type' => 'hydration', 'title' => 'Water · '.$at->format('g:i A'), 'at' => $at]);
            }
            if ($settings->diet_enabled && ($meal = collect($meals)->first(fn ($m) => ! in_array($m['section_id'], $sent, true) && $m['remind_at']->gt($now)))) {
                $upcoming->push(['type' => 'diet', 'title' => $meal['title'].' · '.$this->at($now, $meal['time'])->format('g:i A'), 'at' => $meal['remind_at']]);
            }
            if ($settings->workout_enabled && $settings->workout_next_at) {
                $upcoming->push(['type' => 'workout', 'title' => 'Workout · '.$settings->workout_next_at->format('g:i A'), 'at' => $settings->workout_next_at]);
            }
        }

        $next = $upcoming->sortBy('at')->first();

        return [
            'enabled_count' => (int) $settings->hydration_enabled + (int) $settings->diet_enabled + (int) $settings->workout_enabled,
            'total' => 3,
            'next' => $next ? ['type' => $next['type'], 'title' => $next['title'], 'at' => $next['at']->toIso8601String()] : null,
            'suggest' => match (true) {
                ! $settings->workout_enabled && $workout !== null => 'workout',
                ! $settings->diet_enabled && $meals !== [] => 'diet',
                ! $settings->hydration_enabled => 'hydration',
                default => null,
            },
        ];
    }
}
