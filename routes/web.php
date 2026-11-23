<?php

use App\Http\Controllers\AttendanceScanController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\PublicGymController;
use App\Http\Controllers\QrCodeController;
use App\Livewire;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public & auth
|--------------------------------------------------------------------------
*/
Route::view('/', 'welcome')->name('home');

Route::middleware('guest')->group(function () {
    Route::livewire('/login', Livewire\Auth\Login::class)->name('login');
    Route::livewire('/register-gym', Livewire\Auth\RegisterGym::class)->name('register.gym');
});

Route::post('/logout', LogoutController::class)->middleware('auth')->name('logout');

// Super Admin magic links ("login as"). Single-use, short-lived tokens — see ImpersonationService.
Route::get('/login-as/{token}', [ImpersonationController::class, 'consume'])->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:20,1')->name('impersonate.consume');
Route::post('/login-as/leave', [ImpersonationController::class, 'leave'])->middleware('auth')->name('impersonate.leave');

// QR registration link: always creates an Enquiry for the gym that owns the token.
Route::get('/join/{token}', [PublicGymController::class, 'join'])->where('token', '[A-Za-z0-9]{20,64}')->name('gym.join');

// Website sign-up for new members: city → gym → details → phone OTP. Creates a pending member.
Route::livewire('/join-gym', Livewire\PublicSite\MemberSignup::class)->name('member.join');
Route::livewire('/join-gym/status', Livewire\PublicSite\JoinStatus::class)->name('member.join.status');

// Member self-registration QR (shown on the attendance desk): creates a member without a membership.
Route::get('/register/{token}', [PublicGymController::class, 'register'])->where('token', '[A-Za-z0-9]{20,64}')->name('gym.register');

Route::view('/suspended', 'gym-suspended')->middleware('auth')->name('gym.suspended');

/*
|--------------------------------------------------------------------------
| Gym panel — gym_admin & trainer
|--------------------------------------------------------------------------
*/
Route::prefix('app')->name('gym.')
    ->middleware(['auth', 'active', 'role:gym_admin|trainer', 'gym.operational'])
    ->group(function () {
        Route::livewire('/dashboard', Livewire\Gym\Dashboard::class)->name('dashboard');

        // Members (trainers: read-only, assigned members only — enforced by MemberPolicy & scopes)
        Route::livewire('/members', Livewire\Gym\Members\Index::class)->middleware('permission:members.view')->name('members.index');
        Route::livewire('/members/create', Livewire\Gym\Members\Form::class)->middleware('permission:members.manage')->name('members.create');
        Route::livewire('/members/import', Livewire\Gym\Members\Import::class)->middleware('permission:members.import')->name('members.import');
        Route::get('/members/import/sample', [DocumentController::class, 'importSample'])->middleware('permission:members.import')->name('members.import.sample');
        Route::livewire('/members/{member}', Livewire\Gym\Members\Show::class)->middleware('permission:members.view')->name('members.show');
        Route::livewire('/members/{member}/edit', Livewire\Gym\Members\Form::class)->middleware('permission:members.manage')->name('members.edit');
        Route::get('/members/{member}/qr', [QrCodeController::class, 'member'])->middleware('permission:members.view')->name('members.qr');

        Route::middleware('permission:enquiries.manage')->group(function () {
            Route::livewire('/enquiries', Livewire\Gym\Enquiries\Index::class)->name('enquiries.index');
            Route::livewire('/enquiries/{enquiry}', Livewire\Gym\Enquiries\Show::class)->name('enquiries.show');
        });

        Route::livewire('/plans', Livewire\Gym\Plans\Index::class)->middleware('permission:plans.manage')->name('plans.index');
        Route::livewire('/memberships', Livewire\Gym\Memberships\Index::class)->middleware('permission:memberships.manage')->name('memberships.index');

        Route::middleware('permission:payments.manage')->group(function () {
            Route::livewire('/payments', Livewire\Gym\Payments\Index::class)->name('payments.index');
            Route::get('/payments/{payment}/receipt', [DocumentController::class, 'receipt'])->name('payments.receipt');
            Route::get('/memberships/{membership}/invoice', [DocumentController::class, 'membershipInvoice'])->name('memberships.invoice');
        });

        Route::middleware('permission:attendance.manage')->group(function () {
            Route::livewire('/attendance', Livewire\Gym\Attendance\Index::class)->name('attendance.index');
            Route::get('/attendance/register-qr', [QrCodeController::class, 'register'])->name('attendance.register-qr');
            Route::livewire('/qr-codes', Livewire\Gym\QrCodes::class)->name('qr-codes');
            // Staff scan a member's QR with any phone camera: the QR encodes this URL.
            Route::get('/attendance/scan/{token}', AttendanceScanController::class)->where('token', '[A-Za-z0-9]{20,64}')->name('attendance.scan');
        });
        Route::livewire('/attendance/reports', Livewire\Gym\Attendance\Reports::class)->middleware('permission:attendance.reports')->name('attendance.reports');
        // Diet charts & workout plans (content.custom: admins + trainers; finer rules in FitnessPlanPolicy).
        Route::middleware('permission:content.custom')->group(function () {
            Route::livewire('/content/plans/{plan}', Livewire\Gym\Content\Show::class)->name('content.show');
            Route::livewire('/programs', Livewire\Gym\Content\Programs::class)->name('programs');
            Route::livewire('/content/plans/{plan}/edit', Livewire\Gym\Content\Editor::class)->name('content.edit');
            Route::livewire('/content/{type}', Livewire\Gym\Content\Library::class)->whereIn('type', ['diet', 'workout'])->name('content.index');
            Route::livewire('/content/{type}/create', Livewire\Gym\Content\Editor::class)->whereIn('type', ['diet', 'workout'])->name('content.create');
        });

        Route::livewire('/progress', Livewire\Gym\Progress::class)->middleware('permission:members.view')->name('progress');
        Route::livewire('/streaks', Livewire\Gym\Streaks::class)->middleware('permission:attendance.reports')->name('streaks');
        Route::livewire('/notifications', Livewire\Gym\Announcements::class)->middleware('permission:announcements.send')->name('announcements');

        Route::middleware('permission:trainers.manage')->group(function () {
            Route::livewire('/trainers', Livewire\Gym\Trainers\Index::class)->name('trainers.index');
            Route::livewire('/trainers/{trainer}', Livewire\Gym\Trainers\Show::class)->name('trainers.show');
        });

        Route::middleware('permission:gym.settings')->group(function () {
            Route::livewire('/settings', Livewire\Gym\Settings\Profile::class)->name('settings');
            Route::get('/settings/join-qr', [QrCodeController::class, 'join'])->name('settings.join-qr');
        });

        Route::middleware('permission:gym.billing')->group(function () {
            Route::livewire('/billing', Livewire\Gym\Billing\Index::class)->name('billing');
            Route::get('/billing/{invoice}', [DocumentController::class, 'gymSaasInvoice'])->name('billing.invoice');
        });

        Route::livewire('/account', Livewire\Account::class)->name('account');
    });

/*
|--------------------------------------------------------------------------
| Member portal
|--------------------------------------------------------------------------
*/
Route::prefix('me')->name('portal.')
    ->middleware(['auth', 'active', 'role:member', 'gym.operational'])
    ->group(function () {
        Route::livewire('/', Livewire\Portal\Dashboard::class)->name('dashboard');
        Route::get('/qr', [QrCodeController::class, 'self'])->name('qr');
        Route::livewire('/plans/view/{plan}', Livewire\Portal\PlanShow::class)->whereNumber('plan')->name('plans.show');
        Route::livewire('/plans/{type}', Livewire\Portal\Plans::class)->whereIn('type', ['diet', 'workout'])->name('plans');
        Route::get('/receipts/{payment}', [DocumentController::class, 'memberReceipt'])->name('receipt');
        Route::livewire('/account', Livewire\Account::class)->name('account');
    });

/*
|--------------------------------------------------------------------------
| Super Admin platform
|--------------------------------------------------------------------------
*/
Route::prefix('platform')->name('platform.')
    ->middleware(['auth', 'active', 'role:super_admin'])
    ->group(function () {
        Route::livewire('/', Livewire\Platform\Dashboard::class)->name('dashboard');
        Route::livewire('/gyms', Livewire\Platform\Gyms\Index::class)->name('gyms.index');
        Route::livewire('/gyms/{gym:id}', Livewire\Platform\Gyms\Show::class)->name('gyms.show');
        Route::livewire('/leads', Livewire\Platform\Leads::class)->name('leads');
        Route::livewire('/library', Livewire\Platform\Library::class)->name('library');
        Route::livewire('/notifications', Livewire\Platform\Notifications::class)->name('notifications');
        Route::livewire('/pricing', Livewire\Platform\Pricing::class)->name('pricing');
        Route::livewire('/cities', Livewire\Platform\Cities::class)->name('cities');
        Route::livewire('/invoices', Livewire\Platform\Invoices::class)->name('invoices');
        Route::get('/invoices/{invoice}', [DocumentController::class, 'platformSaasInvoice'])->name('invoices.show');
        Route::livewire('/settings', Livewire\Platform\Settings::class)->name('settings');
        Route::livewire('/account', Livewire\Account::class)->name('account');
    });

/*
|--------------------------------------------------------------------------
| Public gym profile — must stay last (catch-all slug). Reserved slugs are
| excluded at slug generation time (config/gymai.php).
|--------------------------------------------------------------------------
*/
Route::get('/{gym:slug}', [PublicGymController::class, 'show'])->where('gym', '[a-z0-9-]+')->name('gym.public');
