<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureGymIsOperational;
use App\Http\Middleware\SetMemberApiContext;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'active' => EnsureAccountIsActive::class,
            'gym.operational' => EnsureGymIsOperational::class,
            'member.api' => SetMemberApiContext::class,
        ]);

        // Panel theme preference is written by JS (light/dark), so it is stored unencrypted.
        $middleware->encryptCookies(except: ['theme']);

        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()->homeRoute());
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Mobile API: every error uses { success: false, message, code, errors? }.
        $apiError = fn (string $message, string $code, int $status, array $extra = []) => response()->json(
            ['success' => false, 'message' => $message, 'code' => $code] + $extra, $status
        );

        $exceptions->render(function (ValidationException $e, Request $request) use ($apiError) {
            return $request->is('api/*') ? $apiError($e->getMessage(), 'VALIDATION_ERROR', 422, ['errors' => $e->errors()]) : null;
        });
        $exceptions->render(function (AuthenticationException $e, Request $request) use ($apiError) {
            return $request->is('api/*') ? $apiError('Unauthenticated. Please log in again.', 'UNAUTHENTICATED', 401) : null;
        });
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) use ($apiError) {
            return $request->is('api/*') ? $apiError('Too many requests. Please slow down.', 'TOO_MANY_REQUESTS', 429, ['data' => ['retry_after' => (int) ($e->getHeaders()['Retry-After'] ?? 60)]]) : null;
        });
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($apiError) {
            if (! $request->is('api/*')) {
                return null;
            }
            $status = $e->getStatusCode();

            return $apiError($status === 404 ? 'Not found.' : ($e->getMessage() ?: 'Request failed.'), $status === 404 ? 'NOT_FOUND' : 'HTTP_'.$status, $status);
        });
    })->create();
