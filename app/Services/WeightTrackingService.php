<?php

namespace App\Services;

use App\Models\Member;
use App\Models\User;
use App\Models\WeightGoal;
use App\Models\WeightLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Member weight tracking: weigh-ins, a current-vs-target goal, "almost there" / "target achieved"
 * popups and periodic weigh-in reminders. Runs inside the member's gym tenant context.
 */
class WeightTrackingService
{
    public const MIN_KG = 20;

    public const MAX_KG = 350;

    /** "Almost there" when at least half way and within this many kg (or 10% of the journey, if larger). */
    public const NEAR_KG = 1.0;

    /**
     * Record (or correct) the weight for a day. Re-entering the same day updates that reading.
     *
     * @return array{log: WeightLog, goal_achieved: bool}
     */
    public function record(Member $member, float $weightKg, ?string $measuredOn = null, string $source = 'app', ?User $recordedBy = null, ?string $note = null): array
    {
        $this->assertWeight($weightKg, 'weight_kg');
        $date = Carbon::parse($measuredOn ?? Carbon::today())->startOfDay();

        if ($date->isFuture()) {
            throw ValidationException::withMessages(['measured_on' => 'The measurement date cannot be in the future.']);
        }

        return DB::transaction(function () use ($member, $weightKg, $date, $source, $recordedBy, $note) {
            $log = WeightLog::where('member_id', $member->id)->whereDate('measured_on', $date->toDateString())->first() ?? new WeightLog;
            $log->member_id = $member->id;
            $log->fill(['weight_kg' => round($weightKg, 2), 'measured_on' => $date->toDateString(), 'note' => $note]);
            $log->source = $source;
            $log->recorded_by = $recordedBy?->id;
            $log->save();

            return ['log' => $log, 'goal_achieved' => $this->evaluateGoal($member)];
        });
    }

    public function deleteLog(Member $member, int $logId): void
    {
        WeightLog::where('member_id', $member->id)->findOrFail($logId)->delete();
    }

    /**
     * Start a new goal (any previous active goal is closed as "replaced").
     * The start weight is the given one (also recorded as today's weigh-in) or the latest reading.
     */
    public function setGoal(Member $member, float $targetKg, ?string $targetDate = null, ?float $currentKg = null, string $source = 'app', ?User $by = null): WeightGoal
    {
        $this->assertWeight($targetKg, 'target_weight_kg');

        if ($targetDate && ! Carbon::parse($targetDate)->isFuture()) {
            throw ValidationException::withMessages(['target_date' => 'The target date must be in the future.']);
        }

        return DB::transaction(function () use ($member, $targetKg, $targetDate, $currentKg, $source, $by) {
            if ($currentKg !== null) {
                $this->record($member, $currentKg, null, $source, $by);
            }

            $latest = $this->latestLog($member);

            if (! $latest) {
                throw ValidationException::withMessages(['current_weight_kg' => 'Enter your current weight to set a target.']);
            }

            if (abs($targetKg - (float) $latest->weight_kg) < 0.5) {
                throw ValidationException::withMessages(['target_weight_kg' => 'Your target must be at least 0.5 kg away from your current weight ('.$this->kg($latest->weight_kg).').']);
            }

            WeightGoal::where('member_id', $member->id)->where('status', WeightGoal::ACTIVE)->update(['status' => WeightGoal::REPLACED]);

            $goal = new WeightGoal;
            $goal->member_id = $member->id;
            $goal->start_weight_kg = $latest->weight_kg;
            $goal->target_weight_kg = round($targetKg, 2);
            $goal->start_date = Carbon::today();
            $goal->target_date = $targetDate ? Carbon::parse($targetDate) : null;
            $goal->status = WeightGoal::ACTIVE;
            $goal->set_by = $source;
            $goal->created_by = $by?->id;
            $goal->save();

            return $goal;
        });
    }

    /** The member has seen a popup: "goal_achieved" or "almost_there". */
    public function acknowledge(Member $member, string $type): void
    {
        $goal = $this->currentGoal($member);
        $column = match ($type) {
            'goal_achieved' => 'celebrated_at',
            'almost_there' => 'near_notified_at',
            default => null,
        };

        if ($goal && $column && ! $goal->{$column}) {
            $goal->forceFill([$column => now()])->save();
        }
    }

    /** Mark the active goal achieved if the latest reading reaches it. */
    protected function evaluateGoal(Member $member): bool
    {
        $goal = WeightGoal::where('member_id', $member->id)->where('status', WeightGoal::ACTIVE)->latest('id')->first();
        $latest = $this->latestLog($member);

        if (! $goal || ! $latest || $latest->measured_on->lt($goal->start_date) || ! $goal->isReachedBy((float) $latest->weight_kg)) {
            return false;
        }

        $goal->forceFill(['status' => WeightGoal::ACHIEVED, 'achieved_at' => now(), 'achieved_weight_kg' => $latest->weight_kg])->save();

        return true;
    }

    public function latestLog(Member $member): ?WeightLog
    {
        return WeightLog::where('member_id', $member->id)->orderByDesc('measured_on')->orderByDesc('id')->first();
    }

    /** The active goal, or the most recently achieved one. */
    public function currentGoal(Member $member): ?WeightGoal
    {
        return WeightGoal::where('member_id', $member->id)->whereIn('status', [WeightGoal::ACTIVE, WeightGoal::ACHIEVED])->latest('id')->first();
    }

    /**
     * Everything the app / panel needs: current weight, goal progress, BMI, chart history, reminder and popup.
     *
     * @return array<string, mixed>
     */
    public function summary(Member $member, int $historyLimit = 60): array
    {
        $member->loadMissing('gym');
        $logs = WeightLog::where('member_id', $member->id)->orderByDesc('measured_on')->limit($historyLimit)->get();
        $latest = $logs->first();
        $previous = $logs->get(1);
        $first = WeightLog::where('member_id', $member->id)->orderBy('measured_on')->first();
        $goal = $this->currentGoal($member);
        $goalData = $goal ? $this->goalProgress($goal, $latest) : null;
        $reminder = $this->reminder($member, $latest);

        return [
            'unit' => 'kg',
            'current_weight_kg' => $latest ? (float) $latest->weight_kg : null,
            'last_measured_on' => $latest?->measured_on->toDateString(),
            'change_since_last_kg' => $latest && $previous ? round((float) $latest->weight_kg - (float) $previous->weight_kg, 2) : null,
            'change_since_start_kg' => $latest && $first ? round((float) $latest->weight_kg - (float) $first->weight_kg, 2) : null,
            'first_weight_kg' => $first ? (float) $first->weight_kg : null,
            'first_measured_on' => $first?->measured_on->toDateString(),
            'height_cm' => $member->height_cm !== null ? (float) $member->height_cm : null,
            'bmi' => $this->bmi($member, $latest),
            'goal' => $goalData,
            'reminder' => $reminder,
            'popup' => $this->popup($goal, $goalData, $reminder, $latest),
            'history' => $logs->sortBy('measured_on')->values()->map(fn (WeightLog $l) => [
                'id' => $l->id,
                'weight_kg' => (float) $l->weight_kg,
                'measured_on' => $l->measured_on->toDateString(),
                'source' => $l->source,
                'note' => $l->note,
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    protected function goalProgress(WeightGoal $goal, ?WeightLog $latest): array
    {
        $start = (float) $goal->start_weight_kg;
        $target = (float) $goal->target_weight_kg;
        $current = $goal->status === WeightGoal::ACHIEVED && $goal->achieved_weight_kg !== null && (! $latest || $latest->measured_on->lt($goal->start_date))
            ? (float) $goal->achieved_weight_kg
            : ($latest ? (float) $latest->weight_kg : $start);
        $journey = abs($target - $start);
        $done = $goal->direction() === 'gain' ? $current - $start : $start - $current;
        $percent = $goal->status === WeightGoal::ACHIEVED ? 100.0 : max(0.0, min(100.0, $journey > 0 ? $done / $journey * 100 : 0));
        $remaining = $goal->status === WeightGoal::ACHIEVED ? 0.0 : max(0.0, round($journey - max(0, $done), 2));
        $near = $goal->status === WeightGoal::ACTIVE && $percent >= 50 && $remaining <= max(self::NEAR_KG, $journey * 0.1);

        return [
            'id' => $goal->id,
            'status' => $goal->status, // active | achieved
            'direction' => $goal->direction(), // lose | gain | maintain
            'start_weight_kg' => $start,
            'target_weight_kg' => $target,
            'current_weight_kg' => $current,
            'total_change_kg' => round($journey, 2),
            'achieved_change_kg' => round(max(0, $done), 2),
            'remaining_kg' => $remaining,
            'progress_percent' => round($percent, 1),
            'is_near_target' => $near,
            'start_date' => $goal->start_date->toDateString(),
            'target_date' => $goal->target_date?->toDateString(),
            'days_to_target_date' => $goal->target_date ? (int) Carbon::today()->diffInDays($goal->target_date, false) : null,
            'achieved_at' => $goal->achieved_at?->toIso8601String(),
            'achieved_weight_kg' => $goal->achieved_weight_kg !== null ? (float) $goal->achieved_weight_kg : null,
            'set_by' => $goal->set_by,
        ];
    }

    /** @return array<string, mixed> */
    protected function reminder(Member $member, ?WeightLog $latest): array
    {
        $every = $member->gym->weighInEveryDays();
        $nextDue = $latest ? $latest->measured_on->copy()->addDays($every) : Carbon::today();
        $today = Carbon::today();

        return [
            'every_days' => $every,
            'last_measured_on' => $latest?->measured_on->toDateString(),
            'next_due_on' => $nextDue->toDateString(),
            'is_due' => $nextDue->lte($today),
            'days_until_due' => max(0, (int) $today->diffInDays($nextDue, false)),
            'overdue_days' => $nextDue->lt($today) ? (int) $nextDue->diffInDays($today) : 0,
        ];
    }

    /**
     * The one popup the app should show now (highest priority first), or null.
     *
     * @return array{type: string, title: string, message: string, acknowledge: bool}|null
     */
    protected function popup(?WeightGoal $goal, ?array $goalData, array $reminder, ?WeightLog $latest): ?array
    {
        if ($goal && $goal->status === WeightGoal::ACHIEVED && ! $goal->celebrated_at) {
            $verb = $goal->direction() === 'gain' ? 'gained' : 'lost';

            return [
                'type' => 'goal_achieved',
                'title' => 'Target achieved!',
                'message' => "You reached your target of {$this->kg($goal->target_weight_kg)} — you {$verb} {$this->kg($goalData['total_change_kg'])}. Set a new target to keep going!",
                'acknowledge' => true,
            ];
        }

        if ($goalData && $goalData['is_near_target'] && ! $goal->near_notified_at) {
            return [
                'type' => 'almost_there',
                'title' => 'Almost there!',
                'message' => "Only {$this->kg($goalData['remaining_kg'])} to go to reach your target of {$this->kg($goalData['target_weight_kg'])}. You're going to achieve it!",
                'acknowledge' => true,
            ];
        }

        if ($reminder['is_due']) {
            return [
                'type' => 'weigh_in_due',
                'title' => 'Time for your weigh-in',
                'message' => $latest
                    ? "It's been {$latest->measured_on->diffInDays(Carbon::today())} days since your last weigh-in. Update your weight to track your progress."
                    : 'Add your current weight to start tracking your progress.',
                'acknowledge' => false, // goes away once a weight is recorded
            ];
        }

        return null;
    }

    /** @return array{value: float, category: string}|null */
    protected function bmi(Member $member, ?WeightLog $latest): ?array
    {
        if (! $latest || ! $member->height_cm || (float) $member->height_cm < 50) {
            return null;
        }

        $meters = (float) $member->height_cm / 100;
        $bmi = round((float) $latest->weight_kg / ($meters * $meters), 1);

        return [
            'value' => $bmi,
            'category' => match (true) {
                $bmi < 18.5 => 'Underweight',
                $bmi < 25 => 'Normal',
                $bmi < 30 => 'Overweight',
                default => 'Obese',
            },
        ];
    }

    /**
     * Active members whose weigh-in is due (never weighed, or last weigh-in older than the interval).
     * Adds "last_weighed_on". The caller applies visibility scopes.
     */
    public function dueQuery(int $everyDays): Builder
    {
        $cutoff = Carbon::today()->subDays($everyDays)->toDateString();

        return Member::query()->active()
            ->addSelect(['last_weighed_on' => WeightLog::select('measured_on')->whereColumn('member_id', 'members.id')->orderByDesc('measured_on')->limit(1)])
            ->whereDoesntHave('weightLogs', fn ($l) => $l->whereDate('measured_on', '>', $cutoff));
    }

    protected function assertWeight(float $kg, string $field): void
    {
        if ($kg < self::MIN_KG || $kg > self::MAX_KG) {
            throw ValidationException::withMessages([$field => 'Enter a weight between '.self::MIN_KG.' and '.self::MAX_KG.' kg.']);
        }
    }

    protected function kg($value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.').' kg';
    }
}
