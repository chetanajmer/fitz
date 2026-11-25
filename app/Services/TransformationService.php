<?php

namespace App\Services;

use App\Models\AiReport;
use App\Models\Member;
use App\Models\MemberMeasurement;
use App\Models\ProgressPhoto;

/** A member's transformation: first versus latest weight, measurements and photos, with the history between. */
class TransformationService
{
    public function __construct(protected WeightTrackingService $weights) {}

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
