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

    /** Step 1: find the identifier's accounts (the person picks one when there are several), then send the code. */
    public function sendCode(OtpLoginService $otp): void
    {
        $this->identifier = trim($this->identifier);
        $byEmail = OtpLoginService::channel($this->identifier) === 'email';

        if (! $byEmail) {
            $this->identifier = preg_replace('/[\s()-]/', '', $this->identifier);
        }

        $this->validate(['identifier' => $byEmail ? ['required', 'email', 'max:255'] : ['required', 'regex:/^(\+?91|0)?[6-9]\d{9}$/']],
            ['identifier.regex' => 'Enter a valid email address or 10-digit mobile number.'], ['identifier' => 'email or mobile number']);

        $this->accounts = $otp->accounts($this->identifier);

        if ($this->accounts === [] && $otp->registrations($this->identifier)->isEmpty()) {
            $this->addError('identifier', 'No active account uses this '.($byEmail ? 'email' : 'mobile number').'.'.($byEmail ? '' : ' Gym staff: verify your mobile under My account first.'));

            return;
        }

        if ($this->accounts === []) {
            // A registration that cannot sign in yet: the code proves it is theirs, then we show its status.
            $this->account = OtpLoginService::STATUS;
        } elseif (count($this->accounts) === 1) {
            $this->account = $this->accounts[0]['key'];
        }

        if ($this->account !== OtpLoginService::STATUS && ! in_array($this->account, array_column($this->accounts, 'key'), true)) {
            $this->account = '';
            $this->addError('account', 'More than one account uses this '.($byEmail ? 'email' : 'number').'. Choose where to sign in.');

            return;
        }

        try {
            $otp->sendCode($this->identifier, $this->account, request()->ip());
        } catch (ApiException $e) {
            $this->addError('identifier', $e->getMessage());

            return;
        }

        $this->codeSent = true;
        $this->code = '';
    }

    /** Step 2: check the code and sign in. */
    public function verifyCode(OtpLoginService $otp)
    {
        $this->validate(['code' => 'required|digits:'.config('gymai.otp.length', 6)], [], ['code' => 'code']);

        try {
            if ($this->account === OtpLoginService::STATUS) {
                $this->statuses = $otp->verifyStatusCode($this->identifier, $this->code)->map(fn (Member $m) => [
                    'gym' => $m->gym->name,
                    'status' => app(MemberSignupService::class)->statusOf($m),
                    'member_code' => $m->member_code,
                    'registered_on' => $m->created_at->format('d M Y'),
                ])->all();

                return;
            }

            $user = $otp->verifyCode($this->identifier, $this->account, $this->code);
        } catch (ApiException $e) {
            $this->addError('code', $e->getMessage());

            return;
        }

        Auth::login($user, $this->remember);

        return $this->completeLogin($user, 'code');
    }

    public function changeIdentifier(): void
    {
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
