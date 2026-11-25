<?php

namespace App\Services;

use App\Models\AiReport;
use App\Models\Member;
use App\Models\MemberMeasurement;
use App\Models\ProgressPhoto;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/** A member's transformation: first versus latest weight, measurements and photos, with the history between. */
class TransformationService
{
    public function __construct(protected WeightTrackingService $weights, protected StreakService $streaks) {}

    /**
     * Weight and measurements next to the two photos of a comparison: for each, the reading closest to the
     * photo and at most two weeks away. Needs a reading for both photos. $summary comes from summary().
     *
     * @param  array<string, mixed>  $summary
     * @return list<array{label: string, unit: string, before: float, after: float, change: float}>
     */
    public function readingsFor(AiReport $report, array $summary): array
    {
        if (! $report->beforePhoto || ! $report->afterPhoto) {
            return [];
        }

        $closest = fn (array $history, Carbon $day) => collect($history)
            ->map(fn (array $h) => $h + ['days' => abs(Carbon::parse($h['date'])->startOfDay()->diffInDays($day))])
            ->where('days', '<=', 14)->sortBy('days')->first();

        return collect([['label' => 'Weight (kg)', 'history' => $summary['weight']['history']], ...$summary['measurements']])
            ->map(function (array $series) use ($report, $closest) {
                $before = $closest($series['history'], $report->beforePhoto->taken_on);
                $after = $closest($series['history'], $report->afterPhoto->taken_on);

                return ! $before || ! $after || $before['date'] >= $after['date'] ? null : [
                    'label' => Str::before($series['label'], ' ('),
                    'unit' => Str::between($series['label'], '(', ')'),
                    'before' => (float) $before['value'],
                    'after' => (float) $after['value'],
                    'change' => round($after['value'] - $before['value'], 1),
                ];
            })->filter()->values()->all();
    }

    /**
     * The eye-catching part of a finished report: a headline and up to six figures, all from the member's own
     * records (never numbers the AI wrote). Null for older reports without fields.
     *
     * @param  array<string, mixed>  $summary
     * @return array{headline: string, period: ?string, stats: list<array{label: string, value: string, detail: ?string}>, shareable: bool}|null
     */
    public function highlight(AiReport $report, Member $member, array $summary): ?array
    {
        if (! $report->result) {
            return null;
        }

        return $report->type === AiReport::PHOTO_COMPARISON
            ? $this->comparisonHighlight($report, $summary)
            : $this->progressHighlight($report, $member, $summary);
    }

    /** @param  array<string, mixed>  $summary */
    protected function comparisonHighlight(AiReport $report, array $summary): array
    {
        $level = $report->result['change_level'] ?? 'unclear';
        $days = $report->beforePhoto && $report->afterPhoto ? (int) $report->beforePhoto->taken_on->diffInDays($report->afterPhoto->taken_on) : 0;

        return [
            'headline' => AiReport::CHANGE_LEVELS[$level] ?? $level,
            'period' => match (true) {
                $days < 1 => null,
                $days < 14 => "in {$days} ".Str::plural('day', $days),
                $days < 60 => 'in '.intdiv($days, 7).' weeks',
                default => 'in '.(int) round($days / 30.4).' months',
            },
            // Biggest change first.
            'stats' => collect($this->readingsFor($report, $summary))->where('change', '!=', 0)
                ->sortByDesc(fn (array $r) => abs($r['change'] / max($r['before'], 1)))->take(6)
                ->map(fn (array $r) => ['label' => $r['label'], 'value' => $this->signed($r['change']).' '.($r['unit'] === '%' ? 'points' : $r['unit']), 'detail' => "{$r['before']} to {$r['after']}".($r['unit'] === '%' ? '%' : '')])
                ->values()->all(),
            'shareable' => in_array($level, ['slight', 'moderate', 'strong'], true),
        ];
    }

    /** Attendance and weight as they were on the day of the report. @param  array<string, mixed>  $summary */
    protected function progressHighlight(AiReport $report, Member $member, array $summary): array
    {
        $streak = $this->streaks->calculate($member, $report->created_at);
        $visits = $streak['total_visit_days'];
        $showStreak = $streak['current'] >= 3;
        $weights = collect($summary['weight']['history'])->where('date', '<=', $report->created_at->toDateString())->sortBy('date')->values();
        $change = $weights->count() > 1 ? round($weights->last()['value'] - $weights->first()['value'], 1) : 0.0;

        return [
            'headline' => match (true) {
                $showStreak => "{$streak['current']} day streak",
                $visits > 0 => $visits.' gym '.Str::plural('visit', $visits),
                default => 'Day one',
            },
            'period' => null,
            'stats' => array_values(array_filter([
                $showStreak ? ['label' => 'gym visits', 'value' => (string) $visits, 'detail' => null] : null,
                $streak['longest'] > 1 && $streak['longest'] > $streak['current'] ? ['label' => 'best streak', 'value' => "{$streak['longest']} days", 'detail' => null] : null,
                $change != 0 ? ['label' => 'Weight', 'value' => $this->signed($change).' kg', 'detail' => "{$weights->first()['value']} to {$weights->last()['value']}"] : null,
            ])),
            'shareable' => $visits > 0,
        ];
    }

    protected function signed(float $value): string
    {
        return ($value > 0 ? '+' : '−').abs($value);
    }

    /**
     * Photos are models (callers turn them into URLs); everything else is plain data.
     *
     * @return array<string, mixed>
     */
    public function summary(Member $member): array
    {
        $weight = $this->weights->summary($member, 100);
        $measurements = $member->measurements()->get()->sortBy('measured_on')->values();
        $latestComparison = $member->aiReports()->where('type', AiReport::PHOTO_COMPARISON)->where('status', AiReport::COMPLETED)->first();

        return [
            'weight' => [
                'first_kg' => $weight['first_weight_kg'],
                'first_on' => $weight['first_measured_on'],
                'latest_kg' => $weight['current_weight_kg'],
                'latest_on' => $weight['last_measured_on'],
                'change_kg' => $weight['change_since_start_kg'],
                'history' => array_map(fn ($h) => ['date' => $h['measured_on'], 'value' => $h['weight_kg']], $weight['history']),
            ],
            'measurements' => collect(MemberMeasurement::FIELDS)->map(function (string $label, string $field) use ($measurements) {
                $values = $measurements->whereNotNull($field)->values();

                return $values->isEmpty() ? null : [
                    'field' => $field,
                    'label' => $label,
                    'first' => $values->first()->{$field},
                    'first_on' => $values->first()->measured_on->toDateString(),
                    'latest' => $values->last()->{$field},
                    'latest_on' => $values->last()->measured_on->toDateString(),
                    'change' => $values->count() > 1 ? round($values->last()->{$field} - $values->first()->{$field}, 1) : null,
                    'history' => $values->map(fn (MemberMeasurement $m) => ['date' => $m->measured_on->toDateString(), 'value' => $m->{$field}])->all(),
                ];
            })->filter()->values()->all(),
            // First and latest photo of each pose: only the same pose compares fairly.
            'photos' => $member->progressPhotos()->get()->sortBy(['taken_on', 'id'])->groupBy('pose')
                ->filter(fn ($photos) => $photos->count() > 1)
                ->map(fn ($photos, string $pose) => ['pose' => $pose, 'label' => ProgressPhoto::POSES[$pose] ?? $pose, 'before' => $photos->first(), 'after' => $photos->last()])
                ->values()->all(),
            'latest_comparison' => $latestComparison,
        ];
    }
}
