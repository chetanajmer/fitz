<?php

namespace App\Livewire\Platform;

use App\Models\City;
use App\Models\Locality;
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

    /** The city whose localities are open. */
    #[Locked]
    public ?int $localityCityId = null;

    #[Locked]
    public ?int $editingLocalityId = null;

    public string $localityName = '';

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

    public function showLocalities(int $cityId): void
    {
        $this->localityCityId = $this->localityCityId === $cityId ? null : City::findOrFail($cityId)->id;
        $this->reset('editingLocalityId', 'localityName');
    }

    public function editLocality(int $localityId): void
    {
        $locality = Locality::findOrFail($localityId);
        $this->editingLocalityId = $locality->id;
        $this->localityName = $locality->name;
        $this->resetValidation();
    }

    public function cancelLocality(): void
    {
        $this->reset('editingLocalityId', 'localityName');
        $this->resetValidation();
    }

    public function renameLocality(CityService $cities): void
    {
        $this->validate(['localityName' => 'required|string|min:2|max:80'], [], ['localityName' => 'locality']);
        $cities->renameLocality(Locality::findOrFail($this->editingLocalityId), $this->localityName);
        $this->reset('editingLocalityId', 'localityName');
        $this->dispatch('notify', message: 'Locality renamed.');
    }

    public function deleteLocality(int $localityId, CityService $cities): void
    {
        $cities->deleteLocality(Locality::findOrFail($localityId));
        $this->dispatch('notify', message: 'Locality removed.');
    }

    public function render(): View
    {
        return view('livewire.platform.cities', [
            'cities' => City::withCount(['gyms', 'localities'])->orderByDesc('is_active')->orderBy('name')->get(),
            'localities' => $this->localityCityId ? Locality::where('city_id', $this->localityCityId)->withCount('gyms')->orderBy('name')->get() : collect(),
        ]);
    }
}
