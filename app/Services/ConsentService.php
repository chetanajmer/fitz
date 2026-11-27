<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\AiReport;
use App\Models\Member;
use App\Models\MemberConsent;
use App\Models\ProgressPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Versioned member consents (config/consents.php). Every change is a new member_consents row, so the
 * history of what was agreed to, when and from where is kept. Withdrawing photo consent deletes the photos.
 */
class ConsentService
{
    public function __construct(protected BodyProgressService $body) {}

    public function currentVersion(string $type): int
    {
        return (int) config("consents.{$type}.version", 1);
    }

    public function text(string $type, Member $member): string
    {
        return strtr((string) config("consents.{$type}.text"), ['{gym_name}' => $member->gym->name]);
    }

    public function latest(Member $member, string $type): ?MemberConsent
    {
        return MemberConsent::forGym($member->gym_id)->where('member_id', $member->id)->where('type', $type)->latest('id')->first();
    }

    /** Agreed to the current version of the text. */
    public function hasCurrent(Member $member, string $type): bool
    {
        $latest = $this->latest($member, $type);

        return $latest !== null && $latest->accepted && $latest->version >= $this->currentVersion($type);
    }

    /** Photo uploads and photo comparisons need the current photo consent. */
    public function requirePhotoConsent(Member $member): void
    {
        if (! $this->hasCurrent($member, MemberConsent::PROGRESS_PHOTOS)) {
            throw new ApiException('Please agree to the progress photo consent first.', 'PHOTO_CONSENT_REQUIRED', 403, [
                'consent' => $this->state($member, MemberConsent::PROGRESS_PHOTOS),
            ]);
        }
    }

    /** @return array{accepted: bool, version: ?int, current_version: int, accepted_at: ?string, withdrawn_at: ?string, text: string, needs_update: bool} */
    public function state(Member $member, string $type): array
    {
        $latest = $this->latest($member, $type);
        $accepted = (bool) $latest?->accepted;

        return [
            'accepted' => $accepted,
            'version' => $latest?->version,
            'current_version' => $this->currentVersion($type),
            'accepted_at' => $accepted ? $latest->accepted_at?->toIso8601String() : null,
            'withdrawn_at' => $accepted ? null : $latest?->withdrawn_at?->toIso8601String(),
            'text' => $this->text($type, $member),
            'needs_update' => $accepted && $latest->version < $this->currentVersion($type),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function all(Member $member): array
    {
        return collect(MemberConsent::TYPES)->mapWithKeys(fn (string $type) => [$type => $this->state($member, $type)])->all();
    }

    /** Store one consent change (history row). */
    public function record(Member $member, string $type, bool $accepted, ?Request $request = null): MemberConsent
    {
        $consent = new MemberConsent;
        $consent->forceFill([
            'gym_id' => $member->gym_id,
            'member_id' => $member->id,
            'type' => $type,
            'version' => $this->currentVersion($type),
            'accepted' => $accepted,
            'accepted_at' => $accepted ? now() : null,
            'withdrawn_at' => $accepted ? null : now(),
            'ip' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250, '') : null,
        ])->save();

        return $consent;
    }

    /**
     * POST /me/consents. Returns what was deleted when consent was withdrawn.
     *
     * @return array{photos: int, reports: int}
     */
    public function set(Member $member, string $type, bool $accepted, ?Request $request = null): array
    {
        if ($type === MemberConsent::AI_COACH) {
            return ['photos' => 0, 'reports' => app(AiAccessService::class)->setConsent($member, $accepted, $request)];
        }

        return DB::transaction(function () use ($member, $accepted, $request) {
            $this->record($member, MemberConsent::PROGRESS_PHOTOS, $accepted, $request);

            return $accepted ? ['photos' => 0, 'reports' => 0] : $this->deletePhotos($member);
        });
    }

    /**
     * Delete all progress photos (rows + files) and the photo comparisons made from them.
     *
     * @return array{photos: int, reports: int}
     */
    public function deletePhotos(Member $member): array
    {
        $photoIds = ProgressPhoto::forGym($member->gym_id)->where('member_id', $member->id)->pluck('id')->all();

        // First: once the photos are gone the reports no longer point at them.
        $reports = $photoIds === [] ? 0 : app(AiAccessService::class)->eraseReports($member, fn ($q) => $q
            ->where('type', AiReport::PHOTO_COMPARISON)
            ->where(fn ($q) => $q->whereIn('before_photo_id', $photoIds)->orWhereIn('after_photo_id', $photoIds)));

        return ['photos' => $this->body->deleteAllPhotos($member), 'reports' => $reports];
    }

    /**
     * DELETE /me/ai/data: withdraw both consents, delete all progress photos and all AI reports.
     *
     * @return array{photos: int, reports: int}
     */
    public function deleteAiData(Member $member, ?Request $request = null): array
    {
        $deleted = DB::transaction(function () use ($member, $request) {
            $photos = $this->deletePhotos($member);
            $this->record($member, MemberConsent::PROGRESS_PHOTOS, false, $request);
            $reports = app(AiAccessService::class)->setConsent($member, false, $request);

            return ['photos' => $photos['photos'], 'reports' => $photos['reports'] + $reports];
        });

        // Audit trail without personal data.
        Log::info('Member AI data deleted', ['gym_id' => $member->gym_id, 'member_id' => $member->id] + $deleted);

        return $deleted;
    }
}
