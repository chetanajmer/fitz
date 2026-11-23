<?php

namespace App\Livewire\PublicSite;

use App\Exceptions\ApiException;
use App\Models\City;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Scopes\GymScope;
use App\Services\MemberSignupService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Website sign-up for new members, one step at a time: city → gym in that city → details →
 * phone OTP. Same rules as the app sign-up (MemberSignupService): the member is created
 * pending and the gym approves them. Members at the gym's desk use the QR form instead.
 */
#[Layout('components.layouts.guest', ['wide' => true])]
#[Title('Join a gym')]
class MemberSignup extends Component
{
    /** 1 city · 2 gym · 3 details · 4 verify phone · 5 done */
    #[Locked]
    public int $step = 1;

    #[Locked]
    public ?int $cityId = null;

    #[Locked]
    public ?int $gymId = null;

    #[Locked]
    public ?string $memberCode = null;

    public string $citySearch = '';

    public string $gymSearch = '';

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $gender = '';

    public string $date_of_birth = '';

    public string $address = '';

    public string $emergency_contact_name = '';

    public string $emergency_contact_phone = '';

    public string $fitness_goal = '';

    public string $medical_notes = '';

    public string $otp = '';

    /** Honeypot — must stay empty. */
    public string $website = '';

    public function selectCity(int $cityId): void
    {
        $this->cityId = City::active()->findOrFail($cityId)->id;
        $this->gymId = null;
        $this->gymSearch = '';
        $this->step = 2;
    }

    public function selectGym(int $gymId): void
    {
        abort_unless($this->cityId, 404);

        $this->gymId = Gym::operational()->where('city_id', $this->cityId)->findOrFail($gymId)->id;
        $this->step = 3;
    }

    public function back(): void
    {
        $this->resetValidation();
        $this->step = max(1, min($this->step, 4) - 1);
    }

    /** Validate the details and text an OTP to the phone. */
    public function sendOtp(MemberSignupService $signup): void
    {
        abort_unless($this->cityId && $this->gymId, 404);

        $this->validate($this->detailRules(), [
            'phone.regex' => 'Enter a valid 10-digit mobile number.',
            'emergency_contact_phone.regex' => 'Enter a valid phone number.',
        ]);

        if ($this->website !== '') {
            return; // silently drop bots
        }

        $alreadyRegistered = Member::withoutGlobalScope(GymScope::class)->where('gym_id', $this->gymId)
            ->where('phone_digits', Member::normalizePhone($this->phone))->exists();
        if ($alreadyRegistered) {
            $this->addError('phone', 'This mobile number is already registered with '.$this->gym()->name.'. Please log in to the app or contact the gym.');

            return;
        }

        $key = 'web-signup-otp:'.request()->ip();
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
        $this->step = 4;
    }

    /** Check the OTP, then create the pending member. */
    public function verify(MemberSignupService $signup): void
    {
        abort_unless($this->step === 4 && $this->cityId && $this->gymId, 404);

        $this->validate(['otp' => ['required', 'digits:'.(int) config('gymai.otp.length', 6)]], [], ['otp' => 'OTP']);
        $this->validate($this->detailRules());

        try {
            $token = $signup->verifyOtp($this->phone, $this->otp);
            $member = $signup->register($token, $this->cityId, $this->gymId, $this->only([
                'name', 'email', 'gender', 'date_of_birth', 'address',
                'emergency_contact_name', 'emergency_contact_phone', 'fitness_goal', 'medical_notes',
            ]));
        } catch (ApiException $e) {
            $this->addError('otp', $e->getMessage());

            return;
        }

        $this->memberCode = $member->member_code;
        $this->step = 5;
    }

    /** @return array<string, mixed> */
    protected function detailRules(): array
    {
        return [
            'name' => 'required|string|min:2|max:120',
            'phone' => ['required', 'regex:/^\+?[0-9\s\-]{10,15}$/'],
            'email' => 'nullable|email|max:255',
            'gender' => 'nullable|in:male,female,other',
            'date_of_birth' => 'nullable|date|before:today|after:1900-01-01',
            'address' => 'nullable|string|max:255',
            'emergency_contact_name' => 'nullable|string|max:120',
            'emergency_contact_phone' => ['nullable', 'string', 'regex:/^[0-9+\-\s]{8,20}$/'],
            'fitness_goal' => 'nullable|string|max:255',
            'medical_notes' => 'nullable|string|max:2000',
        ];
    }

    protected function city(): ?City
    {
        return $this->cityId ? City::find($this->cityId) : null;
    }

    protected function gym(): ?Gym
    {
        return $this->gymId ? Gym::find($this->gymId) : null;
    }

    public function render(): View
    {
        $search = trim($this->gymSearch);
        $cityLike = '%'.addcslashes(trim($this->citySearch), '%_\\').'%';

        return view('livewire.public-site.member-signup', [
            'cities' => $this->step === 1
                ? City::active()->withCount(['gyms' => fn ($q) => $q->operational()])
                    ->when(trim($this->citySearch) !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', $cityLike)->orWhere('state', 'like', $cityLike)))
                    ->orderBy('name')->get()
                : collect(),
            'gyms' => $this->step === 2 && $this->cityId
                ? Gym::operational()->where('city_id', $this->cityId)
                    ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.addcslashes($search, '%_\\').'%'))
                    ->orderBy('name')->limit(30)->get()
                : collect(),
            'city' => $this->city(),
            'gym' => $this->gym(),
        ]);
    }
}
