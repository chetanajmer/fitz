<?php

use App\Exceptions\ApiException;
use App\Jobs\SchedulerHeartbeat;
use App\Models\AiReport;
use App\Models\Announcement;
use App\Models\Gym;
use App\Models\GymAiSetting;
use App\Models\IdempotencyKey;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Scopes\GymScope;
use App\Services\AiAccessService;
use App\Services\AiReportService;
use App\Services\BillingService;
use App\Services\GymProfileService;
use App\Services\MemberOtpService;
use App\Services\MemberService;
use App\Services\MembershipService;
use App\Services\Push\FcmClient;
use App\Services\Push\PushMessage;
use App\Services\PushNotificationService;
use App\Services\ReminderService;
use App\Services\StreakService;
use App\Support\NotificationCatalog;
use App\Support\SystemHealth;
use App\Tenancy\TenantManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('gymai:refresh-memberships', function (MembershipService $memberships) {
    $result = $memberships->refreshStatuses();
    $this->info("Expired: {$result['expired']} · Activated: {$result['activated']}");
})->purpose('Sync stored membership statuses (upcoming → active → expired) with their dates');

Artisan::command('gymai:generate-invoices {month? : Billing month as YYYY-MM (defaults to last month)}', function (BillingService $billing) {
    $month = $this->argument('month')
        ? Carbon::createFromFormat('Y-m-d', $this->argument('month').'-01')
        : Carbon::today()->subMonthNoOverflow();

    $result = $billing->generateForAllGyms($month);
    $this->info("Invoices for {$month->format('F Y')}: {$result['created']} created, {$result['skipped']} already existed.");
})->purpose('Generate monthly SaaS invoices for every gym');

Artisan::command('gymai:refresh-streaks {gym? : Gym id (defaults to all gyms)}', function (StreakService $streaks) {
    $gyms = Gym::query()->when($this->argument('gym'), fn ($q, $id) => $q->whereKey($id))->get();
    $total = 0;
    foreach ($gyms as $gym) {
        $total += app(TenantManager::class)->runAs($gym, fn () => $streaks->refreshGym($gym));
    }
    $this->info("Streaks recalculated for {$total} member(s) in {$gyms->count()} gym(s).");
})->purpose('Recalculate attendance streaks (breaks streaks that lapsed overnight)');

// ---- Push notifications to the member app (one gym at a time, each push sent once: member_push_logs) ----
$eachGym = function (callable $send): int {
    $total = 0;
    foreach (Gym::query()->operational()->get() as $gym) {
        $total += app(TenantManager::class)->runAs($gym, fn () => $send($gym));
    }

    return $total;
};

Artisan::command('push:run', function (PushNotificationService $notifications) use ($eachGym) {
    $sent = $eachGym(function (Gym $gym) use ($notifications) {
        $total = 0;
        foreach ($notifications->dueForGym($gym) as $type) {
            $total += $notifications->runScheduled($gym, $type);
        }

        return $total;
    });
    $this->info("Sent: {$sent}");
})->purpose('Send the scheduled member notifications that are due now (times and rules set per gym)');

// Manual runs of one scheduled notification for every gym (ignores the configured time, never sends twice).
foreach ([
    'push:membership-expiring' => 'membership_expiring',
    'push:membership-expired' => 'membership_expired',
    'push:weigh-in-due' => 'weigh_in_due',
    'push:workout-reminder' => 'workout_reminder',
    'push:streak-at-risk' => 'streak_at_risk',
    'push:balance-due' => 'balance_due',
] as $signature => $type) {
    Artisan::command($signature, function (PushNotificationService $notifications) use ($eachGym, $type) {
        $sent = $eachGym(fn (Gym $gym) => $notifications->runScheduled($gym, $type));
        $this->info("Sent: {$sent}");
    })->purpose('Push now: '.NotificationCatalog::TYPES[$type]['label']);
}

// Personal reminders the member set in the app (water, meals, workout). Not saved to the notification list.
foreach ([
    'push:hydration' => ['sendHydration', 'Push the water reminders that are due'],
    'push:meal-reminders' => ['sendMeals', 'Push the diet meal reminders that are due'],
    'push:workout' => ['sendWorkouts', 'Push the personal workout reminders that are due'],
    'push:build-meal-reminders' => ['buildMealRemindersForGym', "Build today's meal reminders from each member's diet program"],
] as $signature => [$method, $purpose]) {
    Artisan::command($signature, function (ReminderService $reminders) use ($eachGym, $method) {
        $count = $eachGym(fn (Gym $gym) => $reminders->{$method}($gym));
        $this->info("Done: {$count}");
    })->purpose($purpose);
}

Artisan::command('push:test {member_id : Member id} {--image= : Optional full public URL of a picture}', function (PushNotificationService $notifications) {
    $member = Member::withoutGlobalScope(GymScope::class)->with('gym')->findOrFail($this->argument('member_id'));
    $image = $this->option('image') ?: null;

    if ($image && ! FcmClient::publicImageUrl($image)) {
        $this->warn('The picture URL is not public (localhost, a private address or not http/https): phones cannot load it, so it is not sent.');
    }

    $notification = app(TenantManager::class)->runAs($member->gym, fn () => $notifications->notify($member, PushMessage::make(
        'test',
        'Test from '.$member->gym->name,
        "Hi {$member->name}, this is a test notification from {$member->gym->name}.\n"
            ."If you can read this on your phone, push notifications are working.\n"
            ."Tap it to open the full message in the app: the whole text is shown on the detail page.\n"
            .'Sent '.now()->format('d M Y, h:i A').'.',
        [],
        null,
        $image,
    )));

    $devices = app(TenantManager::class)->runAs($member->gym, fn () => $member->devices()->count());
    $this->info("Saved notification #{$notification->id} for {$member->name} and queued it for {$devices} phone(s).");
    if (! app(FcmClient::class)->isConfigured()) {
        $this->warn('Firebase is not configured (FIREBASE_CREDENTIALS): it is only saved to the in-app list.');
    }
})->purpose('Send a test push notification (long text, optional picture) to a member\'s phones');

Artisan::command('gymai:send-announcements', function (PushNotificationService $notifications) {
    $due = Announcement::withoutGlobalScope(GymScope::class)->where('status', Announcement::SCHEDULED)->where('scheduled_at', '<=', now())->get();
    $members = 0;
    foreach ($due as $announcement) {
        $gym = Gym::find($announcement->gym_id);
        if ($gym) {
            $members += app(TenantManager::class)->runAs($gym, fn () => $notifications->deliver(Announcement::find($announcement->id)));
        }
    }
    $this->info("Announcements sent: {$due->count()} to {$members} member(s).");
})->purpose('Send scheduled gym announcements that are due');

Artisan::command('gymai:ai-monthly-reports', function (AiAccessService $access, AiReportService $reports) {
    if (! $access->platformEnabled()) {
        $this->info('AI coach is switched off for the platform.');

        return;
    }

    $requested = 0;
    $gymIds = GymAiSetting::withoutGlobalScope(GymScope::class)->where(['allowed' => true, 'enabled' => true, 'auto_monthly_report' => true])->pluck('gym_id');
    foreach (Gym::query()->operational()->whereIn('id', $gymIds)->get() as $gym) {
        $requested += app(TenantManager::class)->runAs($gym, function () use ($reports) {
            $count = 0;
            Member::active()->whereNotNull('ai_consent_at')->each(function (Member $member) use ($reports, &$count) {
                try {
                    $reports->request($member, AiReport::PROGRESS, 'auto');
                    $count++;
                } catch (ApiException) {
                    // Not eligible, or a limit is reached: skip this member.
                }
            });

            return $count;
        });
    }
    $this->info("AI progress reports requested: {$requested}.");
})->purpose('Queue the monthly AI progress report for consenting members of gyms that switched it on');

Artisan::command('gymai:optimize-branding {gym? : Gym id (defaults to all gyms)}', function (GymProfileService $profiles) {
    $disk = Storage::disk('public');
    $gyms = Gym::query()->when($this->argument('gym'), fn ($q, $id) => $q->whereKey($id))->get();
    $done = 0;
    foreach ($gyms as $gym) {
        foreach (['logo' => 'logo_path', 'banner' => 'banner_path'] as $kind => $column) {
            $path = $gym->{$column};
            if (! $path || ! $disk->exists($path)) {
                continue;
            }
            $before = $disk->size($path);
            if ($before <= GymProfileService::IMAGE_RULES[$kind]['max_kb'] * 1024) {
                continue; // already within its budget
            }
            try {
                $gym->{$column} = $profiles->storeOptimized($gym, $disk->path($path), $kind, $path);
                $gym->save();
                $after = $disk->size($gym->{$column});
                $this->info("{$gym->name}: {$kind} ".round($before / 1024).' KB -> '.round($after / 1024).' KB');
                $done++;
            } catch (Throwable $e) {
                $this->warn("{$gym->name}: {$kind} skipped (".$e->getMessage().')');
            }
        }
    }
    $this->info("Compressed {$done} picture(s).");
})->purpose('Compress existing gym logos (max 70 KB) and banners (max 500 KB)');

Artisan::command('gymai:health', function (SystemHealth $health) {
    foreach ($health->report() as $check) {
        $this->line(($check['ok'] ? '<info>OK  </info> ' : '<error>FAIL</error> ').str_pad($check['label'], 30).$check['detail']);
    }
})->purpose('Is the cron scheduler, the queue, Firebase and PHP working on this server?');

Schedule::command('gymai:refresh-memberships')->dailyAt('00:10');
Schedule::command('gymai:refresh-streaks')->dailyAt('00:20');
Schedule::command('gymai:generate-invoices')->monthlyOn(1, '02:00');
Artisan::command('gymai:ai-release-stuck', function (AiReportService $reports) {
    $this->info('AI reports released: '.$reports->releaseStuck().'.');
})->purpose('Fail AI reports that were never processed, so their credits are free again');

Schedule::command('gymai:ai-release-stuck')->hourly();
Schedule::command('gymai:ai-monthly-reports')->monthlyOn(1, '07:00');
// Member notifications: each gym's own times (Notifications page), checked every 5 minutes.
Schedule::command('push:run')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('gymai:send-announcements')->everyMinute()->withoutOverlapping();
// Personal reminders (member app): due ones every 5 minutes; meal reminders are rebuilt just after midnight.
Schedule::command('push:hydration')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('push:meal-reminders')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('push:workout')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('push:build-meal-reminders')->dailyAt('00:05')->withoutOverlapping()->onOneServer();
// Heartbeats (see gymai:health and Super Admin -> App Notifications -> Rules): the scheduler
// writes one directly, the queued job proves the queue worker runs too.
Schedule::call(fn () => SystemHealth::beat(SystemHealth::SCHEDULER))->everyMinute()->name('scheduler-heartbeat');
Schedule::job(new SchedulerHeartbeat)->everyMinute()->name('queue-heartbeat');
// Shared hosting has no queue supervisor: the cron that runs schedule:run every minute also works the queue.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=2')->everyMinute()->withoutOverlapping();
// Staff app: stored Idempotency-Key responses are kept for 24 hours.
Schedule::call(fn () => IdempotencyKey::where('created_at', '<', now()->subHours(IdempotencyKey::TTL_HOURS))->delete())->hourly()->name('prune-idempotency-keys');

// Store review account: the member that OTP_DEMO_PHONE logs in as (fixed OTP_DEMO_CODE, no SMS). Safe to run again.
Artisan::command('gymai:demo-member {gym : Gym id or slug} {--name=Play Store Review}', function (MemberService $members, MembershipService $memberships) {
    $phone = Member::normalizePhone((string) config('gymai.otp.demo_phone'));

    if (! MemberOtpService::isDemoPhone($phone)) {
        $this->error('Set OTP_DEMO_PHONE and OTP_DEMO_CODE (4-8 digits) in .env first, then run php artisan config:clear.');

        return 1;
    }

    $gym = Gym::query()->where('id', $this->argument('gym'))->orWhere('slug', $this->argument('gym'))->first();

    if (! $gym?->isOperational()) {
        $this->error('No active gym found for "'.$this->argument('gym').'".');

        return 1;
    }

    $elsewhere = Member::withoutGlobalScope(GymScope::class)->where('phone_digits', $phone)->where('gym_id', '!=', $gym->id)->where('status', 'active')->count();

    return app(TenantManager::class)->runAs($gym, function () use ($gym, $phone, $members, $memberships, $elsewhere) {
        $member = Member::where('phone_digits', $phone)->first()
            ?? $members->create(['name' => $this->option('name'), 'phone' => $phone, 'joining_date' => now()->toDateString(), 'status' => 'active', 'notes' => 'Store review account (OTP_DEMO_PHONE).']);
        $member->forceFill(['status' => 'active'])->save();

        if (! Member::whereKey($member->id)->withValidMembership()->exists()) {
            $plan = MembershipPlan::active()->get()->sortByDesc(fn (MembershipPlan $plan) => $memberships->calculateEndDate($plan, now()->startOfDay()))->first();

            if (! $plan) {
                $this->error("{$gym->name} has no active membership plan. Create one, then run this again.");

                return 1;
            }

            // Fully discounted, so the review account adds no revenue or pending fees.
            $memberships->assign($member, $plan, null, (float) $plan->price, 'Store review account');
            $this->line("Assigned \"{$plan->name}\" (free).");
        }

        $this->info("Demo member ready: {$member->name} ({$member->member_code}) at {$gym->name}. Log in with {$phone} and OTP ".config('gymai.otp.demo_code').'.');

        if ($elsewhere) {
            $this->warn("This number is also an active member of {$elsewhere} other gym(s): the app will ask which gym to log in to.");
        }

        return 0;
    });
})->purpose('Create or refresh the store review member for OTP_DEMO_PHONE');
