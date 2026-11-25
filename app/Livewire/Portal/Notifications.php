<?php

namespace App\Livewire\Portal;

use App\Livewire\Portal\Concerns\ChecksInSelf;
use App\Models\MemberNotification;
use App\Services\PushNotificationService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Member portal: the member's notification inbox (same list as the app) and which optional alerts they get. */
#[Layout('components.layouts.portal')]
#[Title('Notifications')]
class Notifications extends Component
{
    use ChecksInSelf;
    use WithPagination;

    #[Url(except: 'all')]
    public string $filter = 'all';

    /** @var array<string, bool> */
    public array $settings = [];

    public function mount(PushNotificationService $notifications): void
    {
        $this->settings = $notifications->settings($this->portalMember());
    }

    public function updatedFilter(): void
    {
        $this->filter = in_array($this->filter, ['all', 'unread'], true) ? $this->filter : 'all';
        $this->resetPage();
    }

    public function updatedSettings(PushNotificationService $notifications): void
    {
        $this->settings = $notifications->updateSettings($this->portalMember(), $this->settings);
        $this->dispatch('notify', message: 'Notification settings saved.');
    }

    public function markRead(int $notificationId, PushNotificationService $notifications): void
    {
        $member = $this->portalMember();
        abort_unless(MemberNotification::where('member_id', $member->id)->whereKey($notificationId)->exists(), 404);
        $notifications->markRead($member, $notificationId);
    }

    public function markAllRead(PushNotificationService $notifications): void
    {
        $notifications->markRead($this->portalMember());
        $this->dispatch('notify', message: 'All notifications marked as read.');
    }

    public function render(PushNotificationService $notifications): View
    {
        $member = $this->portalMember();

        return view('livewire.portal.notifications', [
            'items' => MemberNotification::where('member_id', $member->id)
                ->when($this->filter === 'unread', fn ($q) => $q->whereNull('read_at'))
                ->latest('id')->paginate(15),
            'unread' => $notifications->unreadCount($member),
            'categories' => PushNotificationService::OPTIONAL_CATEGORIES,
        ]);
    }
}
