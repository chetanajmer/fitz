<?php

namespace App\Support;

use App\Services\Push\FcmClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Is the server set up to run things on its own? The scheduler (cron "schedule:run" every minute)
 * and the queue worker each leave a heartbeat; Firebase and PHP are checked directly.
 */
class SystemHealth
{
    public const SCHEDULER = 'scheduler';

    public const QUEUE = 'queue';

    /** A heartbeat older than this means it is not running. */
    public const STALE_AFTER_MINUTES = 3;

    public static function beat(string $what): void
    {
        Cache::forever('health.'.$what, now()->toIso8601String());
    }

    public static function lastBeat(string $what): ?Carbon
    {
        $value = Cache::get('health.'.$what);

        return $value ? Carbon::parse($value) : null;
    }

    /**
     * @return array<string, array{ok: bool, label: string, detail: string}>
     */
    public function report(): array
    {
        $scheduler = self::lastBeat(self::SCHEDULER);
        $queue = self::lastBeat(self::QUEUE);
        $fresh = fn (?Carbon $at) => $at !== null && $at->gt(now()->subMinutes(self::STALE_AFTER_MINUTES));

        try {
            $waiting = DB::table('jobs')->count();
            $failed = DB::table('failed_jobs')->count();
        } catch (Throwable) {
            [$waiting, $failed] = [0, 0];
        }

        return [
            'scheduler' => [
                'ok' => $fresh($scheduler),
                'label' => 'Scheduler (cron)',
                'detail' => $scheduler
                    ? 'Last ran '.$scheduler->diffForHumans().' ('.$scheduler->format('d M, h:i:s A').')'
                    : 'Has never run. Add a cron job every minute: cd '.base_path().' && <PHP 8.3 path> artisan schedule:run',
            ],
            'queue' => [
                'ok' => $fresh($queue),
                'label' => 'Queue (sends notifications)',
                'detail' => ($queue ? 'Last worked '.$queue->diffForHumans() : 'Has never run (it is started by the scheduler)')
                    .' · '.$waiting.' waiting'.($failed ? ' · '.$failed.' failed' : ''),
            ],
            'firebase' => [
                'ok' => app(FcmClient::class)->isConfigured(),
                'label' => 'Firebase (push to phones)',
                'detail' => app(FcmClient::class)->isConfigured()
                    ? 'Service-account file found'
                    : 'Not set up: FIREBASE_CREDENTIALS is missing or the file is not there',
            ],
            'php' => [
                'ok' => PHP_VERSION_ID >= 80300,
                'label' => 'PHP',
                'detail' => PHP_VERSION.' ('.PHP_BINARY.')',
            ],
        ];
    }
}
