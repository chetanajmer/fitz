<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ApiException;
use App\Models\Announcement;
use App\Models\Member;
use App\Models\MemberDevice;
use App\Models\MemberNotification;
use App\Services\NotificationSettings;
use App\Services\PushNotificationService;
use App\Support\NotificationCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Notifications to members, as on the web Notifications page (permission announcements.send):
 *  - custom notifications are submitted, approved by the Fitnix Super Admin, then sent (now or later) by the gym;
 *    trainers without "see all members" only reach their own members and see only their own notifications;
 *  - automatic notifications (gym admins: gym.settings) are switched on / off with their time and rules.
 */
class AnnouncementController extends AdminController
{
    /** Web field names in service messages => API field names. */
    protected const FIELD_NAMES = ['trainerId' => 'trainer_id', 'scheduledAt' => 'scheduled_at'];

    public function __construct(protected PushNotificationService $notifications, protected NotificationSettings $config) {}

    /** GET /admin/announcements?status=&page= — history (newest first), today's limits and the audiences. */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['nullable', Rule::in(array_keys(Announcement::STATUSES))]]);
        $user = $this->staff($request);
        $limit = $this->config->dailyAnnouncementLimit();

        $page = $this->visible($request)
            ->with('creator:id,name', 'trainer:id,name')
            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate($this->perPage($request));

        return $this->success([
            'stats' => [
                'members_with_app' => Member::query()->active()->whereHas('devices')->count(),
                'waiting_for_approval' => $this->visible($request)->where('status', Announcement::PENDING)->count(),
                'sent_last_30_days' => Announcement::where('status', Announcement::SENT)->where('sent_at', '>=', now()->subDays(30))->count(),
                'sends_left_today' => max(0, $limit - Announcement::whereIn('status', [Announcement::SCHEDULED, Announcement::SENDING, Announcement::SENT])->whereDate('scheduled_at', today())->count()),
            ],
            'limits' => [
                'sends_per_day' => $limit,
                'submissions_per_day' => NotificationSettings::DAILY_SUBMISSION_LIMIT,
                'title_max' => NotificationSettings::TITLE_MAX,
                'body_max' => NotificationSettings::ANNOUNCEMENT_BODY_MAX,
                'image_max_kb' => NotificationSettings::IMAGE_MAX_KB,
                'quiet_hours' => $this->config->quietHours(),
            ],
            'audiences' => collect(Announcement::AUDIENCES)
                ->when(! $user->can('members.all'), fn ($c) => $c->only('trainer'))
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values(),
            'placeholders' => NotificationCatalog::COMMON_PLACEHOLDERS,
            'sees_all_members' => $user->can('members.all'),
            'announcements' => collect($page->items())->map(fn (Announcement $a) => $this->announcementData($a))->values(),
            'pagination' => $this->pagination($page),
        ], 'Notifications.');
    }

    /** GET /admin/announcements/audience-count?audience=&trainer_id= — how many members it reaches, and how many have the app. */
    public function audience(Request $request): JsonResponse
    {
        $data = $request->validate([
            'audience' => ['required', Rule::in(array_keys(Announcement::AUDIENCES))],
            'trainer_id' => ['nullable', 'integer'],
        ]);
        $user = $this->staff($request);
        $preview = new Announcement;
        $preview->forceFill($user->can('members.all')
            ? ['audience' => $data['audience'], 'trainer_id' => $data['audience'] === 'trainer' ? ($data['trainer_id'] ?? null) : null]
            : ['audience' => 'trainer', 'trainer_id' => $user->id]);

        $ready = $preview->audience !== 'trainer' || $preview->trainer_id;

        return $this->success([
            'audience' => $preview->audience,
            'members' => $ready ? $this->notifications->audienceQuery($preview)->count() : 0,
            'with_app' => $ready ? $this->notifications->audienceQuery($preview)->whereHas('devices')->count() : 0,
        ], 'Audience.');
    }

    /** POST /admin/announcements (JSON, or multipart with "image") — sent to Fitnix for approval. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:'.NotificationSettings::TITLE_MAX],
            'body' => ['required', 'string', 'max:'.NotificationSettings::ANNOUNCEMENT_BODY_MAX],
            'audience' => ['nullable', Rule::in(array_keys(Announcement::AUDIENCES))],
            'trainer_id' => ['nullable', 'integer'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.NotificationSettings::IMAGE_MAX_KB],
        ], [
            'image.mimes' => 'Use a JPG, PNG or WebP picture.',
        ]);
        $user = $this->staff($request);
        $audience = $data['audience'] ?? ($user->can('members.all') ? 'active' : 'trainer');

        if ($user->can('members.all') && $audience === 'trainer' && empty($data['trainer_id'])) {
            throw ValidationException::withMessages(['trainer_id' => 'Choose a trainer.']);
        }

        $announcement = $this->mapErrors(function () use ($request, $user, $data, $audience) {
            $imagePath = $request->file('image')?->store('gyms/'.current_gym()->id.'/notifications', 'public');

            return $this->notifications->submit($user, [
                'title' => $data['title'],
                'body' => $data['body'],
                'audience' => $audience,
                'trainer_id' => isset($data['trainer_id']) ? (int) $data['trainer_id'] : null,
                'image_path' => $imagePath,
            ]);
        });

        return $this->success(['announcement' => $this->announcementData($announcement->fresh(['creator', 'trainer']))],
            'Sent to Fitnix for approval. You can send it as soon as it is approved.', 201);
    }

    /** GET /admin/announcements/{id} */
    public function show(Request $request, int $announcementId): JsonResponse
    {
        return $this->success(['announcement' => $this->announcementData($this->find($request, $announcementId))], 'Notification.');
    }

    /** POST /admin/announcements/{id}/send { when: now|later, scheduled_at? } — only approved notifications. */
    public function send(Request $request, int $announcementId): JsonResponse
    {
        $announcement = $this->find($request, $announcementId);
        $data = $request->validate([
            'when' => ['nullable', 'in:now,later'],
            'scheduled_at' => ['nullable', 'required_if:when,later', 'date'],
        ], ['scheduled_at.required_if' => 'Choose when to send it.']);

        if ($announcement->status !== Announcement::APPROVED) {
            throw new ApiException('Only approved notifications can be sent.', 'NOT_APPROVED', 409, ['status' => $announcement->status]);
        }

        $later = ($data['when'] ?? 'now') === 'later';
        $announcement = $this->mapErrors(fn () => $this->notifications->send($announcement, $later ? $data['scheduled_at'] : null));

        return $this->success(['announcement' => $this->announcementData($announcement->fresh(['creator', 'trainer']))],
            $announcement->scheduled_at->isFuture() ? 'Scheduled for '.$announcement->scheduled_at->format('d M, h:i A').'.' : 'Sending to your members now.');
    }

    /** POST /admin/announcements/{id}/cancel — a scheduled one goes back to approved; an unsent one is withdrawn. */
    public function cancel(Request $request, int $announcementId): JsonResponse
    {
        $announcement = $this->find($request, $announcementId);

        if (in_array($announcement->status, [Announcement::SENDING, Announcement::SENT, Announcement::CANCELLED], true)) {
            throw new ApiException('This notification was already sent or withdrawn.', 'ALREADY_SENT', 409, ['status' => $announcement->status]);
        }

        $this->notifications->cancel($announcement);

        return $this->success(['announcement' => $this->announcementData($announcement->fresh(['creator', 'trainer']))], 'Done.');
    }

    // ---- Automatic notifications (gym admins) -------------------------------------------

    /** GET /admin/automatic-notifications — every automatic notification with its wording, time, rules and sends. */
    public function automatic(): JsonResponse
    {
        return $this->success($this->automaticData(), 'Automatic notifications.');
    }

    /** PUT /admin/automatic-notifications/{key} { enabled, time?, params? } */
    public function saveAutomatic(Request $request, string $key): JsonResponse
    {
        $this->ensureType($key);
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'time' => ['nullable', 'date_format:H:i'],
            'params' => ['nullable', 'array'],
        ]);

        $this->config->saveForGym(current_gym(), $key, [
            'enabled' => (bool) $data['enabled'],
            'time' => $data['time'] ?? null,
            'params' => $data['params'] ?? [],
        ]);

        return $this->success($this->automaticData(), NotificationCatalog::TYPES[$key]['label'].' saved.');
    }

    /** POST /admin/automatic-notifications/{key}/toggle { enabled? } — switch one on / off, keeping its time and rules. */
    public function toggleAutomatic(Request $request, string $key): JsonResponse
    {
        $this->ensureType($key);
        $data = $request->validate(['enabled' => ['nullable', 'boolean']]);
        $enabled = isset($data['enabled']) ? (bool) $data['enabled'] : ! $this->config->forGym(current_gym(), $key)['gym_enabled'];
        $this->config->setEnabledForGym(current_gym(), $key, $enabled);

        return $this->success($this->automaticData(), NotificationCatalog::TYPES[$key]['label'].($enabled ? ' turned on.' : ' turned off.'));
    }

    /** POST /admin/automatic-notifications/{key}/reset — back to the Fitnix default time and rules (on / off is kept). */
    public function resetAutomatic(string $key): JsonResponse
    {
        $this->ensureType($key);
        $this->config->resetForGym(current_gym(), $key);

        return $this->success($this->automaticData(), 'Back to the Fitnix default time and rules.');
    }

    /** POST /admin/automatic-notifications/pause { paused } — pause / resume every automatic notification. */
    public function pauseAutomatic(Request $request): JsonResponse
    {
        $data = $request->validate(['paused' => ['required', 'boolean']]);
        $this->config->setPausedFor(current_gym(), (bool) $data['paused']);

        return $this->success($this->automaticData(), $data['paused']
            ? 'All automatic notifications are turned off for your members.'
            : 'Automatic notifications are back on.');
    }

    // ---- Helpers ----------------------------------------------------------------

    /** Admins see every notification of the gym; trainers only their own. */
    protected function visible(Request $request): Builder
    {
        $user = $this->staff($request);

        return Announcement::query()->when(! $user->can('members.all'), fn ($q) => $q->where('created_by', $user->id));
    }

    protected function find(Request $request, int $announcementId): Announcement
    {
        return $this->visible($request)->with('creator:id,name', 'trainer:id,name')->find($announcementId)
            ?? throw new ApiException('Notification not found.', 'NOT_FOUND', 404);
    }

    protected function ensureType(string $key): void
    {
        if (! isset(NotificationCatalog::TYPES[$key])) {
            throw new ApiException('Notification type not found.', 'NOT_FOUND', 404);
        }
    }

    /**
     * Run a service call and rename web field names in its validation errors.
     *
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     */
    protected function mapErrors(\Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (ValidationException $e) {
            $errors = collect($e->errors())->mapWithKeys(fn ($messages, $field) => [self::FIELD_NAMES[$field] ?? $field => $messages])->all();

            throw ValidationException::withMessages($errors);
        }
    }

    /** @return array<string, mixed> */
    protected function announcementData(Announcement $announcement): array
    {
        return [
            'id' => $announcement->id,
            'title' => $announcement->title,
            'body' => $announcement->body,
            'image_url' => $announcement->imageUrl(),
            'audience' => $announcement->audience,
            'audience_label' => $announcement->audienceLabel(),
            'trainer' => $this->person($announcement->trainer),
            'status' => $announcement->status,
            'status_label' => $announcement->statusLabel(),
            'rejection_reason' => $announcement->rejection_reason,
            'scheduled_at' => $this->iso($announcement->scheduled_at),
            'sent_at' => $this->iso($announcement->sent_at),
            'recipients' => (int) $announcement->recipients_count,
            'created_by' => $this->person($announcement->creator),
            'created_at' => $this->iso($announcement->created_at),
            'can_send' => $announcement->status === Announcement::APPROVED,
            'can_cancel' => in_array($announcement->status, [Announcement::PENDING, Announcement::APPROVED, Announcement::REJECTED, Announcement::SCHEDULED], true),
        ];
    }

    /** @return array<string, mixed> */
    protected function automaticData(): array
    {
        $gym = current_gym();
        $keys = NotificationCatalog::keys();
        $since = now()->subDays(30);
        $sent = MemberNotification::whereIn('type', $keys)->where('created_at', '>=', $since)
            ->selectRaw('type, count(*) as month_count, sum(case when created_at >= ? then 1 else 0 end) as today_count', [today()])
            ->groupBy('type')->get()->keyBy('type');

        return [
            'paused' => $this->config->isPausedFor($gym),
            'time_options' => $this->config->timeOptions(),
            'quiet_hours' => $this->config->quietHours(),
            'phones' => MemberDevice::count(),
            'sent' => [
                'today' => (int) $sent->sum('today_count'),
                'last_30_days' => (int) $sent->sum('month_count'),
            ],
            'types' => collect($keys)->map(function (string $key) use ($gym, $sent) {
                $definition = NotificationCatalog::TYPES[$key];
                $setting = $this->config->forGym($gym, $key);
                $template = $this->config->template($key);

                return [
                    'key' => $key,
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                    'kind' => $definition['kind'],
                    'title' => $template['title'],
                    'body' => $template['body'],
                    'enabled' => $setting['enabled'],
                    'gym_enabled' => $setting['gym_enabled'],
                    'platform_enabled' => $setting['platform_enabled'],
                    'time' => $setting['time'],
                    'default_time' => $template['send_time'],
                    'params' => $setting['params'],
                    'param_fields' => $definition['params'] ?? (object) [],
                    'customised' => $setting['customised'],
                    'sent_today' => (int) ($sent[$key]->today_count ?? 0),
                    'sent_last_30_days' => (int) ($sent[$key]->month_count ?? 0),
                ];
            })->values(),
        ];
    }
}
