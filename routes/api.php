<?php

use App\Http\Controllers\Api\V1\AppVersionController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CheckInController;
use App\Http\Controllers\Api\V1\FitnessPlanController;
use App\Http\Controllers\Api\V1\GymBrandingController;
use App\Http\Controllers\Api\V1\MemberProfileController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ProgramController;
use App\Http\Controllers\Api\V1\StreakController;
use App\Http\Controllers\Api\V1\WeightController;
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

    Route::middleware(['auth:member'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout'])->middleware('member.api:any-gym-status')->name('auth.logout');

        // Branding reports the gym status itself, so it accepts suspended/deactivated gyms here.
        Route::get('gyms/{gymId}/branding', GymBrandingController::class)
            ->whereNumber('gymId')
            ->middleware('member.api:any-gym-status')
            ->name('gyms.branding');

        // Member data — requires an active gym.
        Route::middleware('member.api')->prefix('me')->name('me.')->group(function () {
            Route::get('/', [MemberProfileController::class, 'show'])->name('profile');
            Route::get('membership', [MemberProfileController::class, 'membership'])->name('membership');
            Route::get('profile', [MemberProfileController::class, 'profile'])->name('profile.full');
            Route::match(['put', 'patch'], 'profile', [MemberProfileController::class, 'update'])->middleware('throttle:20,1')->name('profile.update');
            Route::post('profile/photo', [MemberProfileController::class, 'updatePhoto'])->middleware('throttle:10,1')->name('profile.photo');
            Route::delete('profile/photo', [MemberProfileController::class, 'deletePhoto'])->middleware('throttle:10,1')->name('profile.photo.delete');
            Route::get('streak', StreakController::class)->name('streak');
            Route::get('weight', [WeightController::class, 'show'])->name('weight');
            Route::post('weight', [WeightController::class, 'store'])->middleware('throttle:20,1')->name('weight.store');
            Route::delete('weight/{logId}', [WeightController::class, 'destroy'])->whereNumber('logId')->name('weight.destroy');
            Route::post('weight/goal', [WeightController::class, 'setGoal'])->middleware('throttle:20,1')->name('weight.goal');
            Route::post('weight/popup/ack', [WeightController::class, 'acknowledge'])->name('weight.popup.ack');
            Route::post('devices', [NotificationController::class, 'registerDevice'])->middleware('throttle:20,1')->name('devices.store');
            Route::delete('devices', [NotificationController::class, 'removeDevice'])->name('devices.destroy');
            Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
            Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
            Route::post('notifications/{notificationId}/read', [NotificationController::class, 'markRead'])->whereNumber('notificationId')->name('notifications.read');
            Route::get('notification-settings', [NotificationController::class, 'settings'])->name('notification-settings');
            Route::put('notification-settings', [NotificationController::class, 'updateSettings'])->name('notification-settings.update');
            Route::get('programs', [ProgramController::class, 'index'])->name('programs');
            Route::get('schedule', [ProgramController::class, 'schedule'])->name('schedule');
            Route::post('schedule/log', [ProgramController::class, 'logDay'])->middleware('throttle:30,1')->name('schedule.log');
            Route::delete('schedule/log', [ProgramController::class, 'clearDay'])->middleware('throttle:30,1')->name('schedule.log.clear');
            Route::get('plans', [FitnessPlanController::class, 'index'])->name('plans.index');
            Route::get('plans/{planId}', [FitnessPlanController::class, 'show'])->whereNumber('planId')->name('plans.show');
            Route::get('check-in', [CheckInController::class, 'status'])->name('check-in.status');
            Route::post('check-in', [CheckInController::class, 'checkIn'])->middleware('throttle:10,1')->name('check-in');
            Route::post('check-out', [CheckInController::class, 'checkOut'])->middleware('throttle:10,1')->name('check-out');
        });
    });
});
