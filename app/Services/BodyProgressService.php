<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MemberMeasurement;
use App\Models\ProgressPhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Body measurements and progress photos, recorded by the member (app) or the gym (staff).
 * Photos live on the private "local" disk: they are only served through authorised routes.
 * Runs inside the member's gym tenant context.
 */
class BodyProgressService
{
    public const DISK = 'local';

    /** Validation rules shared by the API and the gym panel. @return array<string, list<string>> */
    public static function measurementRules(): array
    {
        $cm = ['nullable', 'numeric', 'between:10,300'];

        return [
            'measured_on' => ['nullable', 'date', 'before_or_equal:today', 'after:2000-01-01'],
            'chest_cm' => $cm, 'waist_cm' => $cm, 'hips_cm' => $cm, 'arm_cm' => $cm, 'thigh_cm' => $cm,
            'body_fat_percent' => ['nullable', 'numeric', 'between:2,70'],
        ];
    }

    /**
     * Save the measurements for a day. Re-entering the same day updates that entry.
     *
     * @param  array<string, mixed>  $values  keys of MemberMeasurement::FIELDS (+ measured_on)
     */
    public function recordMeasurement(Member $member, array $values, string $source = 'app', ?User $recordedBy = null): MemberMeasurement
    {
        $fields = array_filter(array_intersect_key($values, MemberMeasurement::FIELDS), fn ($v) => $v !== null && $v !== '');

        if ($fields === []) {
            throw ValidationException::withMessages(['waist_cm' => 'Enter at least one measurement.']);
        }

        $date = Carbon::parse($values['measured_on'] ?? Carbon::today())->toDateString();
        $entry = MemberMeasurement::where('member_id', $member->id)->whereDate('measured_on', $date)->first() ?? new MemberMeasurement;
        $entry->member_id = $member->id;
        $entry->fill(['measured_on' => $date] + array_map(fn ($v) => round((float) $v, 1), $fields));
        $entry->source = $source;
        $entry->recorded_by = $recordedBy?->id;
        $entry->save();

        return $entry;
    }

    public function deleteMeasurement(Member $member, int $id): void
    {
        MemberMeasurement::where('member_id', $member->id)->findOrFail($id)->delete();
    }

    public function addPhoto(Member $member, UploadedFile $file, string $pose, ?string $takenOn = null, string $source = 'app', ?User $uploadedBy = null): ProgressPhoto
    {
        $path = $file->storeAs("progress-photos/{$member->gym_id}/{$member->id}", Str::random(40).'.'.$file->extension(), self::DISK);

        $photo = new ProgressPhoto;
        $photo->member_id = $member->id;
        $photo->fill(['pose' => $pose, 'taken_on' => Carbon::parse($takenOn ?? Carbon::today())->toDateString()]);
        $photo->path = $path;
        $photo->source = $source;
        $photo->uploaded_by = $uploadedBy?->id;
        $photo->save();

        return $photo;
    }

    public function deletePhoto(Member $member, int $id): void
    {
        $photo = ProgressPhoto::where('member_id', $member->id)->findOrFail($id);
        Storage::disk(self::DISK)->delete($photo->path);
        $photo->delete();
    }

    public function photoResponse(ProgressPhoto $photo): StreamedResponse
    {
        abort_unless(Storage::disk(self::DISK)->exists($photo->path), 404);

        return Storage::disk(self::DISK)->response($photo->path, null, ['Cache-Control' => 'private, max-age=3600']);
    }

    /** The photo as a data URL, for sending to the AI model. */
    public function photoDataUrl(ProgressPhoto $photo): string
    {
        $disk = Storage::disk(self::DISK);

        return 'data:'.$disk->mimeType($photo->path).';base64,'.base64_encode($disk->get($photo->path));
    }

    /** @return array<string, mixed> */
    public function measurementArray(MemberMeasurement $m): array
    {
        return ['id' => $m->id, 'measured_on' => $m->measured_on->toDateString(), 'source' => $m->source]
            + $m->only(array_keys(MemberMeasurement::FIELDS));
    }

    /** @return array<string, mixed> */
    public function photoArray(ProgressPhoto $p): array
    {
        return [
            'id' => $p->id,
            'pose' => $p->pose,
            'taken_on' => $p->taken_on->toDateString(),
            'source' => $p->source,
        ];
    }
}
