<?php

use App\Http\Controllers\Api\V1\AiCoachController;
use App\Http\Controllers\Api\V1\AppVersionController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BodyProgressController;
use App\Http\Controllers\Api\V1\CheckInController;
use App\Http\Controllers\Api\V1\CityController;
use App\Http\Controllers\Api\V1\FitnessPlanController;
use App\Http\Controllers\Api\V1\GymBrandingController;
use App\Http\Controllers\Api\V1\MemberProfileController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ProgramController;
use App\Http\Controllers\Api\V1\SignupController;
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

    // New member sign-up: verify phone, pick a city and a gym in it, send details (member starts pending).
    Route::get('cities', [CityController::class, 'index'])->middleware('throttle:60,1')->name('cities.index');
    Route::get('cities/{cityId}/localities', [CityController::class, 'localities'])->whereNumber('cityId')->middleware('throttle:60,1')->name('cities.localities');
    Route::get('cities/{cityId}/gyms', [CityController::class, 'gyms'])->whereNumber('cityId')->middleware('throttle:60,1')->name('cities.gyms');
    Route::get('gyms/{gymId}/profile', [CityController::class, 'gymProfile'])->whereNumber('gymId')->middleware('throttle:60,1')->name('gyms.profile');
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
            Route::get('measurements', [BodyProgressController::class, 'measurements'])->name('measurements.index');
            Route::post('measurements', [BodyProgressController::class, 'storeMeasurement'])->middleware('throttle:20,1')->name('measurements.store');
            Route::delete('measurements/{measurementId}', [BodyProgressController::class, 'destroyMeasurement'])->whereNumber('measurementId')->name('measurements.destroy');
            Route::get('progress-photos', [BodyProgressController::class, 'photos'])->name('progress-photos.index');
            Route::post('progress-photos', [BodyProgressController::class, 'storePhoto'])->middleware('throttle:10,1')->name('progress-photos.store');
            Route::delete('progress-photos/{photoId}', [BodyProgressController::class, 'destroyPhoto'])->whereNumber('photoId')->name('progress-photos.destroy');
            Route::get('progress-photos/{photoId}/image', [BodyProgressController::class, 'photoImage'])->whereNumber('photoId')->name('progress-photos.image');
            Route::get('ai', [AiCoachController::class, 'status'])->name('ai.status');
            Route::post('ai/consent', [AiCoachController::class, 'consent'])->middleware('throttle:20,1')->name('ai.consent');
            Route::get('ai/reports', [AiCoachController::class, 'index'])->name('ai.reports.index');
            Route::post('ai/reports', [AiCoachController::class, 'store'])->middleware('throttle:10,1')->name('ai.reports.store');
            Route::get('ai/reports/{reportId}', [AiCoachController::class, 'show'])->whereNumber('reportId')->name('ai.reports.show');
            Route::get('check-in', [CheckInController::class, 'status'])->name('check-in.status');
            Route::post('check-in', [CheckInController::class, 'checkIn'])->middleware('throttle:10,1')->name('check-in');
            Route::post('check-out', [CheckInController::class, 'checkOut'])->middleware('throttle:10,1')->name('check-out');
        });
    });
});
