<?php

namespace App\Services;

use App\Contracts\SmsSender;
use App\Enums\MemberStatus;
use App\Exceptions\ApiException;
use App\Models\Member;
use App\Models\MemberOtp;
use App\Models\Scopes\GymScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Mobile-number OTP login for members.
 *
 * A phone number can belong to members of several gyms. If it does, the client must
 * pass gym_id to pick one; the API answers MULTIPLE_GYMS with the choices otherwise.
 */
class MemberOtpService
{
    public const NOT_CONNECTED_MESSAGE = 'You are not connected with any gym on our network yet. We will share the available gym options near you — our support executive will call you shortly.';

    public function __construct(protected SmsSender $sms, protected PlatformLeadService $leads) {}

    /**
     * If the number is not an active member of any active gym, it is saved as a platform
     * lead for the Super Admin's support team and NO_GYM_CONNECTED is returned.
     *
     * @param  array{name?:?string, city?:?string, latitude?:?float, longitude?:?float, app_platform?:?string, app_version?:?string}  $leadInfo
     * @return array{member: Member, otp: MemberOtp, code: string}
     */
    public function send(string $phone, ?int $gymId, ?string $ip = null, array $leadInfo = []): array
    {
        try {
            $member = $this->resolveMember($phone, $gymId);
        } catch (ApiException $e) {
            if ($e->errorCode === 'MEMBER_NOT_FOUND' && $pending = $this->pendingRegistration($phone, $gymId)) {
                throw new ApiException("Your registration with {$pending->gym->name} is waiting for the gym's approval. You can log in once they approve it.", 'MEMBER_PENDING_APPROVAL', 403, [
                    'gym_id' => $pending->gym_id,
                    'gym_name' => $pending->gym->name,
                ]);
            }

            if ($e->errorCode === 'MEMBER_NOT_FOUND' && ! $this->hasAnyActiveMembership($phone)) {
                $this->leads->captureFromApp($phone, $leadInfo, $ip);

                throw new ApiException(self::NOT_CONNECTED_MESSAGE, 'NO_GYM_CONNECTED', 404, ['callback_requested' => true]);
            }

            throw $e;
        }
        $digits = $member->phone_digits;
        $isDemo = self::isDemoPhone($digits);

        if (! $isDemo) {
            $this->guardSendLimits($member, $digits);
        }

        $code = $isDemo ? (string) config('gymai.otp.demo_code') : $this->generateCode();
        $otp = DB::transaction(function () use ($member, $digits, $code, $ip) {
            // Only the newest OTP is ever valid.
            MemberOtp::where('member_id', $member->id)->whereNull('consumed_at')->update(['consumed_at' => now()]);

            $otp = new MemberOtp;
            $otp->member_id = $member->id;
            $otp->phone_digits = $digits;
            $otp->code_hash = MemberOtp::hashCode($code);
            $otp->expires_at = now()->addSeconds((int) config('gymai.otp.ttl_seconds', 300));
            $otp->ip_address = $ip;
            $otp->save();

            return $otp;
        });

        if (! $isDemo) {
            $gymName = $member->gym->name;
            $this->sms->send($member->phone, "{$code} is your {$gymName} app login OTP. It is valid for ".intdiv((int) config('gymai.otp.ttl_seconds', 300), 60).' minutes. Do not share it with anyone.');
        }

        return ['member' => $member, 'otp' => $otp, 'code' => $code];
    }

    /**
     * The store review number (config gymai.otp.demo_phone): it always gets the fixed demo code, without an SMS
     * and without the resend limits, so reviewers can log in at any time.
     */
    public static function isDemoPhone(?string $phone): bool
    {
        $demoPhone = Member::normalizePhone((string) config('gymai.otp.demo_phone'));
        $demoCode = (string) config('gymai.otp.demo_code');

        return $demoPhone !== null && preg_match('/^\d{4,8}$/', $demoCode) === 1 && Member::normalizePhone($phone) === $demoPhone;
    }

    /** Resend cooldown and the hourly per-number limit. */
    protected function guardSendLimits(Member $member, string $digits): void
    {
        $latest = MemberOtp::where('member_id', $member->id)->latest('id')->first();
        $cooldown = (int) config('gymai.otp.resend_cooldown_seconds', 30);
        if ($latest && $latest->consumed_at === null && $latest->created_at->gt(now()->subSeconds($cooldown))) {
            $wait = $cooldown - (int) $latest->created_at->diffInSeconds(now());
            throw new ApiException('Please wait before requesting another OTP.', 'OTP_TOO_MANY', 429, ['retry_after' => max(1, $wait)]);
        }

        $key = 'member-otp-send:'.$digits;
        if (RateLimiter::tooManyAttempts($key, (int) config('gymai.otp.max_sends_per_hour', 5))) {
            throw new ApiException('Too many OTP requests. Please try again later.', 'OTP_TOO_MANY', 429, ['retry_after' => RateLimiter::availableIn($key)]);
        }
        RateLimiter::hit($key, 3600);
    }

    /**
     * Verify the code and return the member. Wrong codes count against the OTP's attempt limit.
     */
    public function verify(string $phone, string $code, ?int $gymId): Member
    {
        $member = $this->resolveMember($phone, $gymId);

        $maxAttempts = (int) config('gymai.otp.max_attempts', 5);

        // The transaction returns a failure instead of throwing, so the attempt counter
        // increment is committed (a thrown exception would roll it back).
        $failure = DB::transaction(function () use ($member, $code, $maxAttempts): ?ApiException {
            $otp = MemberOtp::where('member_id', $member->id)->whereNull('consumed_at')->latest('id')->lockForUpdate()->first();

            if (! $otp) {
                return new ApiException('No OTP requested. Please request a new OTP.', 'OTP_NOT_FOUND', 422);
            }
            if ($otp->expires_at->isPast()) {
                return new ApiException('OTP has expired. Please request a new one.', 'OTP_EXPIRED', 422);
            }
            if ($otp->attempts >= $maxAttempts) {
                return new ApiException('Too many wrong attempts. Please request a new OTP.', 'OTP_ATTEMPTS_EXCEEDED', 429);
            }

            if (! $otp->matches($code)) {
                $otp->increment('attempts');

                return new ApiException('Incorrect OTP.', 'OTP_INVALID', 422, ['attempts_left' => max(0, $maxAttempts - $otp->attempts)]);
            }

            $otp->consumed_at = now();
            $otp->save();

            return null;
        });

        if ($failure) {
            throw $failure;
        }

        return $member;
    }

    /**
     * Find the active member for this phone (optionally within one gym) at an operational gym.
     */
    public function resolveMember(string $phone, ?int $gymId): Member
    {
        $digits = Member::normalizePhone($phone);

        /** @var Collection<int, Member> $candidates */
        $candidates = Member::withoutGlobalScope(GymScope::class)
            ->with('gym')
            ->where('phone_digits', $digits)
            ->where('status', MemberStatus::Active->value)
            ->whereHas('gym', fn ($q) => $q->operational())
            ->when($gymId, fn ($q) => $q->where('gym_id', $gymId))
            ->get();

        if ($candidates->isEmpty()) {
            throw new ApiException($gymId
                ? 'This mobile number is not registered with the selected gym.'
                : 'No active member account found for this mobile number.', 'MEMBER_NOT_FOUND', 404);
        }

        if ($candidates->count() > 1) {
            throw new ApiException('This mobile number is registered with more than one gym. Please choose your gym.', 'MULTIPLE_GYMS', 409, [
                'gyms' => $candidates->map(fn (Member $m) => [
                    'gym_id' => $m->gym_id,
                    'name' => $m->gym->name,
                    'city' => $m->gym->city,
                    'logo_url' => $m->gym->logoUrl(),
                ])->values()->all(),
            ]);
        }

        return $candidates->first();
    }

    /** A sign-up from the app still waiting for the gym's approval (newest first). */
    public function pendingRegistration(string $phone, ?int $gymId = null): ?Member
    {
        return Member::withoutGlobalScope(GymScope::class)
            ->with('gym')
            ->where('phone_digits', Member::normalizePhone($phone))
            ->where('status', MemberStatus::Pending->value)
            ->whereHas('gym', fn ($q) => $q->operational())
            ->when($gymId, fn ($q) => $q->where('gym_id', $gymId))
            ->latest('id')
            ->first();
    }

    /** True if the number is an active member of at least one active gym (any gym). */
    public function hasAnyActiveMembership(string $phone): bool
    {
        return Member::withoutGlobalScope(GymScope::class)
            ->where('phone_digits', Member::normalizePhone($phone))
            ->where('status', MemberStatus::Active->value)
            ->whereHas('gym', fn ($q) => $q->operational())
            ->exists();
    }

    protected function generateCode(): string
    {
        $length = (int) config('gymai.otp.length', 6);

        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    public static function maskPhone(string $phone): string
    {
        $digits = Member::normalizePhone($phone) ?? '';

        return str_repeat('X', max(0, strlen($digits) - 4)).substr($digits, -4);
    }
}
