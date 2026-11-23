<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.guest')]
#[Title('Sign in')]
class Login extends Component
{
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function login()
    {
        $this->validate();

        $key = Str::lower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.',
            ]);
        }

        if (! Auth::attempt(['email' => Str::lower($this->email), 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        RateLimiter::clear($key);
        $user = Auth::user();

        if (! $user->isActive()) {
            Auth::logout();
            throw ValidationException::withMessages(['email' => 'Your account is inactive. Please contact your gym.']);
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
