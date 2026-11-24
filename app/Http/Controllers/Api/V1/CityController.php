<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Models\City;
use App\Models\Gym;
use App\Models\Locality;
use App\Services\GymProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public lists used by the app's sign-up flow: cities Gymai is live in, their localities, the gyms
 * in a city (optionally one locality) and each gym's public profile.
 * Only active cities and operational gyms are ever returned.
 */
class CityController extends ApiController
{
    /** GET /api/v1/cities */
    public function index(): JsonResponse
    {
        $cities = City::active()
            ->withCount(['gyms' => fn ($q) => $q->operational()])
            ->orderBy('name')
            ->get();

        return $this->success([
            'cities' => $cities->map(fn (City $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'state' => $c->state,
                'gyms_count' => $c->gyms_count,
            ])->values(),
        ], 'Cities.');
    }

    /** GET /api/v1/cities/{cityId}/localities — areas of the city that have gyms (empty: skip the locality step). */
    public function localities(int $cityId): JsonResponse
    {
        $city = $this->activeCity($cityId);

        $localities = Locality::where('city_id', $city->id)
            ->whereHas('gyms', fn ($q) => $q->operational())
            ->withCount(['gyms' => fn ($q) => $q->operational()])
            ->orderBy('name')
            ->get();

        return $this->success([
            'city' => ['id' => $city->id, 'name' => $city->name],
            'gyms_count' => Gym::operational()->where('city_id', $city->id)->count(),
            'localities' => $localities->map(fn (Locality $l) => ['id' => $l->id, 'name' => $l->name, 'gyms_count' => $l->gyms_count])->values(),
        ], 'Localities.');
    }

    /** GET /api/v1/cities/{cityId}/gyms?search=&locality_id= */
    public function gyms(Request $request, int $cityId): JsonResponse
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:80'], 'locality_id' => ['nullable', 'integer']]);
        $search = trim((string) ($data['search'] ?? ''));
        $city = $this->activeCity($cityId);

        $gyms = Gym::operational()
            ->with('locality')
            ->where('city_id', $city->id)
            ->when($data['locality_id'] ?? null, fn ($q, $id) => $q->where('locality_id', $id))
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->orderBy('name')
            ->limit(30)
            ->get();

        return $this->success([
            'city' => ['id' => $city->id, 'name' => $city->name],
            'gyms' => $gyms->map(fn (Gym $g) => [
                'gym_id' => $g->id,
                'name' => $g->name,
                'address' => $g->address,
                'locality' => $g->locality?->name,
                'logo_url' => $g->logoUrl(),
                'public_url' => $g->publicUrl(), // open in the phone's browser
                'directions_url' => $g->directionsUrl(),
            ])->values(),
        ], 'Gyms.');
    }

    /** GET /api/v1/gyms/{gymId}/profile — the gym's public profile, shown before a new member joins. */
    public function gymProfile(int $gymId, GymProfileService $profiles): JsonResponse
    {
        $gym = Gym::operational()->whereHas('cityRecord', fn ($q) => $q->active())->find($gymId);
        if (! $gym) {
            throw new ApiException('This gym is not available on Gymai.', 'GYM_NOT_FOUND', 404);
        }

        return $this->success($profiles->publicSummary($gym), 'Gym profile.');
    }

    protected function activeCity(int $cityId): City
    {
        return City::active()->find($cityId) ?? throw new ApiException('This city is not available on Gymai.', 'CITY_NOT_FOUND', 404);
    }
}
