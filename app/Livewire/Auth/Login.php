<?php

namespace App\Livewire\Auth;

use App\Exceptions\ApiException;
use App\Models\Member;
use App\Models\User;
use App\Services\MemberOtpService;
use App\Services\MemberSignupService;
use App\Services\OtpLoginService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Sign in with a password (email, or a staff member's verified mobile), or with a one-time code
 * sent to an email address or a mobile number (members and gym staff).
 */
#[Layout('components.layouts.guest')]
#[Title('Sign in')]
class Login extends Component
{
    /** Session: the email or number whose code was just checked, while the person picks an account. */
    protected const VERIFIED = 'otp_login_verified';

    /** password | otp (?mode=otp opens the OTP tab, e.g. from "Already a member? Sign in"). */
    #[Url(except: 'password')]
    public string $mode = 'password';

    /** Email, or the verified sign-in mobile of a gym admin / trainer. */
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    /** OTP: email address or mobile number. */
    public string $identifier = '';

    /** @var list<array{key: string, label: string}> */
    public array $accounts = [];

    public string $account = '';

    public bool $codeSent = false;

    public string $code = '';

    /**
     * After an OTP check for a number that cannot sign in yet: its registrations.
     *
     * @var list<array{gym: string, status: string, member_code: ?string, registered_on: string}>
     */
    public array $statuses = [];

    public function login()
    {
        $this->validate(['email' => 'required|string|max:255', 'password' => 'required|string'], [], ['email' => 'email or mobile number']);

        $key = Str::lower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.',
            ]);
        }

        $credentials = str_contains($this->email, '@')
            ? ['email' => Str::lower($this->email)]
            : ['login_phone' => Member::normalizePhone($this->email) ?? ''];

        if (! Auth::attempt($credentials + ['password' => $this->password], $this->remember)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        RateLimiter::clear($key);

        return $this->completeLogin(Auth::user(), 'email');
    }

    /** Step 1: send a code. The answer is the same whether or not an account exists. */
    public function sendCode(OtpLoginService $otp): void
    {
        $this->identifier = trim($this->identifier);
        $byEmail = OtpLoginService::channel($this->identifier) === 'email';

        if (! $byEmail) {
            $this->identifier = preg_replace('/[\s()-]/', '', $this->identifier);
        }

        $this->validate(['identifier' => $byEmail ? ['required', 'email', 'max:255'] : ['required', 'regex:/^(\+?91|0)?[6-9]\d{9}$/']],
            ['identifier.regex' => 'Enter a valid email address or 10-digit mobile number.'], ['identifier' => 'email or mobile number']);

        $key = 'web-login-otp:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->addError($this->codeSent ? 'code' : 'identifier', 'Too many attempts. Please try again in a few minutes.');

            return;
        }
        RateLimiter::hit($key, 600);

        try {
            $otp->sendCode($this->identifier, request()->ip());
        } catch (ApiException $e) {
            $this->addError($this->codeSent ? 'code' : 'identifier', $e->getMessage());

            return;
        }

        $this->codeSent = true;
        $this->code = '';
    }

    /** Step 2: check the code, then sign in, show the registration status, or ask which account. */
    public function verifyCode(OtpLoginService $otp)
    {
        $this->validate(['code' => 'required|digits:'.config('gymai.otp.length', 6)], [], ['code' => 'code']);

        try {
            $otp->verifyCode($this->identifier, $this->code);
        } catch (ApiException $e) {
            $this->addError('code', $e->getMessage());

            return;
        }

        $this->accounts = $otp->accounts($this->identifier);

        if ($this->accounts === []) {
            $this->statuses = $otp->registrations($this->identifier)->map(fn (Member $m) => [
                'gym' => $m->gym->name,
                'status' => app(MemberSignupService::class)->statusOf($m),
                'member_code' => $m->member_code,
                'registered_on' => $m->created_at->format('d M Y'),
            ])->all();

            return;
        }

        // Kept in the session, not the page: the browser can neither edit nor replay it.
        session([self::VERIFIED => ['identifier' => $this->identifier, 'expires' => now()->addMinutes(10)->timestamp]]);

        if (count($this->accounts) === 1) {
            $this->account = $this->accounts[0]['key'];

            return $this->chooseAccount($otp);
        }
    }

    /** Step 3 (several accounts): sign in to the chosen one. */
    public function chooseAccount(OtpLoginService $otp)
    {
        $verified = session(self::VERIFIED);

        if (! $verified || $verified['expires'] < now()->timestamp) {
            $this->addError('account', 'Your code check has expired. Please request a new code.');

            return;
        }

        try {
            $user = $otp->userFor($verified['identifier'], $this->account);
        } catch (ApiException $e) {
            $this->addError('account', $e->getMessage());

            return;
        }

        session()->forget(self::VERIFIED);
        Auth::login($user, $this->remember);

        // A single account signs in straight from the code field.
        return $this->completeLogin($user, count($this->accounts) === 1 ? 'code' : 'account');
    }

    public function changeIdentifier(): void
    {
        session()->forget(self::VERIFIED);
        $this->reset('accounts', 'account', 'codeSent', 'code', 'statuses');
        $this->resetValidation();
    }

    /** Where the code went, partly hidden: XXXXXX3210 or ra•••@gmail.com. */
    public function sentTo(): string
    {
        if (OtpLoginService::channel($this->identifier) === 'sms') {
            return MemberOtpService::maskPhone($this->identifier);
        }

        [$name, $domain] = explode('@', OtpLoginService::destination($this->identifier), 2);

        return Str::substr($name, 0, 2).'•••@'.$domain;
    }

    protected function completeLogin(User $user, string $errorField)
    {
        if (! $user->isActive()) {
            Auth::logout();
            throw ValidationException::withMessages([$errorField => 'Your account is inactive. Please contact your gym.']);
        }

        session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return $this->redirect(session()->pull('url.intended', $user->homeRoute()));
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
