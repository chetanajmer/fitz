<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Resources\V1\GymBrandingResource;
use App\Models\Gym;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GymBrandingController extends ApiController
{
    /**
     * GET /api/v1/gyms/{gymId}/branding
     *
     * The gym id comes from verify-otp. A member may only read their own gym; the
     * gym must exist and be active, otherwise a GYM_NOT_FOUND / GYM_INACTIVE error is returned.
     */
    public function __invoke(Request $request, int $gymId): JsonResponse
    {
        $member = $request->user('member');
        $gym = Gym::find($gymId);

        if (! $gym) {
            throw new ApiException('Gym is not registered on Gymai.', 'GYM_NOT_FOUND', 404);
        }

        if ($gym->id !== $member->gym_id) {
            throw new ApiException('You can only access your own gym.', 'FORBIDDEN', 403);
        }

        if (! $gym->isOperational()) {
            throw new ApiException(
                'This gym is currently '.$gym->status->label().' on Gymai. Please contact your gym.',
                'GYM_INACTIVE',
                403,
                ['gym_id' => $gym->id, 'gym_status' => $gym->status->value, 'is_active' => false],
            );
        }

        return $this->success(new GymBrandingResource($gym), 'Gym branding.');
    }
}
