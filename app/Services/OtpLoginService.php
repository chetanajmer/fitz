<?php

namespace App\Services;

use App\Contracts\SmsSender;
use App\Enums\MemberStatus;
use App\Enums\Role;
use App\Exceptions\ApiException;
use App\Mail\LoginCodeMail;
use App\Models\LoginCode;
use App\Models\Member;
use App\Models\Scopes\GymScope;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Website sign-in with a one-time code sent to an email address or a mobile number, for members
 * (any gym they belong to) and gym staff (their account email, or their verified sign-in mobile).
 * One email or number can belong to several accounts (two gyms, staff + member): the person picks one.
 * Same limits as the app OTPs (config gymai.otp). The Super Admin signs in with a password only.
 */
class OtpLoginService
{
    public function __construct(
        protected SmsSender $sms,
        protected MemberService $members,
        protected TenantManager $tenant,
    ) {}

    /** "email" for an address, "sms" for anything else (a mobile number). */
    public static function channel(string $identifier): string
    {
        return str_contains($identifier, '@') ? 'email' : 'sms';
    }

    /** Lower-case email, or the 10-digit mobile. */
    public static function destination(string $identifier): string
    {
        return self::channel($identifier) === 'email' ? Str::lower(trim($identifier)) : (string) Member::normalizePhone($identifier);
    }

    /**
     * Accounts this email or number can sign in to. key = "staff:{user id}" or "member:{member id}".
     *
     * @return list<array{key: string, label: string}>
     */
    public function accounts(string $identifier): array
    {
        $destination = self::destination($identifier);
        $byEmail = self::channel($identifier) === 'email';

        $staff = User::role([Role::GymAdmin->value, Role::Trainer->value])->with('gym')
            ->where($byEmail ? 'email' : 'login_phone', $destination)->where('status', 'active')
            ->whereHas('gym', fn ($q) => $q->operational())->get()
            ->map(fn (User $u) => ['key' => 'staff:'.$u->id, 'label' => $u->gym->name.' · '.($u->isGymAdmin() ? 'Gym admin' : 'Trainer')]);

        $members = Member::withoutGlobalScope(GymScope::class)->with('gym')
            ->when($byEmail, fn ($q) => $q->whereRaw('LOWER(email) = ?', [$destination]), fn ($q) => $q->where('phone_digits', $destination))
            ->where('status', MemberStatus::Active->value)
            ->whereHas('gym', fn ($q) => $q->operational())->get()
            ->map(fn (Member $m) => ['key' => 'member:'.$m->id, 'label' => $m->gym->name.' · Member']);

        return $staff->concat($members)->values()->all();
    }

    /** Send a code for one of the identifier's accounts. Only the newest code is valid. */
    public function sendCode(string $identifier, string $account, ?string $ip = null): void
    {
        $this->assertAccount($identifier, $account);
        $channel = self::channel($identifier);
        $destination = self::destination($identifier);

        $cooldown = (int) config('gymai.otp.resend_cooldown_seconds', 30);
        $latest = LoginCode::where('destination', $destination)->where('account', $account)->latest('id')->first();
        if ($latest && $latest->consumed_at === null && $latest->created_at->gt(now()->subSeconds($cooldown))) {
            throw new ApiException('Please wait '.max(1, $cooldown - (int) $latest->created_at->diffInSeconds(now())).' seconds before requesting another code.', 'OTP_COOLDOWN', 429);
        }

        $key = 'login-otp-send:'.$destination;
        if (RateLimiter::tooManyAttempts($key, (int) config('gymai.otp.max_sends_per_hour', 5))) {
            throw new ApiException('Too many code requests. Please try again later.', 'OTP_LIMIT_REACHED', 429);
        }
        RateLimiter::hit($key, 3600);

        $length = (int) config('gymai.otp.length', 6);
        $code = str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
        $minutes = intdiv((int) config('gymai.otp.ttl_seconds', 300), 60);

        DB::transaction(function () use ($channel, $destination, $account, $code, $ip) {
            LoginCode::where('destination', $destination)->where('account', $account)->whereNull('consumed_at')->update(['consumed_at' => now()]);
            LoginCode::create([
                'channel' => $channel,
                'destination' => $destination,
                'account' => $account,
                'code_hash' => LoginCode::hashCode($code),
                'expires_at' => now()->addSeconds((int) config('gymai.otp.ttl_seconds', 300)),
                'ip_address' => $ip,
            ]);
        });

        $channel === 'email'
            ? Mail::to($destination)->send(new LoginCodeMail($code, $minutes))
            : $this->sms->send($destination, "{$code} is your Gymai sign-in OTP. It is valid for {$minutes} minutes. Do not share it with anyone.");
    }

    /** Check the code and return the user to sign in (a member's portal account is created on first use). */
    public function verifyCode(string $identifier, string $account, string $code): User
    {
        $this->assertAccount($identifier, $account);
        $destination = self::destination($identifier);
        $maxAttempts = (int) config('gymai.otp.max_attempts', 5);

        // The failure is returned (not thrown) so the attempt counter increment commits.
        $failure = DB::transaction(function () use ($destination, $account, $code, $maxAttempts): ?ApiException {
            $login = LoginCode::where('destination', $destination)->where('account', $account)->whereNull('consumed_at')->latest('id')->lockForUpdate()->first();

            if (! $login) {
                return new ApiException('No code requested. Please request a new one.', 'OTP_NOT_FOUND', 422);
            }
            if ($login->expires_at->isPast()) {
                return new ApiException('The code has expired. Please request a new one.', 'OTP_EXPIRED', 422);
            }
            if ($login->attempts >= $maxAttempts) {
                return new ApiException('Too many wrong attempts. Please request a new code.', 'OTP_ATTEMPTS_EXCEEDED', 429);
            }
            if (! $login->matches($code)) {
                $login->increment('attempts');

                return new ApiException('Incorrect code.', 'OTP_INVALID', 422);
            }

            $login->consumed_at = now();
            $login->save();

            return null;
        });

        if ($failure) {
            throw $failure;
        }

        [$type, $id] = explode(':', $account);

        if ($type === 'staff') {
            return User::findOrFail((int) $id);
        }

        $member = Member::withoutGlobalScope(GymScope::class)->with('gym')->findOrFail((int) $id);

        return $this->tenant->runAs($member->gym, fn () => $this->members->portalUserFor($member));
    }

    protected function assertAccount(string $identifier, string $account): void
    {
        if (! in_array($account, array_column($this->accounts($identifier), 'key'), true)) {
            throw new ApiException('No active account found for this email or mobile number.', 'ACCOUNT_NOT_FOUND', 404);
        }
    }
}
