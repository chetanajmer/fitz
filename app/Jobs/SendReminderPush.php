<?php

namespace App\Jobs;

use App\Models\Gym;
use App\Models\MemberDevice;
use App\Services\Push\FcmClient;
use App\Tenancy\TenantManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * A personal reminder (water, meal, workout) pushed straight to the member's phones. Unlike other
 * notifications it is not saved to the in-app notification list, which it would flood.
 */
class SendReminderPush implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** @param  array<string, scalar>  $data */
    public function __construct(
        public int $gymId,
        public int $memberId,
        public string $title,
        public string $body,
        public array $data,
        public string $ttl,
    ) {}

    public function handle(TenantManager $tenant, FcmClient $fcm): void
    {
        $gym = Gym::find($this->gymId);

        if (! $gym || ! $fcm->isConfigured()) {
            return;
        }

        $tenant->runAs($gym, function () use ($fcm) {
            foreach (MemberDevice::where('member_id', $this->memberId)->get() as $device) {
                $result = $fcm->send($device->token, $this->title, $this->body, $this->data + ['gym_id' => $this->gymId],
                    (string) ($this->data['channel'] ?? 'reminders'), null, ['ttl' => $this->ttl, 'visibility' => 'PUBLIC']);

                if ($result === FcmClient::INVALID_TOKEN) {
                    $device->delete();
                }
            }
        });
    }
}
