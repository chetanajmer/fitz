<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class ApiController extends Controller
{
    /** Uniform success envelope: { "success": true, "message": "...", "data": {...} } */
    protected function success(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        if ($data instanceof JsonResource) {
            $data = $data->resolve(request());
        }

        return response()->json(['success' => true, 'message' => $message, 'data' => $data], $status);
    }
}
