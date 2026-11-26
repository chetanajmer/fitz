<?php

namespace App\Providers;

use App\Contracts\SmsSender;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureGymIsOperational;
use App\Models\FitnessPlan;
use App\Models\Member;
use App\Models\PersonalAccessToken;
use App\Models\SaasInvoice;
use App\Models\User;
use App\Policies\FitnessPlanPolicy;
use App\Policies\MemberPolicy;
use App\Policies\SaasInvoicePolicy;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\SmartPingSmsSender;
use App\Services\StreakService;
use App\Tenancy\TenantManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One tenant context per request / job (scoped resets between Octane requests and queue jobs).
        $this->app->scoped(TenantManager::class);
        $this->app->scoped(StreakService::class);

        // SMS provider for OTPs: "smartping" sends real SMS, "log" writes them to the log.
        $this->app->bind(SmsSender::class, fn () => match (config('gymai.sms.driver')) {
            'smartping' => new SmartPingSmsSender,
            default => new LogSmsSender,
        });
    }

    public function boot(): void
    {
        // Super Admin has every ability platform-wide.
        Gate::before(fn ($user) => $user instanceof User && $user->isSuperAdmin() ? true : null);

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // Abuse protection for OTP endpoints (per IP), on top of per-phone limits in MemberOtpService.
        RateLimiter::for('otp-send', fn (Request $request) => Limit::perMinute(5)->by('otp-send:'.$request->ip()));
        RateLimiter::for('otp-verify', fn (Request $request) => Limit::perMinute(10)->by('otp-verify:'.$request->ip()));

        Gate::policy(Member::class, MemberPolicy::class);
        Gate::policy(SaasInvoice::class, SaasInvoicePolicy::class);
        Gate::policy(FitnessPlan::class, FitnessPlanPolicy::class);

        // Re-apply access middleware on every Livewire update request, not just the initial page load.
        Livewire::addPersistentMiddleware([
            EnsureAccountIsActive::class,
            EnsureGymIsOperational::class,
            RoleMiddleware::class,
            PermissionMiddleware::class,
        ]);
    }
}
