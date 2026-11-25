<?php

namespace App\Services;

use App\Models\City;
use App\Models\Gym;
use App\Models\Locality;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Super Admin managed list of cities Gymai operates in, and linking gyms to them.
 */
class CityService
{
    public function create(string $name, ?string $state = null): City
    {
        $name = trim($name);
        $this->ensureNameIsFree($name);

        return City::create(['name' => $name, 'state' => $state ?: null, 'is_active' => true]);
    }

    /** Rename a city; the display name on its gyms follows. */
    public function update(City $city, string $name, ?string $state = null): City
    {
        $name = trim($name);
        $this->ensureNameIsFree($name, $city->id);

        return DB::transaction(function () use ($city, $name, $state) {
            $city->update(['name' => $name, 'state' => $state ?: null]);
            Gym::where('city_id', $city->id)->update(['city' => $name]);

            return $city;
        });
    }

    public function setActive(City $city, bool $active): City
    {
        $city->update(['is_active' => $active]);

        return $city;
    }

    /**
     * The city for a gym: an explicit id (web forms validate it against the active list), or a
     * name from seeders / older callers, matched case-insensitively and created when new.
     */
    public function resolve(?int $cityId, ?string $name = null): ?City
    {
        if ($cityId) {
            return City::findOrFail($cityId);
        }

        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        return City::findByName($name) ?? City::create(['name' => $name, 'is_active' => true]);
    }

    public function assignToGym(Gym $gym, ?City $city): void
    {
        if ((int) $gym->city_id !== (int) $city?->id) {
            $gym->locality_id = null; // a locality belongs to one city
        }

        $gym->city_id = $city?->id;
        $gym->city = $city?->name;
    }

    /**
     * Set the gym's locality by name within its city: an existing one (matched case-insensitively)
     * or a new one added to the city's list. A blank name clears it.
     */
    public function assignLocality(Gym $gym, ?string $name): void
    {
        $name = Str::squish((string) $name);

        if ($name === '' || ! $gym->city_id) {
            $gym->locality_id = null;

            return;
        }

        $locality = Locality::where('city_id', $gym->city_id)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();

        if (! $locality) {
            $locality = new Locality(['name' => $name]);
            $locality->city_id = $gym->city_id;
            $locality->save();
        }

        $gym->locality_id = $locality->id;
    }

    public function renameLocality(Locality $locality, string $name): Locality
    {
        $name = Str::squish($name);
        $taken = Locality::where('city_id', $locality->city_id)->whereKeyNot($locality->id)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists();

        if ($taken) {
            throw ValidationException::withMessages(['localityName' => "{$name} is already in this city's list."]);
        }

        $locality->update(['name' => $name]);

        return $locality;
    }

    /** Remove a locality; its gyms keep their city and simply have no locality. */
    public function deleteLocality(Locality $locality): void
    {
        $locality->delete();
    }

    protected function ensureNameIsFree(string $name, ?int $ignoreId = null): void
    {
        $existing = City::findByName($name);

        if ($existing && $existing->id !== $ignoreId) {
            throw ValidationException::withMessages(['name' => "{$existing->name} is already in the list."]);
        }
    }
}
