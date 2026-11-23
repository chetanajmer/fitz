<?php

namespace App\Livewire;

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

    public function mount(): void
    {
        $user = Auth::user();
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = (string) $user->phone;
    }

    public function saveProfile(): void
    {
        $user = Auth::user();

        $data = $this->validate([
            'name' => 'required|string|max:120',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => 'nullable|string|max:20',
        ]);

        $user->update(['name' => $data['name'], 'email' => strtolower($data['email']), 'phone' => $data['phone'] ?: null]);

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
