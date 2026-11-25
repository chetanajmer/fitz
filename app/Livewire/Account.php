<?php

namespace App\Livewire;

use App\Exceptions\ApiException;
use App\Models\Member;
use App\Models\User;
use App\Services\StaffPhoneOtpService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Profile + password page shared by every role; the layout follows the user's role. */
#[Title('My account')]
class Account extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    /** Gym staff: the mobile number being verified for signing in. */
    public string $loginPhone = '';

    public bool $phoneCodeSent = false;

    public string $phoneCode = '';

    public function mount(): void
    {
        $user = Auth::user();
        $this->name = $user->name;
        $this->email = (string) $user->email;
        $this->phone = (string) $user->phone;
    }

    public function saveProfile(): void
    {
        $user = Auth::user();

        $data = $this->validate([
            'name' => 'required|string|max:120',
            // Members who signed in with an OTP may have no email.
            'email' => [$user->isMember() ? 'nullable' : 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => 'nullable|string|max:20',
        ]);

        $user->update(['name' => $data['name'], 'email' => $data['email'] ? strtolower($data['email']) : null, 'phone' => $data['phone'] ?: null]);

        $this->dispatch('notify', message: 'Profile updated.');
    }

    public function savePassword(): void
    {
        $this->validate([
            'current_password' => 'required|current_password',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        Auth::user()->update(['password' => Hash::make($this->password)]);
        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('notify', message: 'Password changed.');
    }

    /** Gym staff: send an OTP to the mobile number they want to sign in with. */
    public function sendPhoneCode(StaffPhoneOtpService $otps): void
    {
        $user = $this->staffUser();
        $this->loginPhone = preg_replace('/[\s()-]/', '', $this->loginPhone);
        $this->validate(['loginPhone' => ['required', 'regex:/^(\+?91|0)?[6-9]\d{9}$/']], ['loginPhone.regex' => 'Enter a valid 10-digit Indian mobile number.']);
        $digits = Member::normalizePhone($this->loginPhone);

        if (User::where('login_phone', $digits)->whereKeyNot($user->id)->exists()) {
            $this->addError('loginPhone', 'Another Gymai account already signs in with this number.');

            return;
        }

        try {
            $otps->send($digits, StaffPhoneOtpService::VERIFY, request()->ip());
        } catch (ApiException $e) {
            $this->addError('loginPhone', $e->getMessage());

            return;
        }

        $this->phoneCodeSent = true;
    }

    public function verifyPhoneCode(StaffPhoneOtpService $otps): void
    {
        $user = $this->staffUser();
        $this->validate(['phoneCode' => 'required|digits:'.config('gymai.otp.length', 6)], [], ['phoneCode' => 'OTP']);
        $digits = Member::normalizePhone($this->loginPhone);

        try {
            $otps->verify($digits, StaffPhoneOtpService::VERIFY, $this->phoneCode);
        } catch (ApiException $e) {
            $this->addError('phoneCode', $e->getMessage());

            return;
        }

        if (User::where('login_phone', $digits)->whereKeyNot($user->id)->exists()) {
            $this->addError('phoneCode', 'Another Gymai account already signs in with this number.');

            return;
        }

        $user->forceFill(['login_phone' => $digits])->save();
        $this->reset('loginPhone', 'phoneCodeSent', 'phoneCode');
        $this->dispatch('notify', message: 'Mobile verified. You can now sign in with it.');
    }

    public function removeLoginPhone(): void
    {
        $this->staffUser()->forceFill(['login_phone' => null])->save();
        $this->dispatch('notify', message: 'Mobile sign-in removed.');
    }

    /** Only gym admins and trainers sign in with a verified mobile; members use their member number. */
    protected function staffUser(): User
    {
        $user = Auth::user();
        abort_if($user->isSuperAdmin() || $user->isMember(), 403);

        return $user;
    }

    public function render()
    {
        $user = Auth::user();
        $layout = match (true) {
            $user->isSuperAdmin() => 'components.layouts.platform',
            $user->isMember() => 'components.layouts.portal',
            default => 'components.layouts.gym',
        };

        return view('livewire.account')->layout($layout);
    }
}
