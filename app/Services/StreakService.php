<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberStreak;
use App\Models\Scopes\GymScope;
use App\Tenancy\TenantManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Attendance streaks.
 *
 * A streak is a chain of visit days. Two visits stay in the same chain when the number of
 * "missed" days between them is at most the gym's grace_days. Days the gym is closed
 * (from its opening hours) are not counted as missed when skip_closed_days is on.
 * The streak value is the number of visit days in the chain (matches "12 DAY STREAK").
 *
 * The current streak is alive while the missed days since the last visit (today excluded,
 * because today isn't over) are within grace_days.
 */
class StreakService
{
    /** @var array<string, array{grace_days:int, skip_closed_days:bool, milestones:list<int>, closed:list<string>}> */
    protected array $rulesCache = [];

    /**
     * Full computation from attendance history.
     *
     * @return array{current:int, current_started_on:?Carbon, alive:bool, at_risk:bool, longest:int, longest_started_on:?Carbon, longest_ended_on:?Carbon, last_visit_on:?Carbon, total_visit_days:int, visited_today:bool}
     */
    public function calculate(Member $member, ?Carbon $today = null): array
    {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();
        $rules = $this->rules($member->gym_id);
        $dates = $this->visitDates($member, $today);

        $result = [
            'current' => 0, 'current_started_on' => null, 'alive' => false, 'at_risk' => false,
            'longest' => 0, 'longest_started_on' => null, 'longest_ended_on' => null,
            'last_visit_on' => $dates->last(), 'total_visit_days' => $dates->count(),
            'visited_today' => (bool) $dates->last()?->isSameDay($today),
        ];

        if ($dates->isEmpty()) {
            return $result;
        }

        $chainStart = $dates->first();
        $chainLength = 0;
        $previous = null;

        foreach ($dates as $date) {
            if ($previous && $this->missedDaysBetween($previous, $date, $rules) > $rules['grace_days']) {
                $this->keepLongest($result, $chainLength, $chainStart, $previous);
                $chainStart = $date;
                $chainLength = 0;
            }
            $chainLength++;
            $previous = $date;
        }
        $this->keepLongest($result, $chainLength, $chainStart, $previous);

        $missedSinceLast = $this->missedDaysBetween($previous, $today, $rules);

        if ($missedSinceLast <= $rules['grace_days']) {
            $result['current'] = $chainLength;
            $result['current_started_on'] = $chainStart;
            $result['alive'] = true;
            // Skipping today would push the gap past the grace allowance.
            $result['at_risk'] = ! $result['visited_today']
                && $this->isOpenDay($today, $rules)
                && $missedSinceLast === $rules['grace_days'];
        }

        return $result;
    }

    /**
     * Recalculate and store the snapshot. Returns the stored row; $milestoneReached is set
     * when the member just crossed a new milestone (hook for future notifications).
     */
    public function refresh(Member $member, ?int &$milestoneReached = null): MemberStreak
    {
        $calc = $this->calculate($member);
        $rules = $this->rules($member->gym_id);

        $streak = MemberStreak::withoutGlobalScope(GymScope::class)->firstOrNew(['member_id' => $member->id]);
        $streak->gym_id = $member->gym_id;
        $streak->current_streak = $calc['current'];
        $streak->current_started_on = $calc['current_started_on'];
        $streak->longest_streak = $calc['longest'];
        $streak->longest_started_on = $calc['longest_started_on'];
        $streak->longest_ended_on = $calc['longest_ended_on'];
        $streak->last_visit_on = $calc['last_visit_on'];
        $streak->total_visit_days = $calc['total_visit_days'];
        $streak->calculated_at = now();

        $reached = collect($rules['milestones'])->filter(fn ($m) => $m <= $calc['current'])->max() ?? 0;
        $milestoneReached = null;
        if ($reached > (int) $streak->highest_milestone) {
            $streak->highest_milestone = $reached;
            $streak->milestone_reached_at = now();
            $milestoneReached = $reached;
        }

        $streak->save();

        return $streak;
    }

    /**
     * Recalculate every member of a gym (nightly job / "Recalculate" button).
     */
    public function refreshGym(Gym $gym): int
    {
        unset($this->rulesCache[$gym->id]);
        $count = 0;

        Member::forGym($gym)->select(['id', 'gym_id'])->chunkById(200, function ($members) use (&$count) {
            foreach ($members as $member) {
                $this->refresh($member);
                $count++;
            }
        });

        return $count;
    }

    /**
     * Everything the member app / UI needs to render the streak card.
     *
     * @return array<string, mixed>
     */
    public function summary(Member $member, ?Carbon $today = null): array
    {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();
        $rules = $this->rules($member->gym_id);
        $calc = $this->calculate($member, $today);
        $stored = MemberStreak::withoutGlobalScope(GymScope::class)->where('member_id', $member->id)->first();

        $next = collect($rules['milestones'])->first(fn ($m) => $m > $calc['current']);

        return [
            'current_streak' => $calc['current'],
            'longest_streak' => max($calc['longest'], $calc['current']),
            'is_active' => $calc['alive'],
            'at_risk' => $calc['at_risk'],
            'checked_in_today' => $calc['visited_today'],
            'current_started_on' => $calc['current_started_on']?->toDateString(),
            'last_visit_on' => $calc['last_visit_on']?->toDateString(),
            'visits_this_month' => $this->visitDates($member, $today)->filter(fn (Carbon $d) => $d->isSameMonth($today))->count(),
            'total_visit_days' => $calc['total_visit_days'],
            'next_milestone' => $next ? ['target' => $next, 'remaining' => $next - $calc['current']] : null,
            'highest_milestone' => (int) ($stored?->highest_milestone ?? 0),
            'milestones' => $rules['milestones'],
            'week' => $this->week($member, $today),
            'rules' => ['grace_days' => $rules['grace_days'], 'skip_closed_days' => $rules['skip_closed_days']],
        ];
    }

    /**
     * Monday → Sunday of the current week.
     * status: attended | missed | closed | today | upcoming
     *
     * @return list<array{date:string, day:string, label:string, status:string}>
     */
    public function week(Member $member, ?Carbon $today = null): array
    {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();
        $rules = $this->rules($member->gym_id);
        $start = $today->copy()->startOfWeek(Carbon::MONDAY);
        $visited = $this->visitDates($member, $today)
            ->filter(fn (Carbon $d) => $d->gte($start))
            ->map(fn (Carbon $d) => $d->toDateString())
            ->all();

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $start->copy()->addDays($i);
            $status = match (true) {
                in_array($date->toDateString(), $visited, true) => 'attended',
                $date->isSameDay($today) => 'today',
                $date->gt($today) => 'upcoming',
                ! $this->isOpenDay($date, $rules) => 'closed',
                default => 'missed',
            };

            $days[] = [
                'date' => $date->toDateString(),
                'day' => strtolower($date->format('l')),
                'label' => substr($date->format('D'), 0, 1),
                'status' => $status,
            ];
        }

        return $days;
    }

    // ---- Internals --------------------------------------------------------------

    /** @return Collection<int, Carbon> distinct visit dates, ascending, up to $today */
    protected function visitDates(Member $member, Carbon $today): Collection
    {
        return Attendance::withoutGlobalScope(GymScope::class)
            ->where('member_id', $member->id)
            ->whereDate('date', '<=', $today->toDateString())
            ->orderBy('date')
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->startOfDay())
            ->unique(fn (Carbon $d) => $d->toDateString())
            ->values();
    }

    /** Open (non-closed) days strictly between two dates. Stops counting once past 8. */
    protected function missedDaysBetween(Carbon $from, Carbon $to, array $rules): int
    {
        $missed = 0;
        for ($d = $from->copy()->addDay(); $d->lt($to); $d->addDay()) {
            if ($this->isOpenDay($d, $rules) && ++$missed > 8) {
                break;
            }
        }

        return $missed;
    }

    protected function isOpenDay(Carbon $date, array $rules): bool
    {
        return ! $rules['skip_closed_days'] || ! in_array(strtolower($date->format('l')), $rules['closed'], true);
    }

    protected function keepLongest(array &$result, int $length, Carbon $start, Carbon $end): void
    {
        if ($length > $result['longest']) {
            $result['longest'] = $length;
            $result['longest_started_on'] = $start;
            $result['longest_ended_on'] = $end;
        }
    }

    /** @return array{grace_days:int, skip_closed_days:bool, milestones:list<int>, closed:list<string>} */
    protected function rules(int $gymId): array
    {
        return $this->rulesCache[$gymId] ??= (function () use ($gymId) {
            $gym = app(TenantManager::class)->gym()?->id === $gymId ? app(TenantManager::class)->gym() : Gym::find($gymId);

            return $gym->streakRules() + ['closed' => $gym->closedWeekdays()];
        })();
    }

    public function forgetRules(?int $gymId = null): void
    {
        if ($gymId === null) {
            $this->rulesCache = [];
        } else {
            unset($this->rulesCache[$gymId]);
        }
    }
}
