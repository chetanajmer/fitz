<?php

namespace App\Livewire\Auth;

use App\Models\City;
use App\Models\Locality;
use App\Models\PlatformSetting;
use App\Models\PricingRate;
use App\Services\GymRegistrationService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Three-step gym sign-up: 1) city, 2) gym name and location, 3) owner account.
 */
#[Layout('components.layouts.guest', ['wide' => true])]
#[Title('Register your gym')]
class RegisterGym extends Component
{
    public const STEPS = [1 => 'City', 2 => 'Gym details', 3 => 'Owner account'];

    public int $step = 1;

    public string $gym_name = '';

    public string $city_id = '';

    public string $address = '';

    public string $map_url = '';

    public string $locality = '';

    public string $owner_name = '';

    public string $email = '';

    public string $phone = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $terms = false;

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return array_merge(...array_map(fn (int $step) => $this->rulesForStep($step), array_keys(self::STEPS)));
    }

    /**
     * @return array<string, mixed>
     */
    protected function rulesForStep(int $step): array
    {
        return match ($step) {
            1 => [
                'city_id' => ['required', Rule::exists('cities', 'id')->where('is_active', true)],
            ],
            2 => [
                'gym_name' => 'required|string|min:2|max:120',
                'map_url' => 'nullable|url:http,https|max:500',
                'locality' => 'nullable|string|max:80',
                'address' => 'nullable|string|max:255',
            ],
            3 => [
                'owner_name' => 'required|string|max:120',
                'email' => 'required|email|max:255|unique:users,email',
                'phone' => 'required|string|min:8|max:20',
                'password' => ['required', 'confirmed', Password::min(8)],
                'terms' => 'accepted',
            ],
        };
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['city_id' => 'city', 'map_url' => 'location link', 'owner_name' => 'name'];
    }

    public function selectCity(int $cityId): void
    {
        $this->city_id = (string) $cityId;
        $this->resetValidation('city_id');
    }

    public function next(): void
    {
        $this->validate($this->rulesForStep($this->step));
        $this->step = min($this->step + 1, count(self::STEPS));
    }

    public function back(): void
    {
        $this->resetValidation();
        $this->step = max($this->step - 1, 1);
    }

    public function goToStep(int $step): void
    {
        if ($step < $this->step) {
            $this->resetValidation();
            $this->step = max($step, 1);
        }
    }

    public function register(GymRegistrationService $service)
    {
        abort_unless(PlatformSetting::get('allow_registrations', '1') === '1', 403, 'New gym registrations are currently closed.');

        try {
            $data = $this->validate();
        } catch (ValidationException $e) {
            $this->step = $this->firstStepWithErrors(array_keys($e->errors()));

            throw $e;
        }

        $data['map_url'] = $data['map_url'] ?: null;
        $owner = $service->register($data);

        session()->flash('success', 'Welcome to Gymai! Your gym is ready.');

        return $this->redirect($owner->homeRoute());
    }

    /**
     * @param  list<string>  $fields
     */
    protected function firstStepWithErrors(array $fields): int
    {
        foreach (array_keys(self::STEPS) as $step) {
            if (array_intersect($fields, array_keys($this->rulesForStep($step)))) {
                return $step;
            }
        }

        return $this->step;
    }

    public function render()
    {
        return view('livewire.auth.register-gym', [
            'price' => PricingRate::rateOn(),
            'cities' => City::active()->orderBy('name')->get(['id', 'name', 'state']),
            'open' => PlatformSetting::get('allow_registrations', '1') === '1',
            'steps' => self::STEPS,
            'localities' => Locality::where('city_id', (int) $this->city_id)->orderBy('name')->pluck('name'),
        ]);
    }
}
