<?php

use App\Http\Controllers\Api\V1\AppVersionController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CheckInController;
use App\Http\Controllers\Api\V1\CityController;
use App\Http\Controllers\Api\V1\FitnessPlanController;
use App\Http\Controllers\Api\V1\GymBrandingController;
use App\Http\Controllers\Api\V1\SignupController;
use App\Http\Controllers\Api\V1\StreakController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Member mobile app API — v1  (base URL: /api/v1)
|--------------------------------------------------------------------------
| Auth: Sanctum bearer tokens issued to members (guard "member").
| Every response uses { success, message, data } or { success:false, message, code, data }.
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('app/version', AppVersionController::class)->middleware('throttle:60,1')->name('app.version');

    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('send-otp', [AuthController::class, 'sendOtp'])->middleware('throttle:otp-send')->name('send-otp');
        Route::post('verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:otp-verify')->name('verify-otp');
    });

    // New member sign-up: verify phone, pick a city and a gym in it, send details (member starts pending).
    Route::get('cities', [CityController::class, 'index'])->middleware('throttle:60,1')->name('cities.index');
    Route::get('cities/{cityId}/gyms', [CityController::class, 'gyms'])->whereNumber('cityId')->middleware('throttle:60,1')->name('cities.gyms');
    Route::prefix('auth/register')->name('auth.register.')->group(function () {
        Route::post('send-otp', [SignupController::class, 'sendOtp'])->middleware('throttle:otp-send')->name('send-otp');
        Route::post('verify-otp', [SignupController::class, 'verifyOtp'])->middleware('throttle:otp-verify')->name('verify-otp');
        Route::post('/', [SignupController::class, 'register'])->middleware('throttle:10,1')->name('store');
    });

    Route::middleware(['auth:member'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout'])->middleware('member.api:any-gym-status')->name('auth.logout');

        // Branding reports the gym status itself, so it accepts suspended/deactivated gyms here.
        Route::get('gyms/{gymId}/branding', GymBrandingController::class)
            ->whereNumber('gymId')
            ->middleware('member.api:any-gym-status')
            ->name('gyms.branding');

        // Member data — requires an active gym.
        Route::middleware('member.api')->prefix('me')->name('me.')->group(function () {
            Route::get('streak', StreakController::class)->name('streak');
            Route::get('plans', [FitnessPlanController::class, 'index'])->name('plans.index');
            Route::get('plans/{planId}', [FitnessPlanController::class, 'show'])->whereNumber('planId')->name('plans.show');
            Route::get('check-in', [CheckInController::class, 'status'])->name('check-in.status');
            Route::post('check-in', [CheckInController::class, 'checkIn'])->middleware('throttle:10,1')->name('check-in');
            Route::post('check-out', [CheckInController::class, 'checkOut'])->middleware('throttle:10,1')->name('check-out');
        });
    });
});
