<?php

namespace App\Jobs;

use App\Models\Gym;
use App\Models\MemberNotification;
use App\Services\Push\FcmClient;
use App\Services\PushNotificationService;
use App\Tenancy\TenantManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Pushes one stored member notification to all of the member's phones through Firebase. */
class SendPushNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $backoff = 60;

    public function __construct(public int $notificationId, public int $gymId) {}

    public function handle(TenantManager $tenant, PushNotificationService $notifications, FcmClient $fcm): void
    {
        if (! $fcm->isConfigured()) {
            return; // Not set up yet: the notification is still in the member's in-app list.
        }

        $gym = Gym::find($this->gymId);

        if (! $gym) {
            return;
        }

        $tenant->runAs($gym, function () use ($notifications, $fcm) {
            $notification = MemberNotification::find($this->notificationId);

            if ($notification && ! $notification->pushed_at) {
                $notifications->push($notification, $fcm);
            }
        });
    }
}
