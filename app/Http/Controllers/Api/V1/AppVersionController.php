<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\AppVersionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppVersionController extends ApiController
{
    /**
     * GET /api/v1/app/version?platform=android&version=1.0.3
     */
    public function __invoke(Request $request, AppVersionService $versions): JsonResponse
    {
        $data = $request->validate([
            'platform' => ['required', Rule::in(AppVersionService::PLATFORMS)],
            'version' => ['required', 'string', 'max:20', 'regex:/^\d+(\.\d+){0,3}$/'],
        ], ['version.regex' => 'Version must look like 1.2.3.']);

        $result = $versions->check($data['platform'], $data['version']);

        $message = match (true) {
            $result['force_update'] => 'A required update is available. Please update the app to continue.',
            $result['update_available'] => 'A new version of the app is available.',
            default => 'App is up to date.',
        };

        return $this->success($result, $message);
    }
}
