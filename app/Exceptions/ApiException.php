<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Business-rule error for the mobile API, rendered as:
 * { "success": false, "message": "...", "code": "OTP_INVALID", "data": {...} }
 */
class ApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status = 400,
        public readonly array $data = [],
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(array_filter([
            'success' => false,
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
            'data' => $this->data ?: null,
        ], fn ($v) => $v !== null), $this->status);
    }
}
