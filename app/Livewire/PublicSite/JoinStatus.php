<?php

namespace App\Livewire\PublicSite;

use App\Exceptions\ApiException;
use App\Models\Member;
use App\Services\MemberSignupService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * "Check my registration status" for people who signed up on the website or app.
 * The phone is verified by OTP first, so a status is only shown to the number's owner.
 */
#[Layout('components.layouts.guest', ['wide' => true])]
#[Title('Registration status')]
class JoinStatus extends Component
{
    /** 1 phone · 2 OTP · 3 results */
    #[Locked]
    public int $step = 1;

    public string $phone = '';

    public string $otp = '';

    /** Set only after a correct OTP; results are read for this number, never for the editable $phone. */
    #[Locked]
    public ?string $verifiedPhone = null;

    public function sendOtp(MemberSignupService $signup): void
    {
        $this->validate(['phone' => ['required', 'regex:/^\+?[0-9\s\-]{10,15}$/']], ['phone.regex' => 'Enter a valid 10-digit mobile number.']);

        $key = 'join-status-otp:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('phone', 'Too many attempts. Please try again in a few minutes.');

            return;
        }
        RateLimiter::hit($key, 600);

        try {
            $signup->sendOtp($this->phone, request()->ip());
        } catch (ApiException $e) {
            $this->addError('phone', $e->getMessage());

            return;
        }

        $this->otp = '';
        $this->step = 2;
    }

    public function verify(MemberSignupService $signup): void
    {
        abort_unless($this->step === 2, 404);

        $this->validate(['otp' => ['required', 'digits:'.(int) config('gymai.otp.length', 6)]], [], ['otp' => 'OTP']);

        try {
            $signup->verifyOtp($this->phone, $this->otp);
        } catch (ApiException $e) {
            $this->addError('otp', $e->getMessage());

            return;
        }

        $this->verifiedPhone = Member::normalizePhone($this->phone);
        $this->step = 3;
    }

    public function startOver(): void
    {
        $this->reset();
    }

    public function render(MemberSignupService $signup): View
    {
        $registrations = $this->step === 3 && $this->verifiedPhone ? $signup->registrationsFor($this->verifiedPhone) : collect();

        return view('livewire.public-site.join-status', [
            'registrations' => $registrations->map(fn (Member $m) => [
                'gym' => $m->gym?->name,
                'member_code' => $m->member_code,
                'status' => $signup->statusOf($m),
                'date' => $m->created_at,
            ]),
        ]);
    }
}
