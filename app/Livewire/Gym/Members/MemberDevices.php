<?php

namespace App\Livewire\Gym\Members;

use App\Models\Member;
use App\Models\MemberDevice;
use App\Models\MemberNotification;
use App\Services\Push\FcmClient;
use App\Services\Push\PushMessage;
use App\Services\PushNotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Member page (gym admins only): the phones the member is signed in on with their Firebase (FCM)
 * tokens, to check and test push notifications.
 */
class MemberDevices extends Component
{
    #[Locked]
    public Member $member;

    public function mount(Member $member): void
    {
        $this->authorizeAdmin();
        $this->member = $member;
    }

    public function sendTest(PushNotificationService $notifications): void
    {
        $this->authorizeAdmin();
        abort_unless($this->member->devices()->exists(), 422);

        $notifications->notify($this->member, PushMessage::make('test', 'Test from '.current_gym()->name, 'Push notifications are working on this phone.'));
        $this->dispatch('notify', message: 'Test notification sent to '.$this->member->name.'\'s phone.');
    }

    public function removeDevice(int $deviceId, PushNotificationService $notifications): void
    {
        $this->authorizeAdmin();
        $device = MemberDevice::where('member_id', $this->member->id)->findOrFail($deviceId);
        $notifications->removeDevice($this->member, $device->token);
        $this->dispatch('notify', message: 'Phone removed. It registers again the next time the app opens.');
    }

    public function render(FcmClient $fcm): View
    {
        return view('livewire.gym.members.member-devices', [
            'devices' => MemberDevice::where('member_id', $this->member->id)->latest('last_seen_at')->get(),
            'lastPush' => MemberNotification::where('member_id', $this->member->id)->whereNotNull('pushed_at')->latest('pushed_at')->first(),
            'firebaseReady' => $fcm->isConfigured(),
        ]);
    }

    /** FCM tokens are only shown to gym admins. */
    protected function authorizeAdmin(): void
    {
        abort_unless(Auth::user()?->can('gym.settings'), 403);
    }
}
