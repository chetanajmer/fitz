<?php

namespace App\Livewire\Gym;

use App\Models\Announcement;
use App\Models\Member;
use App\Models\MemberDevice;
use App\Models\MemberNotification;
use App\Models\User;
use App\Services\NotificationSettings;
use App\Services\PushNotificationService;
use App\Support\NotificationCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Gym notifications:
 *  - custom notifications: written here, approved by the Super Admin, then sent by the gym;
 *  - automatic notifications: the gym switches them on / off and picks the time and rules
 *    (the wording is set by the Super Admin).
 */
#[Layout('components.layouts.gym')]
#[Title('Notifications')]
class Announcements extends Component
{
    use WithFileUploads, WithPagination;

    /** custom | automatic */
    #[Url(except: 'custom')]
    public string $tab = 'custom';

    public string $title = '';

    public string $body = '';

    public string $audience = 'active';

    public ?string $trainerId = null;

    /** Optional picture shown in the notification (JPG / PNG, max 1 MB). */
    public $image = null;

    // ---- Send an approved notification ----
    #[Locked]
    public ?int $sendingId = null;

    /** now | later */
    public string $when = 'now';

    public ?string $scheduledAt = null;

    /** @var array<string, array{enabled: bool, time: ?string, params: array<string, mixed>}> */
    public array $automatic = [];

    public function mount(NotificationSettings $config): void
    {
        if (! $this->seesAllMembers()) {
            $this->audience = 'trainer';
        }

        $this->loadAutomatic($config);
    }

    public function submit(PushNotificationService $notifications): void
    {
        $this->validate([
            'title' => 'required|string|max:'.NotificationSettings::TITLE_MAX,
            'body' => 'required|string|max:'.NotificationSettings::ANNOUNCEMENT_BODY_MAX,
            'audience' => 'required|in:'.implode(',', array_keys(Announcement::AUDIENCES)),
            'trainerId' => 'nullable|required_if:audience,trainer|integer',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:'.NotificationSettings::IMAGE_MAX_KB,
        ], [
            'trainerId.required_if' => 'Choose a trainer.',
            'image.max' => 'The picture must be smaller than 2 MB.',
            'image.mimes' => 'Use a JPG, PNG or WebP picture.',
        ]);

        $imagePath = $this->image ? $this->image->store('gyms/'.current_gym()->id.'/notifications', 'public') : null;

        $notifications->submit(Auth::user(), [
            'title' => $this->title,
            'body' => $this->body,
            'audience' => $this->audience,
            'trainer_id' => $this->trainerId ? (int) $this->trainerId : null,
            'image_path' => $imagePath,
        ]);

        $this->reset('title', 'body', 'image');
        $this->resetPage();
        $this->dispatch('notify', message: 'Sent to Gymai for approval. You can send it as soon as it is approved.');
    }

    public function openSend(int $announcementId): void
    {
        $this->sendingId = $this->visibleAnnouncements()->where('status', Announcement::APPROVED)->findOrFail($announcementId)->id;
        $this->when = 'now';
        $this->scheduledAt = null;
        $this->resetErrorBag();
    }

    public function closeSend(): void
    {
        $this->sendingId = null;
        $this->resetErrorBag();
    }

    public function send(PushNotificationService $notifications): void
    {
        $announcement = $this->visibleAnnouncements()->findOrFail($this->sendingId);
        $this->validate(['when' => 'required|in:now,later', 'scheduledAt' => 'nullable|required_if:when,later|date'], ['scheduledAt.required_if' => 'Choose when to send it.']);

        $announcement = $notifications->send($announcement, $this->when === 'later' ? $this->scheduledAt : null);
        $this->sendingId = null;

        $this->dispatch('notify', message: $announcement->scheduled_at->isFuture()
            ? 'Scheduled for '.$announcement->scheduled_at->format('d M, h:i A').'.'
            : 'Sending to your members now.');
    }

    public function cancel(int $announcementId, PushNotificationService $notifications): void
    {
        $notifications->cancel($this->visibleAnnouncements()->findOrFail($announcementId));
        $this->dispatch('notify', message: 'Done.');
    }

    public function saveAutomatic(string $key, NotificationSettings $config): void
    {
        abort_unless(Auth::user()->can('gym.settings') && isset(NotificationCatalog::TYPES[$key]), 403);

        $config->saveForGym(current_gym(), $key, $this->automatic[$key] ?? ['enabled' => false]);
        $this->loadAutomatic($config);
        $this->dispatch('notify', message: NotificationCatalog::TYPES[$key]['label'].' saved.');
    }

    /** Switch one automatic notification on / off right away (time and rules are kept). */
    public function toggleAutomatic(string $key, NotificationSettings $config): void
    {
        abort_unless(Auth::user()->can('gym.settings') && isset(NotificationCatalog::TYPES[$key]), 403);

        $enabled = ! $config->forGym(current_gym(), $key)['gym_enabled'];
        $config->setEnabledForGym(current_gym(), $key, $enabled);
        $this->loadAutomatic($config);
        $this->dispatch('notify', message: NotificationCatalog::TYPES[$key]['label'].($enabled ? ' turned on.' : ' turned off.'));
    }

    /** Pause / resume every automatic notification for the gym. */
    public function togglePause(NotificationSettings $config): void
    {
        abort_unless(Auth::user()->can('gym.settings'), 403);

        $paused = ! $config->isPausedFor(current_gym());
        $config->setPausedFor(current_gym(), $paused);
        $this->dispatch('notify', message: $paused
            ? 'All automatic notifications are turned off for your members.'
            : 'Automatic notifications are back on.');
    }

    public function resetAutomatic(string $key, NotificationSettings $config): void
    {
        abort_unless(Auth::user()->can('gym.settings') && isset(NotificationCatalog::TYPES[$key]), 403);

        $config->resetForGym(current_gym(), $key);
        $this->loadAutomatic($config);
        $this->dispatch('notify', message: 'Back to the Gymai default time and rules.');
    }

    public function render(PushNotificationService $notifications, NotificationSettings $config): View
    {
        $gym = current_gym();
        $preview = new Announcement;
        $preview->forceFill([
            'audience' => $this->seesAllMembers() ? $this->audience : 'trainer',
            'trainer_id' => $this->seesAllMembers() ? ($this->trainerId ?: null) : Auth::id(),
        ]);
        $canPreview = $preview->audience !== 'trainer' || $preview->trainer_id;
        $limit = $config->dailyAnnouncementLimit();

        return view('livewire.gym.announcements', [
            'history' => $this->visibleAnnouncements()->with('creator:id,name', 'trainer:id,name')->latest('id')->paginate(15),
            'audiences' => Announcement::AUDIENCES,
            'trainers' => $this->seesAllMembers() ? User::trainersOf($gym->id)->orderBy('name')->get(['id', 'name']) : collect(),
            'recipients' => $canPreview ? $notifications->audienceQuery($preview)->count() : 0,
            'withApp' => $canPreview ? $notifications->audienceQuery($preview)->whereHas('devices')->count() : 0,
            'stats' => [
                'members_with_app' => Member::query()->active()->whereHas('devices')->count(),
                'pending' => $this->visibleAnnouncements()->where('status', Announcement::PENDING)->count(),
                'sent_30' => Announcement::where('status', Announcement::SENT)->where('sent_at', '>=', now()->subDays(30))->count(),
                'left_today' => max(0, $limit - Announcement::whereIn('status', [Announcement::SCHEDULED, Announcement::SENDING, Announcement::SENT])->whereDate('scheduled_at', today())->count()),
            ],
            'limit' => $limit,
            'quiet' => $config->quietHours(),
            'catalog' => NotificationCatalog::TYPES,
            'templates' => collect(NotificationCatalog::keys())->mapWithKeys(fn ($key) => [$key => $config->template($key)])->all(),
            'timeOptions' => $config->timeOptions(),
            'seesAll' => $this->seesAllMembers(),
            'canEditSettings' => Auth::user()->can('gym.settings'),
            'phones' => MemberDevice::count(),
            'paused' => $config->isPausedFor($gym),
            'activeCount' => collect(NotificationCatalog::keys())->filter(fn ($key) => $config->forGym($gym, $key)['enabled'])->count(),
            'sent' => $this->tab === 'automatic' ? $this->automaticCounts() : null,
        ]);
    }

    protected function loadAutomatic(NotificationSettings $config): void
    {
        $gym = current_gym();
        $this->automatic = collect(NotificationCatalog::keys())->mapWithKeys(function ($key) use ($config, $gym) {
            $setting = $config->forGym($gym, $key);

            return [$key => ['enabled' => $setting['gym_enabled'], 'time' => $setting['time'], 'params' => $setting['params']]];
        })->all();
    }

    /**
     * Automatic notifications sent by this gym: per type for today and the last 30 days.
     *
     * @return array{by_type: array<string, array{today: int, month: int}>, month: int, today: int, members: int, on_phone: int}
     */
    protected function automaticCounts(): array
    {
        $keys = NotificationCatalog::keys();
        $since = now()->subDays(30);
        $rows = MemberNotification::whereIn('type', $keys)->where('created_at', '>=', $since)
            ->selectRaw('type, count(*) as month_count, sum(case when created_at >= ? then 1 else 0 end) as today_count', [today()])
            ->groupBy('type')->get()->keyBy('type');

        return [
            'by_type' => collect($keys)->mapWithKeys(fn ($key) => [$key => [
                'today' => (int) ($rows[$key]->today_count ?? 0),
                'month' => (int) ($rows[$key]->month_count ?? 0),
            ]])->all(),
            'month' => (int) $rows->sum('month_count'),
            'today' => (int) $rows->sum('today_count'),
            'members' => MemberNotification::whereIn('type', $keys)->where('created_at', '>=', $since)->distinct()->count('member_id'),
            'on_phone' => MemberNotification::whereIn('type', $keys)->where('created_at', '>=', $since)->whereNotNull('pushed_at')->count(),
        ];
    }

    protected function seesAllMembers(): bool
    {
        return Auth::user()->can('members.all');
    }

    /** Admins see every notification of the gym; trainers only their own. */
    protected function visibleAnnouncements(): Builder
    {
        return Announcement::query()->when(! $this->seesAllMembers(), fn ($q) => $q->where('created_by', Auth::id()));
    }
}
