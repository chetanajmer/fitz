<?php

namespace App\Livewire\PublicSite;

use App\Models\Gym;
use App\Models\Member;
use App\Services\MemberService;
use App\Tenancy\TenantManager;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Public self-registration form behind the gym's desk QR (/register/{token}).
 * Creates an active member with no membership — staff assign a plan and collect
 * payment afterwards, so the member cannot check in until then. The gym is a
 * locked, server-resolved model; the browser cannot change which gym it joins.
 */
class MemberRegistrationForm extends Component
{
    #[Locked]
    public Gym $gym;

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

    /** Honeypot — must stay empty. */
    public string $website = '';

    #[Locked]
    public ?string $memberCode = null;

    public function mount(Gym $gym): void
    {
        $this->gym = $gym;
    }

    public function submit(MemberService $members, TenantManager $tenant): void
    {
        abort_unless($this->gym->fresh()->isOperational(), 404);

        $data = $this->validate([
            'name' => 'required|string|min:2|max:120',
            'phone' => ['required', 'string', 'regex:/^[0-9+\-\s]{8,20}$/'],
            'email' => 'nullable|email|max:255',
            'gender' => 'nullable|in:male,female,other',
            'date_of_birth' => 'nullable|date|before:today|after:1900-01-01',
            'address' => 'nullable|string|max:255',
            'emergency_contact_name' => 'nullable|string|max:120',
            'emergency_contact_phone' => ['nullable', 'string', 'regex:/^[0-9+\-\s]{8,20}$/'],
            'fitness_goal' => 'nullable|string|max:255',
            'medical_notes' => 'nullable|string|max:2000',
        ], [
            'phone.regex' => 'Enter a valid phone number.',
            'emergency_contact_phone.regex' => 'Enter a valid phone number.',
        ]);

        if ($this->website !== '') {
            $this->memberCode = '—'; // silently drop bots

            return;
        }

        $key = 'public-register:'.$this->gym->id.':'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['name' => 'Too many registrations. Please try again later or ask the front desk.']);
        }

        // Count every attempt, including duplicates, so the "already registered" answer
        // cannot be used to test unlimited phone numbers against this gym.
        RateLimiter::hit($key, 3600);

        $alreadyRegistered = Member::withoutGlobalScopes()->where('gym_id', $this->gym->id)
            ->where('phone_digits', Member::normalizePhone($data['phone']))->exists();
        if ($alreadyRegistered) {
            throw ValidationException::withMessages(['phone' => 'This mobile number is already registered with '.$this->gym->name.'. Please contact the front desk.']);
        }

        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);
        $data['city'] = $this->gym->city;
        $data['joining_date'] = now()->toDateString();
        $data['status'] = 'active';

        $member = $tenant->runAs($this->gym, fn () => $members->create($data));

        $this->memberCode = $member->member_code;
    }

    public function render(): View
    {
        return view('livewire.public-site.member-registration-form');
    }
}
