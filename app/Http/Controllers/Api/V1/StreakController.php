<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\StreakService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StreakController extends ApiController
{
    /**
     * GET /api/v1/me/streak — data for the home-screen streak card.
     */
    public function __invoke(Request $request, StreakService $streaks): JsonResponse
    {
        return $this->success($streaks->summary($request->user('member')), 'Streak.');
    }
}
