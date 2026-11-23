<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Models\City;
use App\Models\Gym;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public lists used by the app's sign-up flow: cities Gymai is live in, then gyms in a city.
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

    /** GET /api/v1/cities/{cityId}/gyms?search= */
    public function gyms(Request $request, int $cityId): JsonResponse
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:80']]);
        $search = trim((string) ($data['search'] ?? ''));

        $city = City::active()->find($cityId);
        if (! $city) {
            throw new ApiException('This city is not available on Gymai.', 'CITY_NOT_FOUND', 404);
        }

        $gyms = Gym::operational()
            ->where('city_id', $city->id)
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
                'logo_url' => $g->logoUrl(),
            ])->values(),
        ], 'Gyms.');
    }
}
