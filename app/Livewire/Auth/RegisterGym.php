<?php

namespace App\Livewire\Auth;

use App\Models\PlatformSetting;
use App\Models\PricingRate;
use App\Services\GymRegistrationService;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.guest', ['wide' => true])]
#[Title('Register your gym')]
class RegisterGym extends Component
{
    public string $gym_name = '';

    public string $city = '';

    public string $address = '';

    public string $gym_phone = '';

    public string $owner_name = '';

    public string $email = '';

    public string $phone = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $terms = false;

    protected function rules(): array
    {
        return [
            'gym_name' => 'required|string|min:2|max:120',
            'city' => 'required|string|max:80',
            'address' => 'nullable|string|max:255',
            'gym_phone' => 'nullable|string|max:20',
            'owner_name' => 'required|string|max:120',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'required|string|min:8|max:20',
            'password' => ['required', 'confirmed', Password::min(8)],
            'terms' => 'accepted',
        ];
    }

    public function register(GymRegistrationService $service)
    {
        abort_unless(PlatformSetting::get('allow_registrations', '1') === '1', 403, 'New gym registrations are currently closed.');

        $data = $this->validate();
        $owner = $service->register($data);

        session()->flash('success', 'Welcome to Gymai! Your gym is ready.');

        return $this->redirect($owner->homeRoute());
    }

    public function render()
    {
        return view('livewire.auth.register-gym', [
            'price' => PricingRate::rateOn(),
            'open' => PlatformSetting::get('allow_registrations', '1') === '1',
        ]);
    }
}
