<?php

namespace App\Services;

use App\Contracts\SmsSender;
use App\Exceptions\ApiException;
use App\Models\PhoneVerification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * OTPs that prove a gym admin or trainer owns a mobile number before it becomes their sign-in
 * number (users.login_phone). Signing in itself uses OtpLoginService.
 * Same rules as the member OTPs (config gymai.otp): cooldown, hourly limit, expiry, attempt limit.
 */
class StaffPhoneOtpService
{
    public const VERIFY = 'verify_phone';

    public function __construct(protected SmsSender $sms) {}

    /** Send a code to a 10-digit number. Only the newest code for the number and purpose is valid. */
    public function send(string $digits, string $purpose, ?string $ip = null): void
    {
        $cooldown = (int) config('gymai.otp.resend_cooldown_seconds', 30);
        $latest = PhoneVerification::where('phone_digits', $digits)->where('purpose', $purpose)->latest('id')->first();

        if ($latest && $latest->consumed_at === null && $latest->created_at->gt(now()->subSeconds($cooldown))) {
            throw new ApiException('Please wait '.max(1, $cooldown - (int) $latest->created_at->diffInSeconds(now())).' seconds before requesting another OTP.', 'OTP_COOLDOWN', 429);
        }

        $key = "staff-otp-send:{$purpose}:{$digits}";
        if (RateLimiter::tooManyAttempts($key, (int) config('gymai.otp.max_sends_per_hour', 5))) {
            throw new ApiException('Too many OTP requests. Please try again later.', 'OTP_LIMIT_REACHED', 429);
        }
        RateLimiter::hit($key, 3600);

        $length = (int) config('gymai.otp.length', 6);
        $code = str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
        $ttl = (int) config('gymai.otp.ttl_seconds', 300);

        DB::transaction(function () use ($digits, $purpose, $code, $ttl, $ip) {
            PhoneVerification::where('phone_digits', $digits)->where('purpose', $purpose)->whereNull('consumed_at')->update(['consumed_at' => now()]);

            $verification = new PhoneVerification;
            $verification->phone_digits = $digits;
            $verification->purpose = $purpose;
            $verification->code_hash = PhoneVerification::hashCode($code);
            $verification->expires_at = now()->addSeconds($ttl);
            $verification->ip_address = $ip;
            $verification->save();
        });

        $this->sms->send($digits, "{$code} is your Fitnix mobile verification OTP. It is valid for ".intdiv($ttl, 60).' minutes. Do not share it with anyone.');
    }

    /** Check a code; throws on a wrong, expired or used-up code. Wrong guesses count against the code. */
    public function verify(string $digits, string $purpose, string $code): void
    {
        $maxAttempts = (int) config('gymai.otp.max_attempts', 5);

        // The failure is returned (not thrown) so the attempt counter increment commits.
        $failure = DB::transaction(function () use ($digits, $purpose, $code, $maxAttempts): ?ApiException {
            $verification = PhoneVerification::where('phone_digits', $digits)->where('purpose', $purpose)->whereNull('consumed_at')->latest('id')->lockForUpdate()->first();

            if (! $verification) {
                return new ApiException('No OTP requested. Please request a new OTP.', 'OTP_NOT_FOUND', 422);
            }
            if ($verification->expires_at->isPast()) {
                return new ApiException('OTP has expired. Please request a new one.', 'OTP_EXPIRED', 422);
            }
            if ($verification->attempts >= $maxAttempts) {
                return new ApiException('Too many wrong attempts. Please request a new OTP.', 'OTP_ATTEMPTS_EXCEEDED', 429);
            }
            if (! $verification->matches($code)) {
                $verification->increment('attempts');

                return new ApiException('Incorrect OTP.', 'OTP_INVALID', 422);
            }

            $verification->consumed_at = now();
            $verification->save();

            return null;
        });

        if ($failure) {
            throw $failure;
        }
    }
}
