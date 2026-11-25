<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Member;
use App\Models\MemberMeasurement;
use App\Models\ProgressPhoto;
use App\Services\AiReportService;
use App\Services\BodyProgressService;
use App\Services\TransformationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Body measurements and progress photos from the member app. */
class BodyProgressController extends ApiController
{
    public function __construct(protected BodyProgressService $body) {}

    /** GET /api/v1/me/measurements — oldest first. */
    public function measurements(Request $request): JsonResponse
    {
        return $this->success($this->measurementData($this->member($request)), 'Measurements.');
    }

    /** POST /api/v1/me/measurements  { measured_on?, chest_cm?, waist_cm?, hips_cm?, arm_cm?, thigh_cm?, body_fat_percent? } */
    public function storeMeasurement(Request $request): JsonResponse
    {
        $member = $this->member($request);
        $this->body->recordMeasurement($member, $request->validate(BodyProgressService::measurementRules()));

        return $this->success($this->measurementData($member), 'Measurements saved.', 201);
    }

    /** DELETE /api/v1/me/measurements/{measurementId} */
    public function destroyMeasurement(Request $request, int $measurementId): JsonResponse
    {
        $member = $this->member($request);
        $this->body->deleteMeasurement($member, $measurementId);

        return $this->success($this->measurementData($member), 'Measurement deleted.');
    }

    /** GET /api/v1/me/progress-photos — oldest first; image_url needs the same bearer token. */
    public function photos(Request $request): JsonResponse
    {
        return $this->success(['photos' => $this->photoList($this->member($request))], 'Progress photos.');
    }

    /** POST /api/v1/me/progress-photos (multipart)  { photo, pose: front|side|back, taken_on? } */
    public function storePhoto(Request $request): JsonResponse
    {
        $data = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
            'pose' => ['required', Rule::in(array_keys(ProgressPhoto::POSES))],
            'taken_on' => ['nullable', 'date', 'before_or_equal:today', 'after:2000-01-01'],
        ]);

        $member = $this->member($request);
        $this->body->addPhoto($member, $request->file('photo'), $data['pose'], $data['taken_on'] ?? null);

        return $this->success(['photos' => $this->photoList($member)], 'Photo saved.', 201);
    }

    /** DELETE /api/v1/me/progress-photos/{photoId} */
    public function destroyPhoto(Request $request, int $photoId): JsonResponse
    {
        $member = $this->member($request);
        $this->body->deletePhoto($member, $photoId);

        return $this->success(['photos' => $this->photoList($member)], 'Photo deleted.');
    }

    /** GET /api/v1/me/progress-photos/{photoId}/image — the image file (private). */
    public function photoImage(Request $request, int $photoId): StreamedResponse
    {
        return $this->body->photoResponse($this->member($request)->progressPhotos()->findOrFail($photoId));
    }

    /** GET /api/v1/me/transformation — first versus latest weight, measurements and photos. */
    public function transformation(Request $request, TransformationService $transformation, AiReportService $reports): JsonResponse
    {
        $data = $transformation->summary($this->member($request));
        $photo = fn (ProgressPhoto $p) => $this->body->photoArray($p) + ['image_url' => route('api.v1.me.progress-photos.image', $p->id)];
        $data['photos'] = array_map(fn ($pair) => ['before' => $photo($pair['before']), 'after' => $photo($pair['after'])] + $pair, $data['photos']);
        $data['latest_comparison'] = $data['latest_comparison'] ? $reports->toArray($data['latest_comparison']) : null;

        return $this->success($data, 'Transformation.');
    }

    /** @return array{fields: array<string, string>, measurements: list<array<string, mixed>>} */
    protected function measurementData(Member $member): array
    {
        return [
            'fields' => MemberMeasurement::FIELDS,
            'measurements' => $member->measurements()->get()->map(fn (MemberMeasurement $m) => $this->body->measurementArray($m))->all(),
        ];
    }

    /** @return list<array<string, mixed>> */
    protected function photoList(Member $member): array
    {
        return $member->progressPhotos()->get()
            ->map(fn (ProgressPhoto $p) => $this->body->photoArray($p) + ['image_url' => route('api.v1.me.progress-photos.image', $p->id)])
            ->all();
    }

    protected function member(Request $request): Member
    {
        return $request->user('member');
    }
}
