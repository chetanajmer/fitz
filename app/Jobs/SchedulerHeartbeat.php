<?php

namespace App\Jobs;

use App\Support\SystemHealth;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Queued every minute by the scheduler. When a queue worker runs it, we know queued work
 * (push notifications, announcements) is being processed. See SystemHealth.
 */
class SchedulerHeartbeat implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function handle(): void
    {
        SystemHealth::beat(SystemHealth::QUEUE);
    }
}
