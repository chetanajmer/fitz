<?php

use App\Http\Controllers\Api\V1\Admin\AccountController as StaffAccountController;
use App\Http\Controllers\Api\V1\Admin\AnnouncementController as StaffAnnouncementController;
use App\Http\Controllers\Api\V1\Admin\AttendanceController as StaffAttendanceController;
use App\Http\Controllers\Api\V1\Admin\AttendanceReportController as StaffAttendanceReportController;
use App\Http\Controllers\Api\V1\Admin\AuthController as StaffAuthController;
use App\Http\Controllers\Api\V1\Admin\DashboardController as StaffDashboardController;
use App\Http\Controllers\Api\V1\Admin\EnquiryController as StaffEnquiryController;
use App\Http\Controllers\Api\V1\Admin\FeeController as StaffFeeController;
use App\Http\Controllers\Api\V1\Admin\GymAdminToolsController as StaffGymAdminToolsController;
use App\Http\Controllers\Api\V1\Admin\GymSettingsController as StaffGymSettingsController;
use App\Http\Controllers\Api\V1\Admin\MemberController as StaffMemberController;
use App\Http\Controllers\Api\V1\Admin\MemberProgramController as StaffMemberProgramController;
use App\Http\Controllers\Api\V1\Admin\MemberProgressController as StaffMemberProgressController;
use App\Http\Controllers\Api\V1\Admin\MemberToolsController as StaffMemberToolsController;
use App\Http\Controllers\Api\V1\Admin\OptionsController as StaffOptionsController;
use App\Http\Controllers\Api\V1\Admin\PlanController as StaffPlanController;
use App\Http\Controllers\Api\V1\Admin\ReportController as StaffReportController;
use App\Http\Controllers\Api\V1\Admin\TrainerController as StaffTrainerController;
use App\Http\Controllers\Api\V1\AiCoachController;
use App\Http\Controllers\Api\V1\AppVersionController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BodyProgressController;
use App\Http\Controllers\Api\V1\CheckInController;
use App\Http\Controllers\Api\V1\CityController;
use App\Http\Controllers\Api\V1\ConsentController;
use App\Http\Controllers\Api\V1\FitnessPlanController;
use App\Http\Controllers\Api\V1\GymBrandingController;
use App\Http\Controllers\Api\V1\MemberProfileController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ProgramController;
use App\Http\Controllers\Api\V1\ReminderController;
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
            Route::get('transformation', [BodyProgressController::class, 'transformation'])->name('transformation');
            Route::get('ai', [AiCoachController::class, 'status'])->name('ai.status');
            Route::post('ai/consent', [AiCoachController::class, 'consent'])->middleware('throttle:20,1')->name('ai.consent');
            Route::get('ai/reports', [AiCoachController::class, 'index'])->name('ai.reports.index');
            Route::post('ai/reports', [AiCoachController::class, 'store'])->middleware('throttle:10,1')->name('ai.reports.store');
            Route::get('ai/reports/{reportId}', [AiCoachController::class, 'show'])->whereNumber('reportId')->name('ai.reports.show');
            Route::get('reminders', [ReminderController::class, 'show'])->name('reminders');
            Route::put('reminders', [ReminderController::class, 'update'])->middleware('throttle:30,1')->name('reminders.update');
            Route::get('hydration', [ReminderController::class, 'hydration'])->name('hydration');
            Route::post('hydration', [ReminderController::class, 'logWater'])->middleware('throttle:30,1')->name('hydration.store');
            Route::delete('hydration/last', [ReminderController::class, 'undoWater'])->middleware('throttle:30,1')->name('hydration.undo');
            Route::get('consents', [ConsentController::class, 'index'])->name('consents');
            Route::post('consents', [ConsentController::class, 'store'])->middleware('throttle:20,1')->name('consents.store');
            Route::delete('ai/data', [ConsentController::class, 'destroyAiData'])->middleware('throttle:5,1')->name('ai.data.destroy');
            Route::get('check-in', [CheckInController::class, 'status'])->name('check-in.status');
            Route::post('check-in', [CheckInController::class, 'checkIn'])->middleware('throttle:10,1')->name('check-in');
            Route::post('check-in/qr', [CheckInController::class, 'qrCheckIn'])->middleware('throttle:10,1')->name('check-in.qr');
            Route::post('check-out', [CheckInController::class, 'checkOut'])->middleware('throttle:10,1')->name('check-out');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Staff mobile app API (gym admins and trainers) — v1  (base URL: /api/v1/admin)
|--------------------------------------------------------------------------
| Auth: Sanctum bearer tokens issued to App\Models\User (guard "staff"). Separate from the member API above:
| member tokens are refused here and staff tokens are refused there.
*/

Route::prefix('v1/admin')->name('api.v1.admin.')->group(function () {
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('login', [StaffAuthController::class, 'login'])->middleware('throttle:staff-auth')->name('login');
        Route::post('send-otp', [StaffAuthController::class, 'sendOtp'])->middleware('throttle:staff-otp-send')->name('send-otp');
        Route::post('verify-otp', [StaffAuthController::class, 'verifyOtp'])->middleware('throttle:staff-otp-verify')->name('verify-otp');
        Route::post('select-account', [StaffAuthController::class, 'selectAccount'])->middleware('throttle:staff-auth')->name('select-account');
    });

    Route::middleware(['auth:staff', 'staff.api'])->group(function () {
        Route::post('auth/logout', [StaffAuthController::class, 'logout'])->name('auth.logout');

        Route::get('me', [StaffAccountController::class, 'show'])->name('me');
        Route::put('me', [StaffAccountController::class, 'update'])->middleware('throttle:staff-api')->name('me.update');
        Route::put('me/password', [StaffAccountController::class, 'password'])->middleware('throttle:staff-api')->name('me.password');
        Route::post('me/login-phone/send-otp', [StaffAccountController::class, 'sendLoginPhoneOtp'])->middleware('throttle:staff-otp-send')->name('me.login-phone.send-otp');
        Route::post('me/login-phone/verify-otp', [StaffAccountController::class, 'verifyLoginPhoneOtp'])->middleware('throttle:staff-otp-verify')->name('me.login-phone.verify-otp');
        Route::delete('me/login-phone', [StaffAccountController::class, 'removeLoginPhone'])->name('me.login-phone.destroy');
        Route::post('me/delete-request', [StaffAccountController::class, 'deleteRequest'])->middleware('throttle:staff-api')->name('me.delete-request');
        Route::post('devices', [StaffAccountController::class, 'registerDevice'])->middleware('throttle:staff-api')->name('devices.store');
        Route::delete('devices', [StaffAccountController::class, 'removeDevice'])->name('devices.destroy');

        Route::get('options/plans', [StaffOptionsController::class, 'plans'])
            ->middleware('staff.can:plans.manage|memberships.manage|payments.manage|enquiries.manage')->name('options.plans');
        Route::get('options/trainers', [StaffOptionsController::class, 'trainers'])
            ->middleware('staff.can:members.all|trainers.manage|enquiries.manage|announcements.send')->name('options.trainers');

        Route::get('dashboard', StaffDashboardController::class)->middleware('staff.can:gym.dashboard')->name('dashboard');

        Route::middleware('staff.can:members.import')->group(function () {
            Route::get('members/import', [StaffMemberToolsController::class, 'importFormat'])->name('members.import.format');
            Route::post('members/import', [StaffMemberToolsController::class, 'import'])->middleware(['throttle:staff-api', 'idempotent'])->name('members.import');
        });

        Route::middleware('staff.can:members.view')->group(function () {
            Route::get('members', [StaffMemberController::class, 'index'])->name('members.index');
            Route::get('members/{memberId}', [StaffMemberController::class, 'show'])->whereNumber('memberId')->name('members.show');
        });
        Route::middleware('staff.can:members.manage')->group(function () {
            Route::post('members', [StaffMemberController::class, 'store'])->middleware(['throttle:staff-api', 'idempotent'])->name('members.store');
            Route::put('members/{memberId}', [StaffMemberController::class, 'update'])->whereNumber('memberId')->middleware('throttle:staff-api')->name('members.update');
            Route::post('members/{memberId}/photo', [StaffMemberController::class, 'uploadPhoto'])->whereNumber('memberId')->middleware('throttle:staff-api')->name('members.photo');
            Route::delete('members/{memberId}/photo', [StaffMemberController::class, 'deletePhoto'])->whereNumber('memberId')->name('members.photo.destroy');
            Route::post('members/{memberId}/status', [StaffMemberController::class, 'status'])->whereNumber('memberId')->name('members.status');
            Route::post('members/{memberId}/password', [StaffMemberController::class, 'password'])->whereNumber('memberId')->middleware('throttle:staff-api')->name('members.password');
            Route::post('members/{memberId}/trainer', [StaffMemberController::class, 'trainer'])->whereNumber('memberId')->name('members.trainer');
            Route::delete('members/{memberId}', [StaffMemberController::class, 'destroy'])->whereNumber('memberId')->name('members.destroy');
        });

        Route::middleware('staff.can:attendance.manage')->group(function () {
            Route::get('attendance/today', [StaffAttendanceController::class, 'today'])->name('attendance.today');
            Route::post('attendance/check-in', [StaffAttendanceController::class, 'checkIn'])->middleware(['throttle:staff-api', 'idempotent'])->name('attendance.check-in');
            Route::post('attendance/{attendanceId}/check-out', [StaffAttendanceController::class, 'checkOut'])->whereNumber('attendanceId')->middleware('throttle:staff-api')->name('attendance.check-out');
            Route::get('check-in-qr', [StaffAttendanceController::class, 'checkInQr'])->name('check-in-qr');
        });

        // Fees: memberships (plans assigned to members), payments, dues, receipts and invoices.
        Route::get('members/{memberId}/fees', [StaffFeeController::class, 'memberFees'])->whereNumber('memberId')
            ->middleware('staff.can:memberships.manage|payments.manage')->name('members.fees');
        Route::get('memberships/{membershipId}/invoice', [StaffFeeController::class, 'invoice'])->whereNumber('membershipId')
            ->middleware('staff.can:memberships.manage|payments.manage')->name('memberships.invoice');
        Route::get('pending-fees', [StaffFeeController::class, 'pendingFees'])
            ->middleware('staff.can:memberships.manage|payments.manage')->name('pending-fees');

        Route::middleware('staff.can:memberships.manage')->group(function () {
            Route::get('memberships', [StaffFeeController::class, 'memberships'])->name('memberships.index');
            Route::post('members/{memberId}/memberships', [StaffFeeController::class, 'assign'])->whereNumber('memberId')->middleware(['throttle:staff-api', 'idempotent'])->name('members.memberships.store');
            Route::post('memberships/{membershipId}/cancel', [StaffFeeController::class, 'cancel'])->whereNumber('membershipId')->middleware('throttle:staff-api')->name('memberships.cancel');
        });

        Route::middleware('staff.can:payments.manage')->group(function () {
            Route::get('payments', [StaffFeeController::class, 'payments'])->name('payments.index');
            Route::post('payments', [StaffFeeController::class, 'record'])->middleware(['throttle:staff-api', 'idempotent'])->name('payments.store');
            Route::get('payments/{paymentId}', [StaffFeeController::class, 'payment'])->whereNumber('paymentId')->name('payments.show');
            Route::get('payments/{paymentId}/receipt', [StaffFeeController::class, 'receipt'])->whereNumber('paymentId')->name('payments.receipt');
            Route::get('reports/overview', [StaffReportController::class, 'overview'])->name('reports.overview');
        });

        // Membership plans.
        Route::prefix('plans')->name('plans.')->middleware('staff.can:plans.manage')->group(function () {
            Route::get('/', [StaffPlanController::class, 'index'])->name('index');
            Route::post('/', [StaffPlanController::class, 'store'])->middleware(['throttle:staff-api', 'idempotent'])->name('store');
            Route::get('{planId}', [StaffPlanController::class, 'show'])->whereNumber('planId')->name('show');
            Route::put('{planId}', [StaffPlanController::class, 'update'])->whereNumber('planId')->middleware('throttle:staff-api')->name('update');
            Route::post('{planId}/toggle', [StaffPlanController::class, 'toggle'])->whereNumber('planId')->name('toggle');
            Route::delete('{planId}', [StaffPlanController::class, 'destroy'])->whereNumber('planId')->name('destroy');
        });

        // Enquiries (leads) and join requests.
        Route::middleware('staff.can:enquiries.manage')->group(function () {
            Route::get('enquiries', [StaffEnquiryController::class, 'index'])->name('enquiries.index');
            Route::get('enquiries/options', [StaffEnquiryController::class, 'options'])->name('enquiries.options');
            Route::post('enquiries', [StaffEnquiryController::class, 'store'])->middleware(['throttle:staff-api', 'idempotent'])->name('enquiries.store');
            Route::get('enquiries/{enquiryId}', [StaffEnquiryController::class, 'show'])->whereNumber('enquiryId')->name('enquiries.show');
            Route::put('enquiries/{enquiryId}', [StaffEnquiryController::class, 'update'])->whereNumber('enquiryId')->middleware('throttle:staff-api')->name('enquiries.update');
            Route::post('enquiries/{enquiryId}/status', [StaffEnquiryController::class, 'status'])->whereNumber('enquiryId')->middleware('throttle:staff-api')->name('enquiries.status');
            Route::post('enquiries/{enquiryId}/notes', [StaffEnquiryController::class, 'addNote'])->whereNumber('enquiryId')->middleware('throttle:staff-api')->name('enquiries.notes');
            Route::post('enquiries/{enquiryId}/convert', [StaffEnquiryController::class, 'convert'])->whereNumber('enquiryId')->middleware(['throttle:staff-api', 'idempotent'])->name('enquiries.convert');
            Route::get('join-requests', [StaffEnquiryController::class, 'joinRequests'])->name('join-requests.index');
            Route::post('join-requests/{memberId}/approve', [StaffEnquiryController::class, 'approveJoinRequest'])->whereNumber('memberId')->name('join-requests.approve');
            Route::post('join-requests/{memberId}/decline', [StaffEnquiryController::class, 'declineJoinRequest'])->whereNumber('memberId')->name('join-requests.decline');
        });

        // Attendance history, reports and streaks.
        Route::get('attendance', [StaffAttendanceReportController::class, 'history'])->middleware('staff.can:attendance.manage|attendance.reports')->name('attendance.index');
        Route::middleware('staff.can:attendance.reports')->group(function () {
            Route::get('attendance/reports', [StaffAttendanceReportController::class, 'reports'])->name('attendance.reports');
            Route::get('streaks', [StaffAttendanceReportController::class, 'streaks'])->name('streaks');
            Route::post('streaks/recalculate', [StaffAttendanceReportController::class, 'recalculate'])->middleware('throttle:staff-api')->name('streaks.recalculate');
        });

        // A member's weight, programs and QR (anyone who can see the member).
        Route::middleware('staff.can:members.view')->group(function () {
            Route::get('members/{memberId}/qr', [StaffMemberToolsController::class, 'qr'])->whereNumber('memberId')->name('members.qr');
            Route::get('members/{memberId}/weight', [StaffMemberProgressController::class, 'weight'])->whereNumber('memberId')->name('members.weight');
            Route::post('members/{memberId}/weight', [StaffMemberProgressController::class, 'recordWeight'])->whereNumber('memberId')->middleware('throttle:staff-api')->name('members.weight.store');
            Route::delete('members/{memberId}/weight/{logId}', [StaffMemberProgressController::class, 'deleteWeight'])->whereNumber(['memberId', 'logId'])->name('members.weight.destroy');
            Route::put('members/{memberId}/weight-goal', [StaffMemberProgressController::class, 'setGoal'])->whereNumber('memberId')->middleware('throttle:staff-api')->name('members.weight-goal');
            Route::get('progress', [StaffMemberProgressController::class, 'tracking'])->name('progress');
            Route::get('members/{memberId}/programs', [StaffMemberProgramController::class, 'index'])->whereNumber('memberId')->name('members.programs');
        });
        Route::middleware('staff.can:content.custom|content.manage')->group(function () {
            Route::get('programs', [StaffMemberProgramController::class, 'options'])->name('programs.options');
            Route::post('members/{memberId}/programs', [StaffMemberProgramController::class, 'assign'])->whereNumber('memberId')->middleware(['throttle:staff-api', 'idempotent'])->name('members.programs.assign');
            Route::post('members/{memberId}/programs/{assignmentId}/stop', [StaffMemberProgramController::class, 'stop'])->whereNumber(['memberId', 'assignmentId'])->name('members.programs.stop');
        });

        // Measurements, progress photos and AI reports.
        Route::middleware('staff.can:ai.view')->group(function () {
            Route::get('members/{memberId}/ai', [StaffMemberProgressController::class, 'bodyProgress'])->whereNumber('memberId')->name('members.ai');
            Route::post('members/{memberId}/measurements', [StaffMemberProgressController::class, 'storeMeasurement'])->whereNumber('memberId')->middleware('throttle:staff-api')->name('members.measurements.store');
            Route::delete('members/{memberId}/measurements/{measurementId}', [StaffMemberProgressController::class, 'deleteMeasurement'])->whereNumber(['memberId', 'measurementId'])->name('members.measurements.destroy');
            Route::post('members/{memberId}/progress-photos', [StaffMemberProgressController::class, 'storePhoto'])->whereNumber('memberId')->middleware('throttle:staff-api')->name('members.progress-photos.store');
            Route::delete('members/{memberId}/progress-photos/{photoId}', [StaffMemberProgressController::class, 'deletePhoto'])->whereNumber(['memberId', 'photoId'])->name('members.progress-photos.destroy');
            Route::get('members/{memberId}/progress-photos/{photoId}/image', [StaffMemberProgressController::class, 'photoImage'])->whereNumber(['memberId', 'photoId'])->name('members.progress-photos.image');
            Route::post('members/{memberId}/ai/reports', [StaffMemberProgressController::class, 'requestReport'])->whereNumber('memberId')->middleware(['throttle:staff-api', 'idempotent'])->name('members.ai-reports.store');
            Route::get('members/{memberId}/ai/reports/{reportId}', [StaffMemberProgressController::class, 'report'])->whereNumber(['memberId', 'reportId'])->name('members.ai-reports.show');
            Route::post('members/{memberId}/ai/access', [StaffMemberProgressController::class, 'toggleAiAccess'])->whereNumber('memberId')->name('members.ai-access');
        });

        // Announcements (custom notifications) to members. /admin/notifications is kept for the staff inbox (spec section 14).
        Route::middleware('staff.can:announcements.send')->group(function () {
            Route::get('announcements', [StaffAnnouncementController::class, 'index'])->name('announcements.index');
            Route::get('announcements/audience-count', [StaffAnnouncementController::class, 'audience'])->name('announcements.audience-count');
            Route::post('announcements', [StaffAnnouncementController::class, 'store'])->middleware(['throttle:staff-api', 'idempotent'])->name('announcements.store');
            Route::get('announcements/{announcementId}', [StaffAnnouncementController::class, 'show'])->whereNumber('announcementId')->name('announcements.show');
            Route::post('announcements/{announcementId}/send', [StaffAnnouncementController::class, 'send'])->whereNumber('announcementId')->middleware(['throttle:staff-api', 'idempotent'])->name('announcements.send');
            Route::post('announcements/{announcementId}/cancel', [StaffAnnouncementController::class, 'cancel'])->whereNumber('announcementId')->name('announcements.cancel');
        });

        // Gym-admin only: automatic notifications, member phones, AI coach settings, Fitnix billing.
        Route::middleware('staff.can:gym.settings')->group(function () {
            Route::get('automatic-notifications', [StaffAnnouncementController::class, 'automatic'])->name('automatic-notifications.index');
            Route::post('automatic-notifications/pause', [StaffAnnouncementController::class, 'pauseAutomatic'])->name('automatic-notifications.pause');
            Route::put('automatic-notifications/{key}', [StaffAnnouncementController::class, 'saveAutomatic'])->where('key', '[a-z_]+')->middleware('throttle:staff-api')->name('automatic-notifications.update');
            Route::post('automatic-notifications/{key}/toggle', [StaffAnnouncementController::class, 'toggleAutomatic'])->where('key', '[a-z_]+')->name('automatic-notifications.toggle');
            Route::post('automatic-notifications/{key}/reset', [StaffAnnouncementController::class, 'resetAutomatic'])->where('key', '[a-z_]+')->name('automatic-notifications.reset');
            Route::get('members/{memberId}/devices', [StaffMemberToolsController::class, 'devices'])->whereNumber('memberId')->name('members.devices');
            Route::post('members/{memberId}/devices/test', [StaffMemberToolsController::class, 'testPush'])->whereNumber('memberId')->middleware('throttle:staff-api')->name('members.devices.test');
            Route::delete('members/{memberId}/devices/{deviceId}', [StaffMemberToolsController::class, 'removeDevice'])->whereNumber(['memberId', 'deviceId'])->name('members.devices.destroy');
        });
        Route::middleware('staff.can:ai.manage')->group(function () {
            Route::get('ai-coach', [StaffGymAdminToolsController::class, 'aiCoach'])->name('ai-coach');
            Route::put('ai-coach', [StaffGymAdminToolsController::class, 'updateAiCoach'])->middleware('throttle:staff-api')->name('ai-coach.update');
        });
        Route::middleware('staff.can:gym.billing')->group(function () {
            Route::get('billing', [StaffGymAdminToolsController::class, 'billing'])->name('billing');
            Route::get('billing/invoices/{invoiceId}/document', [StaffGymAdminToolsController::class, 'invoiceDocument'])->whereNumber('invoiceId')->name('billing.invoices.document');
        });

        // Trainer management (gym admins).
        Route::prefix('trainers')->name('trainers.')->middleware('staff.can:trainers.manage')->group(function () {
            Route::get('/', [StaffTrainerController::class, 'index'])->name('index');
            Route::post('/', [StaffTrainerController::class, 'store'])->middleware(['throttle:staff-api', 'idempotent'])->name('store');
            Route::get('access-options', [StaffTrainerController::class, 'accessOptions'])->name('access-options');
            Route::get('{trainerId}', [StaffTrainerController::class, 'show'])->whereNumber('trainerId')->name('show');
            Route::put('{trainerId}', [StaffTrainerController::class, 'update'])->whereNumber('trainerId')->middleware('throttle:staff-api')->name('update');
            Route::delete('{trainerId}', [StaffTrainerController::class, 'destroy'])->whereNumber('trainerId')->name('destroy');
            Route::post('{trainerId}/photo', [StaffTrainerController::class, 'uploadPhoto'])->whereNumber('trainerId')->middleware('throttle:staff-api')->name('photo');
            Route::post('{trainerId}/toggle', [StaffTrainerController::class, 'toggle'])->whereNumber('trainerId')->name('toggle');
            Route::post('{trainerId}/password', [StaffTrainerController::class, 'password'])->whereNumber('trainerId')->middleware('throttle:staff-api')->name('password');
            Route::put('{trainerId}/access', [StaffTrainerController::class, 'access'])->whereNumber('trainerId')->name('access');
            Route::post('{trainerId}/members', [StaffTrainerController::class, 'assignMembers'])->whereNumber('trainerId')->middleware('throttle:staff-api')->name('members.assign');
            Route::delete('{trainerId}/members/{memberId}', [StaffTrainerController::class, 'unassignMember'])->whereNumber(['trainerId', 'memberId'])->name('members.unassign');
        });

        // Gym profile, branding and gym-wide settings (gym admins).
        Route::prefix('gym')->name('gym.')->middleware('staff.can:gym.settings')->group(function () {
            Route::get('/', [StaffGymSettingsController::class, 'show'])->name('show');
            Route::put('/', [StaffGymSettingsController::class, 'update'])->middleware('throttle:staff-api')->name('update');
            Route::post('logo', [StaffGymSettingsController::class, 'uploadLogo'])->middleware('throttle:staff-api')->name('logo');
            Route::delete('logo', [StaffGymSettingsController::class, 'deleteLogo'])->name('logo.destroy');
            Route::post('banner', [StaffGymSettingsController::class, 'uploadBanner'])->middleware('throttle:staff-api')->name('banner');
            Route::delete('banner', [StaffGymSettingsController::class, 'deleteBanner'])->name('banner.destroy');
            Route::post('photos', [StaffGymSettingsController::class, 'uploadPhotos'])->middleware('throttle:staff-api')->name('photos.store');
            Route::delete('photos/{photoId}', [StaffGymSettingsController::class, 'deletePhoto'])->whereNumber('photoId')->name('photos.destroy');
            Route::put('theme/{area}', [StaffGymSettingsController::class, 'updateTheme'])->whereIn('area', ['admin', 'member'])->middleware('throttle:staff-api')->name('theme.update');
            Route::delete('theme/{area}', [StaffGymSettingsController::class, 'resetTheme'])->whereIn('area', ['admin', 'member'])->name('theme.reset');
            Route::put('attendance-settings', [StaffGymSettingsController::class, 'attendanceSettings'])->middleware('throttle:staff-api')->name('attendance-settings');
            Route::put('rules', [StaffGymSettingsController::class, 'updateRules'])->middleware('throttle:staff-api')->name('rules');
            Route::get('qr', [StaffGymSettingsController::class, 'qr'])->name('qr');
            Route::put('qr/check-in-mode', [StaffGymSettingsController::class, 'checkInQrMode'])->middleware('throttle:staff-api')->name('qr.check-in-mode');
            Route::post('qr/regenerate', [StaffGymSettingsController::class, 'regenerateQr'])->middleware('throttle:staff-api')->name('qr.regenerate');
            Route::get('cities', [StaffGymSettingsController::class, 'cities'])->name('cities');
            Route::get('cities/{cityId}/localities', [StaffGymSettingsController::class, 'localities'])->whereNumber('cityId')->name('cities.localities');
        });
    });
});
