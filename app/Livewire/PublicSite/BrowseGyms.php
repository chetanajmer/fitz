<?php

namespace App\Livewire\PublicSite;

use App\Models\City;
use App\Models\Gym;
use App\Models\Locality;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Browse the gyms in a city (optionally one locality) without signing up. Each gym's public
 * profile opens in a new tab. The filters live in the URL, so a search can be shared.
 */
#[Layout('components.layouts.guest', ['wide' => true])]
#[Title('Browse gyms')]
class BrowseGyms extends Component
{
    #[Url(as: 'city', except: null)]
    public ?int $cityId = null;

    #[Url(as: 'area', except: null)]
    public ?int $localityId = null;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public string $citySearch = '';

    public function selectCity(int $cityId): void
    {
        $this->cityId = City::active()->findOrFail($cityId)->id;
        $this->reset('localityId', 'search');
    }

    public function changeCity(): void
    {
        $this->reset('cityId', 'localityId', 'search', 'citySearch');
    }

    /** null = every locality of the city. */
    public function selectLocality(?int $localityId = null): void
    {
        $this->localityId = $localityId === null ? null : Locality::where('city_id', $this->cityId)->findOrFail($localityId)->id;
    }

    public function render(): View
    {
        $city = $this->cityId ? City::active()->find($this->cityId) : null;
        $search = trim($this->search);
        $cityLike = '%'.addcslashes(trim($this->citySearch), '%_\\').'%';

        return view('livewire.public-site.browse-gyms', [
            'city' => $city,
            'cities' => $city ? collect() : City::active()->withCount(['gyms' => fn ($q) => $q->operational()])
                ->when(trim($this->citySearch) !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', $cityLike)->orWhere('state', 'like', $cityLike)))
                ->orderBy('name')->get(),
            'localities' => $city ? Locality::where('city_id', $city->id)->whereHas('gyms', fn ($q) => $q->operational())
                ->withCount(['gyms' => fn ($q) => $q->operational()])->orderBy('name')->get() : collect(),
            'gyms' => $city ? Gym::operational()->with('locality')->where('city_id', $city->id)
                ->when($this->localityId, fn ($q, $id) => $q->where('locality_id', $id))
                ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.addcslashes($search, '%_\\').'%'))
                ->orderBy('name')->limit(60)->get() : collect(),
        ]);
    }
}
