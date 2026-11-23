<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Models\Gym;
use App\Services\PushNotificationService;
use App\Tenancy\TenantManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Delivers a gym announcement to its audience (one in-app notification + push per member). */
class SendAnnouncement implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public function __construct(public int $announcementId, public int $gymId) {}

    public function handle(TenantManager $tenant, PushNotificationService $notifications): void
    {
        $gym = Gym::find($this->gymId);

        if (! $gym) {
            return;
        }

        $tenant->runAs($gym, function () use ($notifications) {
            $announcement = Announcement::find($this->announcementId);

            if ($announcement) {
                $notifications->deliver($announcement);
            }
        });
    }
}
