<?php

namespace App\Services;

use App\Contracts\SmsSender;
use App\Enums\Role;
use App\Exceptions\ApiException;
use App\Mail\LoginCodeMail;
use App\Models\LoginCode;
use App\Models\Member;
use App\Models\StaffDevice;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Sign-in for the staff mobile app (gym admins and trainers): email or verified mobile + password, or a
 * one-time code sent to the email or the verified mobile. Codes are stored as login_codes with their own
 * account marker, so they never mix with website sign-in codes. Until a correct code is entered every email
 * or number gets the same answer, and a code is only delivered when a staff account exists.
 * When the credentials match several staff accounts, a single-use selection token (5 minutes) lets the app
 * finish the sign-in for the account the person picks.
 */
class StaffAuthService
{
    /** login_codes.account for staff app codes. */
    public const CODE_ACCOUNT = 'staff-app';

    public const SELECTION_TTL_SECONDS = 300;

    public function __construct(protected SmsSender $sms) {}

    public static function channel(string $identifier): string
    {
        return str_contains($identifier, '@') ? 'email' : 'sms';
    }

    public static function destination(string $identifier): string
    {
        return self::channel($identifier) === 'email' ? Str::lower(trim($identifier)) : (string) Member::normalizePhone($identifier);
    }

    /**
     * Active staff accounts of active gyms that sign in with this email or verified mobile.
     *
     * @return Collection<int, User>
     */
    public function staffAccounts(string $identifier): Collection
    {
        $destination = self::destination($identifier);

        if ($destination === '') {
            return new Collection;
        }

        return User::role([Role::GymAdmin->value, Role::Trainer->value])->with('gym')
            ->where(self::channel($identifier) === 'email' ? 'email' : 'login_phone', $destination)
            ->where('status', 'active')
            ->whereHas('gym', fn ($q) => $q->operational())
            ->orderBy('id')
            ->get();
    }

    /**
     * Password sign-in. Returns every staff account the credentials open (usually one).
     *
     * @return Collection<int, User>
     */
    public function attemptPassword(string $identifier, string $password, ?string $ip): Collection
    {
        $key = 'staff-app-login:'.self::destination($identifier).'|'.$ip;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw new ApiException('Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.', 'TOO_MANY_REQUESTS', 429, ['retry_after' => RateLimiter::availableIn($key)]);
        }

        $accounts = $this->staffAccounts($identifier)->filter(fn (User $user) => Hash::check($password, $user->password))->values();

        if ($accounts->isEmpty()) {
            RateLimiter::hit($key, 60);

            throw new ApiException('These credentials do not match our records.', 'INVALID_CREDENTIALS', 422);
        }

        RateLimiter::clear($key);

        return $accounts;
    }

    /** Send a sign-in code. Only the newest code is valid; nothing is delivered when no staff account exists. */
    public function sendCode(string $identifier, ?string $ip = null): void
    {
        $channel = self::channel($identifier);
        $destination = self::destination($identifier);
        $cooldown = (int) config('gymai.otp.resend_cooldown_seconds', 30);
        $latest = LoginCode::where('destination', $destination)->where('account', self::CODE_ACCOUNT)->latest('id')->first();

        if ($latest && $latest->consumed_at === null && $latest->created_at->gt(now()->subSeconds($cooldown))) {
            $wait = max(1, $cooldown - (int) $latest->created_at->diffInSeconds(now()));

            throw new ApiException("Please wait {$wait} seconds before requesting another code.", 'OTP_TOO_MANY', 429, ['retry_after' => $wait]);
        }

        $key = 'staff-app-otp-send:'.$destination;
        if (RateLimiter::tooManyAttempts($key, (int) config('gymai.otp.max_sends_per_hour', 5))) {
            throw new ApiException('Too many code requests. Please try again later.', 'OTP_TOO_MANY', 429, ['retry_after' => RateLimiter::availableIn($key)]);
        }
        RateLimiter::hit($key, 3600);

        $length = (int) config('gymai.otp.length', 6);
        $code = str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
        $ttl = (int) config('gymai.otp.ttl_seconds', 300);

        DB::transaction(function () use ($channel, $destination, $code, $ip, $ttl) {
            LoginCode::where('destination', $destination)->where('account', self::CODE_ACCOUNT)->whereNull('consumed_at')->update(['consumed_at' => now()]);
            LoginCode::create([
                'channel' => $channel,
                'destination' => $destination,
                'account' => self::CODE_ACCOUNT,
                'code_hash' => LoginCode::hashCode($code),
                'expires_at' => now()->addSeconds($ttl),
                'ip_address' => $ip,
            ]);
        });

        if ($this->staffAccounts($identifier)->isEmpty()) {
            return;
        }

        $minutes = intdiv($ttl, 60);
        $channel === 'email'
            ? Mail::to($destination)->send(new LoginCodeMail($code, $minutes))
            : $this->sms->send($destination, "{$code} is your Fitnix sign-in OTP. It is valid for {$minutes} minutes. Do not share it with anyone.");
    }

    /** Check the newest code; throws on a wrong, expired or used-up one. */
    public function verifyCode(string $identifier, string $code): void
    {
        $destination = self::destination($identifier);
        $maxAttempts = (int) config('gymai.otp.max_attempts', 5);

        // The failure is returned (not thrown) so the attempt counter increment commits.
        $failure = DB::transaction(function () use ($destination, $code, $maxAttempts): ?ApiException {
            $login = LoginCode::where('destination', $destination)->where('account', self::CODE_ACCOUNT)->whereNull('consumed_at')->latest('id')->lockForUpdate()->first();

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
    }

    // ---- Choosing an account ----------------------------------------------------------------------

    /** Account choices shown to the person: key "staff:{id}", label "Iron Den · Trainer". */
    public function choices(Collection $accounts): array
    {
        return $accounts->map(fn (User $user) => [
            'key' => 'staff:'.$user->id,
            'label' => $user->gym->name.' · '.($user->isGymAdmin() ? 'Gym admin' : 'Trainer'),
        ])->values()->all();
    }

    /** A single-use token (5 minutes) that lets the app finish the sign-in for one of these accounts. */
    public function issueSelectionToken(Collection $accounts): string
    {
        $token = Str::random(64);
        Cache::put($this->selectionKey($token), $accounts->pluck('id')->all(), self::SELECTION_TTL_SECONDS);

        return $token;
    }

    /** The chosen account of a selection token. The token is used up once an account is signed in. */
    public function redeemSelection(string $token, string $account): User
    {
        $ids = Cache::get($this->selectionKey($token));

        if (! is_array($ids)) {
            throw new ApiException('Your sign-in has expired. Please sign in again.', 'SELECTION_EXPIRED', 422);
        }

        $id = str_starts_with($account, 'staff:') ? (int) substr($account, 6) : 0;

        if (! in_array($id, $ids, true)) {
            throw new ApiException('Choose one of the accounts offered.', 'ACCOUNT_NOT_FOUND', 422);
        }

        $user = User::with('gym')->find($id);

        if (! $user || ! $user->isActive() || ! ($user->isGymAdmin() || $user->isTrainer()) || ! $user->gym?->isOperational()) {
            throw new ApiException('This account is inactive. Please contact your gym.', 'ACCOUNT_DISABLED', 403);
        }

        Cache::forget($this->selectionKey($token));

        return $user;
    }

    protected function selectionKey(string $token): string
    {
        return 'staff-app-selection:'.hash('sha256', $token);
    }

    // ---- Tokens -------------------------------------------------------------------------------------

    /** A Sanctum bearer token for the staff app. */
    public function issueToken(User $user, ?string $deviceName = null): string
    {
        $user->forceFill(['last_login_at' => now()])->save();

        return $user->createToken($deviceName ?: 'staff-app', ['staff'])->plainTextToken;
    }

    /** Sign out this token and forget the phones registered with it (plus an explicitly given push token). */
    public function logout(User $user, ?string $deviceToken = null): void
    {
        $tokenId = $user->currentAccessToken()?->id;

        StaffDevice::where('user_id', $user->id)
            ->where(fn ($q) => $q->when($tokenId, fn ($w) => $w->where('access_token_id', $tokenId))
                ->when($deviceToken, fn ($w) => $w->orWhere('token', $deviceToken))
                ->when(! $tokenId && ! $deviceToken, fn ($w) => $w->whereRaw('1 = 0')))
            ->delete();

        $user->currentAccessToken()?->delete();
    }
}
