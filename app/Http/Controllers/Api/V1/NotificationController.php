<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Member;
use App\Models\MemberDevice;
use App\Models\MemberNotification;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Member app: push-notification devices, the notification list and notification settings. */
class NotificationController extends ApiController
{
    public function __construct(protected PushNotificationService $notifications) {}

    /**
     * POST /api/v1/me/devices  { token, platform: android|ios, app_version?, device_name? }
     * The app calls this on every launch (and when Firebase refreshes the token).
     */
    public function registerDevice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'min:20', 'max:512'],
            'platform' => ['required', Rule::in(MemberDevice::PLATFORMS)],
            'app_version' => ['nullable', 'string', 'max:20'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $this->notifications->registerDevice($this->member($request), $data['token'], $data['platform'], $data['app_version'] ?? null, $data['device_name'] ?? null);

        return $this->success(['registered' => true], 'Device registered.');
    }

    /** DELETE /api/v1/me/devices  { token } — stop notifications on this phone (also done by logout with device_token). */
    public function removeDevice(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:512']]);
        $this->notifications->removeDevice($this->member($request), $data['token']);

        return $this->success(null, 'Device removed.');
    }

    /** GET /api/v1/me/notifications?page=1&unread=1 */
    public function index(Request $request): JsonResponse
    {
        $member = $this->member($request);
        $page = MemberNotification::where('member_id', $member->id)
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
            ->latest('id')
            ->paginate(min(50, max(5, (int) $request->integer('per_page', 20))));

        return $this->success([
            'unread_count' => $this->notifications->unreadCount($member),
            'notifications' => collect($page->items())->map->toApi()->all(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ], 'Notifications.');
    }

    /** POST /api/v1/me/notifications/{id}/read */
    public function markRead(Request $request, int $notificationId): JsonResponse
    {
        $member = $this->member($request);
        abort_unless(MemberNotification::where('member_id', $member->id)->whereKey($notificationId)->exists(), 404);
        $this->notifications->markRead($member, $notificationId);

        return $this->success(['unread_count' => $this->notifications->unreadCount($member)], 'Marked as read.');
    }

    /** POST /api/v1/me/notifications/read-all */
    public function markAllRead(Request $request): JsonResponse
    {
        $member = $this->member($request);
        $this->notifications->markRead($member);

        return $this->success(['unread_count' => 0], 'All notifications marked as read.');
    }

    /** GET /api/v1/me/notification-settings */
    public function settings(Request $request): JsonResponse
    {
        return $this->success($this->settingsPayload($this->member($request)), 'Notification settings.');
    }

    /** PUT /api/v1/me/notification-settings  { reminders?: bool, announcements?: bool } */
    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate(collect(PushNotificationService::OPTIONAL_CATEGORIES)->mapWithKeys(fn ($label, $key) => [$key => ['sometimes', 'boolean']])->all());
        $member = $this->member($request);
        $this->notifications->updateSettings($member, $data);

        return $this->success($this->settingsPayload($member), 'Notification settings saved.');
    }

    protected function member(Request $request): Member
    {
        return $request->user('member');
    }

    /** @return array<string, mixed> */
    protected function settingsPayload(Member $member): array
    {
        $settings = $this->notifications->settings($member);

        return [
            'settings' => $settings,
            'options' => collect(PushNotificationService::OPTIONAL_CATEGORIES)->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'enabled' => $settings[$key]])->values()->all(),
            'always_on' => 'Membership, payment and program updates are always sent.',
        ];
    }
}
