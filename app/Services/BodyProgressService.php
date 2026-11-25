<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MemberMeasurement;
use App\Models\ProgressPhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

    protected const MAX_PIXELS = 50_000_000;

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
        // Re-encoded: smaller, upright and without EXIF data (phone photos carry the GPS location).
        $path = "progress-photos/{$member->gym_id}/{$member->id}/".Str::random(40).'.jpg';
        Storage::disk(self::DISK)->put($path, $this->jpeg($file->get(), 1600));

        $photo = new ProgressPhoto;
        $photo->member_id = $member->id;
        $photo->fill(['pose' => $pose, 'taken_on' => Carbon::parse($takenOn ?? Carbon::today())->toDateString()]);
        $photo->path = $path;
        $photo->source = $source;
        $photo->uploaded_by = $uploadedBy?->id;
        $photo->save();

        return $photo;
    }

    /** All of the member's photos: the records now, the files once the transaction is committed. */
    public function deleteAllPhotos(Member $member): void
    {
        $photos = ProgressPhoto::forGym($member->gym_id)->where('member_id', $member->id);
        $paths = $photos->pluck('path')->all();
        $photos->delete();

        DB::afterCommit(fn () => Storage::disk(self::DISK)->delete($paths));
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
        return 'data:image/jpeg;base64,'.base64_encode($this->jpeg(Storage::disk(self::DISK)->get($photo->path), 1024));
    }

    /** An upright JPEG at most $max pixels on its longest side, with no metadata. */
    protected function jpeg(string $bytes, int $max): string
    {
        $size = @getimagesizefromstring($bytes);

        if (! $size || $size[0] * $size[1] > self::MAX_PIXELS) {
            throw ValidationException::withMessages(['photo' => 'This photo cannot be used. Please upload a JPG, PNG or WebP photo of up to 50 megapixels.']);
        }

        $limit = ini_get('memory_limit');
        if ($limit !== '-1' && ini_parse_quantity($limit) < 512 * 1024 * 1024) {
            ini_set('memory_limit', '512M'); // decoding a large phone photo needs about 5 bytes per pixel
        }

        $source = @imagecreatefromstring($bytes);
        if (! $source) {
            throw ValidationException::withMessages(['photo' => 'This photo could not be read. Please try another one.']);
        }

        $scale = min(1, $max / max($size[0], $size[1]));
        [$width, $height] = [max(1, (int) round($size[0] * $scale)), max(1, (int) round($size[1] * $scale))];
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255)); // transparent PNG / WebP
        imagecopyresampled($image, $source, 0, 0, 0, 0, $width, $height, $size[0], $size[1]);
        unset($source);

        $orientation = $size['mime'] === 'image/jpeg' && function_exists('exif_read_data')
            ? (@exif_read_data('data://image/jpeg;base64,'.base64_encode($bytes))['Orientation'] ?? 1) : 1;
        $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;
        if ($angle) {
            $image = imagerotate($image, $angle, 0);
        }

        ob_start();
        imagejpeg($image, null, 85);

        return (string) ob_get_clean();
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
