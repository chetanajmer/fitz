<?php

namespace Tests\Feature;

use App\Contracts\SmsSender;
use App\Livewire\Gym\Enquiries\Index as EnquiriesIndex;
use App\Livewire\PublicSite\JoinStatus;
use App\Livewire\PublicSite\MemberSignup;
use App\Models\Gym;
use App\Models\Member;
use App\Services\MemberSignupService;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class JoinRequestsTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{phone:string, message:string}> */
    protected array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->sent = [];
        $this->app->instance(SmsSender::class, new class($this->sent) implements SmsSender
        {
            public function __construct(private array &$sent) {}

            public function send(string $phone, string $message): void
            {
                $this->sent[] = compact('phone', 'message');
            }
        });
    }

    protected function lastOtp(): string
    {
        preg_match('/^(\d{6})/', end($this->sent)['message'], $m);

        return $m[1];
    }

    /** A website/app sign-up waiting for the gym's approval. */
    protected function pendingSignUp(Gym $gym, string $name = 'Join Request', string $phone = '9811100010'): Member
    {
        return app(TenantManager::class)->runAs($gym, fn () => $this->createMember($gym, ['name' => $name, 'phone' => $phone, 'status' => 'pending']));
    }

    public function test_join_requests_show_on_enquiries_and_can_be_approved_or_declined(): void
    {
        $owner = $this->createGymOwner();
        $gym = $this->gymOf($owner);
        $first = $this->pendingSignUp($gym, 'First Joiner', '9811100011');
        $second = $this->pendingSignUp($gym, 'Second Joiner', '9811100012');

        $page = Livewire::actingAs($owner)->test(EnquiriesIndex::class)
            ->assertSee('Join requests')->assertSee('First Joiner')->assertSee('Second Joiner')
            ->call('approveJoinRequest', $first->id)
            ->call('declineJoinRequest', $second->id);

        $page->assertDontSee('First Joiner')->assertDontSee('Second Joiner')->assertDontSee('Join requests');
        $this->assertSame('active', $first->fresh()->status->value);
        $this->assertSoftDeleted($second);
    }

    public function test_only_pending_members_can_be_approved_from_enquiries(): void
    {
        $owner = $this->createGymOwner();
        $member = $this->createMember($this->gymOf($owner), ['status' => 'inactive']);

        Livewire::actingAs($owner)->test(EnquiriesIndex::class)
            ->call('approveJoinRequest', $member->id)
            ->assertHasErrors('member');

        $this->assertSame('inactive', $member->fresh()->status->value);
    }

    public function test_staff_who_cannot_manage_members_do_not_see_join_requests(): void
    {
        $gym = $this->gymOf($this->createGymOwner());
        $trainer = $this->createTrainer($gym);
        $trainer->givePermissionTo('enquiries.manage');
        $this->pendingSignUp($gym);

        Livewire::actingAs($trainer)->test(EnquiriesIndex::class)->assertDontSee('Join requests');
    }

    public function test_enquiries_menu_shows_a_badge_on_every_gym_page(): void
    {
        $owner = $this->createGymOwner();
        $gym = $this->gymOf($owner);
        $this->pendingSignUp($gym, 'Badge One', '9811100013');
        $this->pendingSignUp($gym, 'Badge Two', '9811100014');

        $this->actingAs($owner)->get(route('gym.members.index'))->assertOk()
            ->assertSee('title="2 join requests, 0 new enquiries"', false);
    }

    public function test_sign_up_screen_shows_live_status_after_approval(): void
    {
        $owner = $this->createGymOwner();
        $gym = $this->gymOf($owner);

        $page = Livewire::test(MemberSignup::class)
            ->call('selectCity', $gym->city_id)->call('selectGym', $gym->id)
            ->set('name', 'Status Watcher')->set('phone', '9811100015')
            ->call('sendOtp');
        $page->set('otp', $this->lastOtp())->call('verify')
            ->assertSee('Waiting for approval');

        Livewire::actingAs($owner)->test(EnquiriesIndex::class)
            ->call('approveJoinRequest', Member::forGym($gym)->sole()->id);

        $page->call('$refresh')->assertSee('Approved')->assertDontSee('Waiting for approval');
    }

    public function test_status_page_shows_registrations_only_after_otp(): void
    {
        $gym = $this->gymOf($this->createGymOwner('Iron Temple'));
        $this->pendingSignUp($gym, 'Status Person', '9811100016');

        $page = Livewire::test(JoinStatus::class)
            ->set('phone', '9811100016')->call('sendOtp')->assertSet('step', 2)
            ->assertDontSee('Iron Temple');

        $page->set('otp', $this->lastOtp())->call('verify')
            ->assertSet('step', 3)
            ->assertSee('Iron Temple')->assertSee('Waiting for approval');
    }

    public function test_status_page_does_not_follow_a_phone_changed_after_verification(): void
    {
        $gym = $this->gymOf($this->createGymOwner('Secret Gym'));
        $this->pendingSignUp($gym, 'Someone Else', '9811100099');

        $page = Livewire::test(JoinStatus::class)->set('phone', '9811100017')->call('sendOtp');
        $page->set('otp', $this->lastOtp())->call('verify')->assertSet('step', 3);

        $page->set('phone', '9811100099')->call('$refresh')
            ->assertDontSee('Secret Gym')
            ->assertSee('find a registration for XXXXXX0017');
    }

    public function test_declined_sign_up_reads_as_not_accepted(): void
    {
        $gym = $this->gymOf($this->createGymOwner());
        $member = $this->pendingSignUp($gym);
        $member->delete();

        $this->assertSame('declined', app(MemberSignupService::class)->statusOf(Member::withoutGlobalScopes()->find($member->id)));
    }
}
