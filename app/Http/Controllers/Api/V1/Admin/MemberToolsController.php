<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ApiException;
use App\Models\MemberDevice;
use App\Models\MemberNotification;
use App\Services\MemberImportService;
use App\Services\Push\FcmClient;
use App\Services\Push\PushMessage;
use App\Services\PushNotificationService;
use App\Services\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Smaller member tools of the web panel:
 *  - a member's own check-in QR (members.view);
 *  - the phones a member's app is signed in on, with a test push (gym admins: gym.settings, as on the web);
 *  - bulk import from CSV / Excel (members.import).
 */
class MemberToolsController extends AdminController
{
    /** GET /admin/members/{id}/qr — the member's attendance QR: "payload" to draw, "svg" ready to show. */
    public function qr(Request $request, int $memberId, QrCodeService $qr): JsonResponse
    {
        $member = $this->visibleMemberOr404($request, $memberId);
        $payload = route('gym.attendance.scan', $member->qr_token);

        return $this->success([
            'member' => $this->memberBrief($member),
            'payload' => $payload,
            'svg' => $qr->svg($payload, 400),
        ], 'Member QR.');
    }

    /** GET /admin/members/{id}/devices — phones with the app, newest first, and the last push that reached a phone. */
    public function devices(Request $request, int $memberId, FcmClient $fcm): JsonResponse
    {
        return $this->success($this->devicesData($this->visibleMemberOr404($request, $memberId)->id, $fcm), 'Devices.');
    }

    /** POST /admin/members/{id}/devices/test — send a test notification to every phone of the member. */
    public function testPush(Request $request, int $memberId, PushNotificationService $notifications, FcmClient $fcm): JsonResponse
    {
        $member = $this->visibleMemberOr404($request, $memberId);

        if (! $member->devices()->exists()) {
            throw new ApiException("{$member->name} has not signed in to the app on any phone.", 'NO_DEVICES', 409);
        }

        $notifications->notify($member, PushMessage::make('test', 'Test from '.current_gym()->name, 'Push notifications are working on this phone.'));

        return $this->success($this->devicesData($member->id, $fcm), "Test notification sent to {$member->name}'s phone.");
    }

    /** DELETE /admin/members/{id}/devices/{deviceId} — it registers again the next time the app opens. */
    public function removeDevice(Request $request, int $memberId, int $deviceId, PushNotificationService $notifications, FcmClient $fcm): JsonResponse
    {
        $member = $this->visibleMemberOr404($request, $memberId);
        $device = MemberDevice::where('member_id', $member->id)->find($deviceId)
            ?? throw new ApiException('Device not found.', 'NOT_FOUND', 404);

        $notifications->removeDevice($member, $device->token);

        return $this->success($this->devicesData($member->id, $fcm), 'Phone removed. It registers again the next time the app opens.');
    }

    /** GET /admin/members/import — the columns the file may have, and a sample CSV. */
    public function importFormat(MemberImportService $importer): JsonResponse
    {
        return $this->success([
            'columns' => MemberImportService::COLUMNS,
            'required' => ['name', 'phone'],
            'accepted' => ['csv', 'xlsx'],
            'max_kb' => 5120,
            'sample_csv' => $importer->sampleCsv(),
        ], 'Import format.');
    }

    /** POST /admin/members/import (multipart: file, CSV or XLSX ≤ 5 MB) — returns { imported, skipped, errors }. */
    public function import(Request $request, MemberImportService $importer): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:5120', 'extensions:csv,xlsx,txt']]);
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        $result = $importer->import($file->getRealPath(), $extension === 'xlsx' ? 'xlsx' : 'csv');

        return $this->success($result, "{$result['imported']} member(s) imported.");
    }

    /** @return array<string, mixed> */
    protected function devicesData(int $memberId, FcmClient $fcm): array
    {
        $last = MemberNotification::where('member_id', $memberId)->whereNotNull('pushed_at')->latest('pushed_at')->first();

        return [
            'push_ready' => $fcm->isConfigured(),
            'devices' => MemberDevice::where('member_id', $memberId)->latest('last_seen_at')->get()->map(fn (MemberDevice $d) => [
                'id' => $d->id,
                'platform' => $d->platform,
                'device_name' => $d->device_name,
                'app_version' => $d->app_version,
                'last_seen_at' => $this->iso($d->last_seen_at),
                'token_preview' => substr((string) $d->token, 0, 12).'…',
            ])->values(),
            'last_push' => $last ? ['title' => $last->title, 'pushed_at' => $this->iso($last->pushed_at)] : null,
        ];
    }
}
