<?php

namespace App\Livewire\Platform;

use App\Models\Announcement;
use App\Models\Gym;
use App\Models\Scopes\GymScope;
use App\Services\NotificationSettings;
use App\Services\PushNotificationService;
use App\Support\NotificationCatalog;
use App\Tenancy\TenantManager;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Super Admin: member app notifications.
 *  - approvals: custom notifications gyms want to send (approve / reject with a reason);
 *  - templates: the wording, default time and rules of every automatic notification;
 *  - rules: quiet hours and the daily limit for custom notifications.
 */
#[Layout('components.layouts.platform')]
#[Title('App notifications')]
class Notifications extends Component
{
    use WithPagination;

    /** approvals | templates | rules */
    #[Url(except: 'approvals')]
    public string $tab = 'approvals';

    /** pending | reviewed */
    #[Url(except: 'pending')]
    public string $queue = 'pending';

    #[Locked]
    public ?int $rejectingId = null;

    public string $reason = '';

    #[Locked]
    public ?string $editing = null;

    /** @var array{title: string, body: string, is_enabled: bool, send_time: ?string, params: array<string, mixed>} */
    public array $form = ['title' => '', 'body' => '', 'is_enabled' => true, 'send_time' => null, 'params' => []];

    public string $quietStart = NotificationSettings::QUIET_START;

    public string $quietEnd = NotificationSettings::QUIET_END;

    public int $dailyLimit = NotificationSettings::DAILY_ANNOUNCEMENT_LIMIT;

    public function mount(NotificationSettings $config): void
    {
        ['start' => $this->quietStart, 'end' => $this->quietEnd] = $config->quietHours();
        $this->dailyLimit = $config->dailyAnnouncementLimit();
    }

    public function updatedQueue(): void
    {
        $this->resetPage();
    }

    // ---- Approvals ----------------------------------------------------------------------------

    public function approve(int $announcementId, PushNotificationService $notifications): void
    {
        $notifications->approve($this->announcements()->findOrFail($announcementId), Auth::user());
        $this->dispatch('notify', message: 'Approved. The gym can send it now.');
    }

    public function startReject(int $announcementId): void
    {
        $this->rejectingId = $this->announcements()->where('status', Announcement::PENDING)->findOrFail($announcementId)->id;
        $this->reason = '';
        $this->resetErrorBag();
    }

    public function cancelReject(): void
    {
        $this->rejectingId = null;
        $this->resetErrorBag();
    }

    public function reject(PushNotificationService $notifications): void
    {
        $this->validate(['reason' => 'required|string|max:500'], ['reason.required' => 'Tell the gym why it was not approved.']);
        $notifications->reject($this->announcements()->findOrFail($this->rejectingId), Auth::user(), $this->reason);
        $this->rejectingId = null;
        $this->dispatch('notify', message: 'Rejected. The gym sees your reason.');
    }

    // ---- Templates ----------------------------------------------------------------------------

    public function edit(string $key, NotificationSettings $config): void
    {
        abort_unless(isset(NotificationCatalog::TYPES[$key]), 404);
        $template = $config->template($key);
        $this->editing = $key;
        $this->form = [
            'title' => $template['title'], 'body' => $template['body'], 'is_enabled' => $template['is_enabled'],
            'send_time' => $template['send_time'], 'params' => $template['params'],
        ];
        $this->resetErrorBag();
    }

    public function cancelEdit(): void
    {
        $this->editing = null;
        $this->resetErrorBag();
    }

    /** Activate / deactivate a notification for every gym, without opening the editor. */
    public function toggleTemplate(string $key, NotificationSettings $config): void
    {
        abort_unless(isset(NotificationCatalog::TYPES[$key]), 404);
        $active = $config->setTemplateEnabled($key, ! $config->template($key)['is_enabled'], Auth::user());

        if ($this->editing === $key) {
            $this->form['is_enabled'] = $active;
        }

        $this->dispatch('notify', message: NotificationCatalog::TYPES[$key]['label'].($active ? ' activated for all gyms.' : ' deactivated for all gyms.'));
    }

    public function saveTemplate(NotificationSettings $config): void
    {
        $this->validate([
            'form.title' => 'required|string|max:'.NotificationSettings::TITLE_MAX,
            'form.body' => 'required|string|max:'.NotificationSettings::BODY_MAX,
            'form.is_enabled' => 'boolean',
        ], [], ['form.title' => 'title', 'form.body' => 'message']);

        try {
            $config->saveTemplate($this->editing, $this->form, Auth::user());
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError('form.'.$field, $messages[0]);
            }

            return;
        }

        $this->editing = null;
        $this->dispatch('notify', message: 'Template saved. It is used for every gym from now on.');
    }

    public function resetTemplate(string $key, NotificationSettings $config): void
    {
        $config->resetTemplate($key, Auth::user());
        $this->edit($key, $config);
        $this->dispatch('notify', message: 'Back to the Gymai default.');
    }

    // ---- Rules --------------------------------------------------------------------------------

    public function saveRules(NotificationSettings $config): void
    {
        $this->validate(['dailyLimit' => 'required|integer|min:1|max:20']);
        $config->saveRules(['start' => $this->quietStart, 'end' => $this->quietEnd, 'daily_limit' => $this->dailyLimit]);
        $this->dispatch('notify', message: 'Notification rules saved.');
    }

    public function render(NotificationSettings $config): View
    {
        $list = $this->tab === 'approvals'
            ? $this->announcements()->with(['gym:id,name', 'creator:id,name', 'reviewer:id,name', 'trainer:id,name'])
                ->when($this->queue === 'pending', fn ($q) => $q->where('status', Announcement::PENDING)->oldest('id'),
                    fn ($q) => $q->where('status', '!=', Announcement::PENDING)->whereNotNull('reviewed_at')->latest('reviewed_at'))
                ->paginate(15)
            : null;

        return view('livewire.platform.notifications', [
            'list' => $list,
            'audienceSizes' => $list ? $this->audienceSizes($list->getCollection()) : [],
            'pendingCount' => $this->announcements()->where('status', Announcement::PENDING)->count(),
            'catalog' => NotificationCatalog::TYPES,
            'templates' => collect(NotificationCatalog::keys())->mapWithKeys(fn ($key) => [$key => $config->template($key)])->all(),
            'timeOptions' => $config->timeOptions(),
        ]);
    }

    protected function announcements(): Builder
    {
        return Announcement::withoutGlobalScope(GymScope::class);
    }

    /**
     * How many members each pending notification would reach.
     *
     * @param  iterable<Announcement>  $announcements
     * @return array<int, int>
     */
    protected function audienceSizes(iterable $announcements): array
    {
        $sizes = [];
        foreach ($announcements as $announcement) {
            if ($announcement->status === Announcement::PENDING && ($gym = Gym::find($announcement->gym_id))) {
                $sizes[$announcement->id] = app(TenantManager::class)->runAs($gym,
                    fn () => app(PushNotificationService::class)->audienceQuery($announcement)->count());
            }
        }

        return $sizes;
    }
}
