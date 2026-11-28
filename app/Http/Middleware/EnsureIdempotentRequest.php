<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff API create requests may send an "Idempotency-Key" header (a UUID). The first successful response
 * is stored for 24 hours against the user and key; sending the same key again returns that response
 * (with "Idempotent-Replayed: true") and creates nothing new. Failed requests are not stored, so they can be retried.
 */
class EnsureIdempotentRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');
        $user = $request->user('staff');

        if ($key === null || $key === '' || ! $user) {
            return $next($request);
        }

        if (! Str::isUuid($key)) {
            throw new ApiException('Idempotency-Key must be a UUID.', 'VALIDATION_ERROR', 422);
        }

        $route = $request->method().' '.$request->path();
        $hash = hash('sha256', json_encode($request->except(['photo', 'image'])).'|'.collect($request->allFiles())->keys()->implode(','));

        IdempotencyKey::where('user_id', $user->id)->where('key', $key)
            ->where('created_at', '<', now()->subHours(IdempotencyKey::TTL_HOURS))->delete();

        $stored = IdempotencyKey::where('user_id', $user->id)->where('key', $key)->first();

        if ($stored) {
            return $this->replay($stored, $route, $hash);
        }

        try {
            $stored = IdempotencyKey::create([
                'user_id' => $user->id, 'key' => $key, 'route' => $route, 'request_hash' => $hash, 'created_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Another request with this key arrived at the same moment.
            return $this->replay(IdempotencyKey::where('user_id', $user->id)->where('key', $key)->firstOrFail(), $route, $hash);
        }

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            $stored->delete();

            throw $e;
        }

        if ($response->isSuccessful()) {
            $stored->forceFill(['response_status' => $response->getStatusCode(), 'response_body' => $response->getContent()])->save();
        } else {
            $stored->delete();
        }

        return $response;
    }

    protected function replay(IdempotencyKey $stored, string $route, string $hash): Response
    {
        if ($stored->route !== $route || ! hash_equals($stored->request_hash, $hash)) {
            throw new ApiException('This Idempotency-Key was already used for a different request.', 'IDEMPOTENCY_KEY_REUSED', 422);
        }

        if ($stored->response_status === null) {
            throw new ApiException('The first request with this Idempotency-Key is still being processed.', 'IDEMPOTENCY_IN_PROGRESS', 409);
        }

        return new JsonResponse(json_decode((string) $stored->response_body, true), $stored->response_status, ['Idempotent-Replayed' => 'true']);
    }
}
