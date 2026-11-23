<?php

namespace App\Services;

use App\Contracts\SmsSender;
use App\Enums\MemberStatus;
use App\Exceptions\ApiException;
use App\Models\City;
use App\Models\Gym;
use App\Models\Member;
use App\Models\PhoneVerification;
use App\Models\Scopes\GymScope;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Member sign-up from the mobile app: verify the phone by OTP, then pick a city and a gym
 * in it, then send personal details. Creates a *pending* member the gym must approve:
 * unlike the desk QR, anyone anywhere could pick any gym.
 */
class MemberSignupService
{
    /** How long a verified phone may be used to finish the sign-up form. */
    public const TOKEN_TTL_MINUTES = 30;

    public function __construct(
        protected SmsSender $sms,
        protected MemberService $members,
        protected TenantManager $tenant,
    ) {}

    /** @return array{verification: PhoneVerification, code: string} */
    public function sendOtp(string $phone, ?string $ip = null): array
    {
        $digits = Member::normalizePhone($phone);
        $cooldown = (int) config('gymai.otp.resend_cooldown_seconds', 30);

        $latest = PhoneVerification::where('phone_digits', $digits)->latest('id')->first();
        if ($latest && $latest->consumed_at === null && $latest->created_at->gt(now()->subSeconds($cooldown))) {
            $wait = $cooldown - (int) $latest->created_at->diffInSeconds(now());

            throw new ApiException('Please wait before requesting another OTP.', 'OTP_COOLDOWN', 429, ['retry_after' => max(1, $wait)]);
        }

        $key = 'signup-otp-send:'.$digits;
        if (RateLimiter::tooManyAttempts($key, (int) config('gymai.otp.max_sends_per_hour', 5))) {
            throw new ApiException('Too many OTP requests. Please try again later.', 'OTP_LIMIT_REACHED', 429, ['retry_after' => RateLimiter::availableIn($key)]);
        }
        RateLimiter::hit($key, 3600);

        $length = (int) config('gymai.otp.length', 6);
        $code = str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
        $ttl = (int) config('gymai.otp.ttl_seconds', 300);

        $verification = DB::transaction(function () use ($digits, $code, $ttl, $ip) {
            // Only the newest OTP for a number is ever valid.
            PhoneVerification::where('phone_digits', $digits)->whereNull('consumed_at')->update(['consumed_at' => now()]);

            $verification = new PhoneVerification;
            $verification->phone_digits = $digits;
            $verification->code_hash = PhoneVerification::hashCode($code);
            $verification->expires_at = now()->addSeconds($ttl);
            $verification->ip_address = $ip;
            $verification->save();

            return $verification;
        });

        $this->sms->send($digits, "{$code} is your Gymai sign-up OTP. It is valid for ".intdiv($ttl, 60).' minutes. Do not share it with anyone.');

        return ['verification' => $verification, 'code' => $code];
    }

    /**
     * Check the OTP and return a single-use registration token. Wrong codes count against
     * the attempt limit (the failure is returned from the transaction so the count commits).
     */
    public function verifyOtp(string $phone, string $code): string
    {
        $digits = Member::normalizePhone($phone);
        $maxAttempts = (int) config('gymai.otp.max_attempts', 5);
        $token = Str::random(64);

        $failure = DB::transaction(function () use ($digits, $code, $maxAttempts, $token): ?ApiException {
            $verification = PhoneVerification::where('phone_digits', $digits)->whereNull('consumed_at')->latest('id')->lockForUpdate()->first();

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

                return new ApiException('Incorrect OTP.', 'OTP_INVALID', 422, ['attempts_left' => max(0, $maxAttempts - $verification->attempts)]);
            }

            $verification->consumed_at = now();
            $verification->token_hash = PhoneVerification::hashToken($token);
            $verification->token_expires_at = now()->addMinutes(self::TOKEN_TTL_MINUTES);
            $verification->save();

            return null;
        });

        if ($failure) {
            throw $failure;
        }

        return $token;
    }

    /**
     * Create a pending member at the chosen gym for the verified phone.
     *
     * @param  array<string, mixed>  $details  name, email, gender, date_of_birth, address, emergency contact, fitness_goal, medical_notes
     */
    public function register(string $token, int $cityId, int $gymId, array $details): Member
    {
        $city = City::active()->find($cityId);
        if (! $city) {
            throw new ApiException('This city is not available on Gymai.', 'CITY_NOT_FOUND', 404);
        }

        $gym = Gym::operational()->where('city_id', $city->id)->find($gymId);
        if (! $gym) {
            throw new ApiException("This gym is not available in {$city->name}.", 'GYM_NOT_FOUND', 404);
        }

        return DB::transaction(function () use ($token, $city, $gym, $details) {
            $verification = PhoneVerification::where('token_hash', PhoneVerification::hashToken($token))->lockForUpdate()->first();

            if (! $verification || $verification->token_used_at !== null || $verification->token_expires_at?->isPast()) {
                throw new ApiException('Your phone verification has expired. Please verify your number again.', 'REGISTRATION_TOKEN_INVALID', 422);
            }

            $alreadyRegistered = Member::withoutGlobalScope(GymScope::class)
                ->where('gym_id', $gym->id)->where('phone_digits', $verification->phone_digits)->exists();
            if ($alreadyRegistered) {
                throw new ApiException("This mobile number is already registered with {$gym->name}.", 'ALREADY_REGISTERED', 409);
            }

            $verification->token_used_at = now();
            $verification->save();

            $data = collect($details)->only([
                'name', 'email', 'gender', 'date_of_birth', 'address',
                'emergency_contact_name', 'emergency_contact_phone', 'fitness_goal', 'medical_notes',
            ])->map(fn ($v) => $v === '' ? null : $v)->all();

            $data['phone'] = $verification->phone_digits;
            $data['city'] = $city->name;
            $data['joining_date'] = now()->toDateString();
            $data['status'] = MemberStatus::Pending->value;

            return $this->tenant->runAs($gym, fn () => $this->members->create($data));
        });
    }
}
