<?php

use App\Exceptions\ApiException;
use App\Jobs\SchedulerHeartbeat;
use App\Models\AiReport;
use App\Models\Announcement;
use App\Models\Gym;
use App\Models\GymAiSetting;
use App\Models\Member;
use App\Models\Scopes\GymScope;
use App\Services\AiAccessService;
use App\Services\AiReportService;
use App\Services\BillingService;
use App\Services\GymProfileService;
use App\Services\MembershipService;
use App\Services\Push\FcmClient;
use App\Services\Push\PushMessage;
use App\Services\PushNotificationService;
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
// Heartbeats (see gymai:health and Super Admin -> App Notifications -> Rules): the scheduler
// writes one directly, the queued job proves the queue worker runs too.
Schedule::call(fn () => SystemHealth::beat(SystemHealth::SCHEDULER))->everyMinute()->name('scheduler-heartbeat');
Schedule::job(new SchedulerHeartbeat)->everyMinute()->name('queue-heartbeat');
// Shared hosting has no queue supervisor: the cron that runs schedule:run every minute also works the queue.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=2')->everyMinute()->withoutOverlapping();
