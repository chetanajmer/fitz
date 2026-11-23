<?php

namespace App\Livewire\Platform;

use App\Models\City;
use App\Services\CityService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Cities Gymai operates in. Active cities are offered to gyms when they register and
 * to members signing up from the app; switching a city off hides it (and its gyms) there.
 */
#[Layout('components.layouts.platform')]
#[Title('Cities')]
class Cities extends Component
{
    #[Locked]
    public ?int $editingId = null;

    public string $name = '';

    public string $state = '';

    public function edit(int $cityId): void
    {
        $city = City::findOrFail($cityId);
        $this->editingId = $city->id;
        $this->name = $city->name;
        $this->state = (string) $city->state;
        $this->resetValidation();
    }

    public function cancel(): void
    {
        $this->reset('editingId', 'name', 'state');
        $this->resetValidation();
    }

    public function save(CityService $cities): void
    {
        $data = $this->validate([
            'name' => 'required|string|min:2|max:80',
            'state' => 'nullable|string|max:80',
        ]);

        if ($this->editingId) {
            $cities->update(City::findOrFail($this->editingId), $data['name'], $data['state']);
            $message = 'City updated.';
        } else {
            $cities->create($data['name'], $data['state']);
            $message = 'City added.';
        }

        $this->cancel();
        $this->dispatch('notify', message: $message);
    }

    public function toggle(int $cityId, CityService $cities): void
    {
        $city = City::findOrFail($cityId);
        $cities->setActive($city, ! $city->is_active);
        $this->dispatch('notify', message: $city->is_active ? "{$city->name} is live." : "{$city->name} is hidden from gyms and members.");
    }

    public function render(): View
    {
        return view('livewire.platform.cities', [
            'cities' => City::withCount('gyms')->orderByDesc('is_active')->orderBy('name')->get(),
        ]);
    }
}
