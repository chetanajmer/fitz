<?php

namespace App\Services;

use App\Enums\MemberStatus;
use App\Exceptions\ApiException;
use App\Models\AiReport;
use App\Models\Gym;
use App\Models\GymAiSetting;
use App\Models\Member;
use App\Models\MemberConsent;
use App\Models\PlatformSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Who may use the AI progress module and how much. Three levels, the strictest wins:
 *  1. Super Admin: platform switch; per gym allowed, monthly credit pool, per-member ceiling, photos allowed.
 *  2. Gym admin: enabled, eligible members (all / plans / selected), per-member limit (≤ ceiling).
 *  3. Member: consent, and credits left this month.
 * Usage = credits of this month's non-failed AI reports.
 */
class AiAccessService
{
    /** The consent text the member agrees to (config/consents.php, versioned). */
    public function consentText(): string
    {
        return (string) config('consents.ai_coach.text');
    }

    public function __construct(protected BodyProgressService $body) {}

    /** The gym's settings, or the defaults for a gym the Super Admin has not configured yet (unsaved). */
    public function settingsFor(Gym $gym): GymAiSetting
    {
        return GymAiSetting::forGym($gym)->first() ?? $this->defaultsFor($gym);
    }

    public function defaultsFor(Gym $gym): GymAiSetting
    {
        $settings = new GymAiSetting([
            'allowed' => false,
            'monthly_credits' => (int) PlatformSetting::get('ai_default_gym_credits'),
            'member_ceiling' => (int) PlatformSetting::get('ai_default_member_ceiling'),
            'photos_allowed' => true,
            'enabled' => false,
            'eligibility' => 'all',
            'member_limit' => (int) PlatformSetting::get('ai_default_member_ceiling'),
            'auto_monthly_report' => false,
        ]);
        $settings->gym_id = $gym->id;

        return $settings;
    }

    public function platformEnabled(): bool
    {
        return PlatformSetting::get('ai_enabled') === '1';
    }

    public function cost(string $type): int
    {
        return max(1, (int) PlatformSetting::get($type === AiReport::PHOTO_COMPARISON ? 'ai_credits_photo_comparison' : 'ai_credits_progress'));
    }

    public function memberUsed(Member $member, ?Carbon $month = null): int
    {
        return (int) AiReport::forGym($member->gym_id)->where('member_id', $member->id)->countingInMonth($month)->sum('credits');
    }

    public function gymUsed(Gym|int $gym, ?Carbon $month = null): int
    {
        return (int) AiReport::forGym($gym)->countingInMonth($month)->sum('credits');
    }

    public function isEligible(Member $member, GymAiSetting $settings): bool
    {
        if ($member->status !== MemberStatus::Active || ! ($membership = $member->currentMembership())) {
            return false;
        }

        return match ($settings->eligibility) {
            'plans' => in_array($membership->plan_id, array_map('intval', $settings->eligible_plan_ids ?? []), true),
            'selected' => $member->ai_access,
            default => true,
        };
    }

    /**
     * Throws when the member may not use this AI feature right now (code + message for the app).
     * Call it again inside the request transaction, with the gym settings row locked.
     */
    public function authorize(Member $member, string $type, ?GymAiSetting $settings = null): void
    {
        $settings ??= $this->settingsFor($member->gym);
        $cost = $this->cost($type);

        $denial = match (true) {
            ! $this->platformEnabled() => ['AI_DISABLED', 'AI coach is not available right now.', 403],
            ! $settings->allowed || ! $settings->enabled => ['AI_NOT_AVAILABLE', 'Your gym has not switched on AI coach.', 403],
            $type === AiReport::PHOTO_COMPARISON && ! $settings->photos_allowed => ['AI_FEATURE_DISABLED', 'Photo comparison is not available at your gym.', 403],
            ! $this->isEligible($member, $settings) => ['AI_NOT_ELIGIBLE', 'AI coach is not included in your membership. Ask your gym about it.', 403],
            $member->ai_consent_at === null => ['AI_CONSENT_REQUIRED', 'Please accept the AI consent first.', 403],
            $this->memberUsed($member) + $cost > $settings->memberMonthlyLimit() => ['AI_LIMIT_REACHED', 'You have used all your AI credits for this month.', 429],
            $this->gymUsed($member->gym_id) + $cost > $settings->monthly_credits => ['AI_GYM_LIMIT_REACHED', 'Your gym has used all its AI credits for this month.', 429],
            default => null,
        };

        if ($denial) {
            throw new ApiException($denial[1], $denial[0], $denial[2], ['credits' => $this->credits($member, $settings)]);
        }
    }

    /** Whether a queued report may still send the member's data: things can change after it was requested. */
    public function maySend(Member $member, string $type): bool
    {
        $settings = $this->settingsFor($member->gym);

        return ! $member->trashed()
            && $this->platformEnabled() && $settings->allowed && $settings->enabled
            && ($type !== AiReport::PHOTO_COMPARISON || $settings->photos_allowed)
            && $member->ai_consent_at !== null
            && $this->isEligible($member, $settings);
    }

    /** @return array{limit: int, used: int, remaining: int, resets_on: string} */
    public function credits(Member $member, ?GymAiSetting $settings = null): array
    {
        $settings ??= $this->settingsFor($member->gym);
        $limit = $settings->memberMonthlyLimit();
        $used = $this->memberUsed($member);

        return ['limit' => $limit, 'used' => $used, 'remaining' => max(0, $limit - $used), 'resets_on' => Carbon::today()->addMonthNoOverflow()->startOfMonth()->toDateString()];
    }

    /** What the member app shows on the AI coach screen. @return array<string, mixed> */
    public function status(Member $member): array
    {
        $settings = $this->settingsFor($member->gym);
        $available = $this->platformEnabled() && $settings->allowed && $settings->enabled && $this->isEligible($member, $settings);

        return [
            'available' => $available,
            'has_consented' => $member->ai_consent_at !== null,
            'consented_at' => $member->ai_consent_at?->toIso8601String(),
            'consent_text' => $this->consentText(),
            'consent_version' => (int) config('consents.ai_coach.version'),
            'consent_needs_update' => $this->consents()->state($member, MemberConsent::AI_COACH)['needs_update'],
            'photo_consent' => collect($this->consents()->state($member, MemberConsent::PROGRESS_PHOTOS))->only(['accepted', 'needs_update'])->all(),
            'credits' => $this->credits($member, $settings),
            'features' => [
                AiReport::PROGRESS => ['available' => $available, 'credits' => $this->cost(AiReport::PROGRESS)],
                AiReport::PHOTO_COMPARISON => ['available' => $available && $settings->photos_allowed, 'credits' => $this->cost(AiReport::PHOTO_COMPARISON)],
            ],
            'disclaimer' => AiReport::DISCLAIMER,
        ];
    }

    /**
     * Agree to or withdraw AI coach. Withdrawing deletes the AI reports and the progress photos.
     * Returns how many reports were erased.
     */
    public function setConsent(Member $member, bool $accepted, ?Request $request = null): int
    {
        return DB::transaction(function () use ($member, $accepted, $request) {
            $member->forceFill(['ai_consent_at' => $accepted ? now() : null])->save();
            $this->consents()->record($member, MemberConsent::AI_COACH, $accepted, $request);

            if ($accepted) {
                return 0;
            }

            $erased = $this->eraseReports($member);
            $this->body->deleteAllPhotos($member);

            return $erased;
        });
    }

    protected function consents(): ConsentService
    {
        return app(ConsentService::class);
    }

    /**
     * Delete the member's report history. Finished reports keep a bare usage record: without it, withdrawing
     * and agreeing again would refill the monthly credits, and the platform would lose its AI cost figures.
     */
    public function eraseReports(Member $member, ?callable $filter = null): int
    {
        $reports = AiReport::forGym($member->gym_id)->where('member_id', $member->id)->where('status', '!=', AiReport::ERASED)
            ->when($filter, fn ($q) => $filter($q));
        $count = (clone $reports)->count();

        (clone $reports)->whereIn('status', [AiReport::PENDING, AiReport::FAILED])->delete();
        $reports->where('status', AiReport::COMPLETED)->update([
            'status' => AiReport::ERASED,
            'content' => null,
            'result' => null,
            'before_photo_id' => null,
            'after_photo_id' => null,
            'requested_by_user' => null,
            'error' => null,
            'updated_at' => now(),
        ]);

        return $count;
    }
}
