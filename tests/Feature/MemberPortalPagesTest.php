<?php

namespace Tests\Feature;

use App\Livewire\Portal\CheckIn;
use App\Livewire\Portal\Notifications;
use App\Models\Member;
use App\Models\MemberNotification;
use App\Services\MembershipService;
use App\Services\Push\PushMessage;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MemberPortalPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function memberWithPlan(): Member
    {
        $gym = $this->gymOf($this->createGymOwner());
        $member = $this->createMember($gym, ['name' => 'Arjun Shekhawat', 'email' => 'arjun@example.test'], ['portal_password' => 'password']);
        $plan = $this->createPlan($gym);
        $this->inGym($gym, fn () => app(MembershipService::class)->assign($member, $plan, now()->subDays(5)->toDateString(), 0, null, ['amount' => 600, 'method' => 'upi']));

        return $member->refresh();
    }

    protected function notify(Member $member, string $title): MemberNotification
    {
        return $this->inGym($member->gym, fn () => app(PushNotificationService::class)->notify($member, PushMessage::make('announcement', $title, 'Body for '.$title)));
    }

    public function test_overview_welcomes_the_member_and_links_every_portal_section(): void
    {
        $member = $this->memberWithPlan();
        $this->actingAs($member->user);

        $this->get(route('portal.dashboard'))->assertOk()
            ->assertSee('Welcome back, Arjun!')
            ->assertSee('Total attendance')->assertSee('Workout sessions')
            ->assertSee(route('portal.membership'), false)
            ->assertSee(route('portal.check-in'), false)
            ->assertSee(route('portal.notifications'), false)
            ->assertSee(route('portal.progress'), false);
    }

    public function test_membership_page_shows_the_plan_dues_and_receipts(): void
    {
        $member = $this->memberWithPlan();
        $payment = $this->inGym($member->gym, fn () => $member->payments()->first());
        $this->actingAs($member->user);

        $this->get(route('portal.membership'))->assertOk()
            ->assertSee('Monthly')->assertSee('days left')
            ->assertSee('Payments &amp; receipts', false)
            ->assertSee(route('portal.receipt', $payment), false)
            ->assertSee('due'); // 600 of 1000 paid
    }

    public function test_check_in_page_shows_the_desk_qr_scanner_and_lets_the_member_check_in(): void
    {
        $member = $this->memberWithPlan();
        $this->actingAs($member->user);

        $this->get(route('portal.check-in'))->assertOk()
            ->assertSee("Scan the gym's QR", false)
            ->assertDontSee(route('portal.qr'), false)
            ->assertSee('Check in now');

        Livewire::test(CheckIn::class)->call('checkIn', 'not the gym QR')->assertHasErrors('member');
        Livewire::test(CheckIn::class)->call('checkIn', $member->gym->checkin_token)->assertHasNoErrors()->assertSee('Checked in at');
    }

    public function test_notifications_list_mark_read_and_settings(): void
    {
        $member = $this->memberWithPlan();
        $first = $this->notify($member, 'Gym closed on Sunday');
        $this->notify($member, 'New Zumba batch');
        $this->actingAs($member->user);

        // Two announcements plus the automatic "payment received" notification from the membership payment.
        $this->get(route('portal.dashboard'))->assertOk()->assertSee('Notifications, 3 unread', false);

        Livewire::test(Notifications::class)
            ->assertSee('Gym closed on Sunday')->assertSee('New Zumba batch')
            ->call('markRead', $first->id)
            ->set('filter', 'unread')
            ->assertDontSee('Gym closed on Sunday')->assertSee('New Zumba batch')
            ->call('markAllRead')
            ->assertSee('No unread notifications')
            ->set('settings.announcements', false);

        $this->assertNotNull($first->fresh()->read_at);
        $this->assertFalse($member->fresh()->notification_settings['announcements']);
    }

    public function test_a_member_cannot_mark_another_members_notification(): void
    {
        $member = $this->memberWithPlan();
        $other = $this->createMember($member->gym);
        $theirs = $this->notify($other, 'Private note');
        $this->actingAs($member->user);

        Livewire::test(Notifications::class)->call('markRead', $theirs->id)->assertNotFound();
        $this->assertNull($theirs->fresh()->read_at);
    }

    public function test_new_portal_pages_are_for_members_only(): void
    {
        $owner = $this->createGymOwner();
        $this->actingAs($owner);

        foreach (['portal.membership', 'portal.check-in', 'portal.notifications'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
    }
}
