<?php

namespace Tests\Feature;

use App\Contracts\SmsSender;
use App\Livewire\Gym\Streaks;
use App\Livewire\Portal\Dashboard;
use App\Models\Attendance;
use App\Models\Gym;
use App\Models\Member;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SelfCheckInTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected Member $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gym = $this->gymOf($this->createGymOwner());
        $this->member = $this->createMember($this->gym, ['phone' => '9000000401', 'email' => 'self@example.test'], ['portal_password' => 'password']);
        $this->inGym($this->gym, fn () => app(MembershipService::class)->assign($this->member, $this->createPlan($this->gym)));
    }

    protected function apiToken(): string
    {
        $sms = new class implements SmsSender
        {
            public string $code = '';

            public function send(string $phone, string $message): void
            {
                $this->code = substr($message, 0, 6);
            }
        };
        $this->app->instance(SmsSender::class, $sms);

        $this->postJson('/api/v1/auth/send-otp', ['phone' => '9000000401'])->assertOk();

        return $this->postJson('/api/v1/auth/verify-otp', ['phone' => '9000000401', 'otp' => $sms->code])->json('data.token');
    }

    public function test_member_checks_in_and_out_from_portal_button(): void
    {
        $this->actingAs($this->member->user);

        $this->get(route('portal.dashboard'))->assertOk()->assertSee('Check in now');

        Livewire::test(Dashboard::class)
            ->call('checkIn')
            ->assertDispatched('streak-grew', count: 1, milestone: false)
            ->assertHasNoErrors()
            ->assertSee('Checked in at')
            ->assertSee('1 day streak')
            ->call('checkIn')
            ->assertHasErrors('member')
            ->call('checkOut')
            ->assertHasNoErrors()
            ->assertSee('Visit done today');

        $attendance = Attendance::withoutGlobalScopes()->sole();
        $this->assertSame('self', $attendance->method);
        $this->assertNotNull($attendance->check_out_at);
        $this->assertSame($this->member->user_id, $attendance->recorded_by);
    }

    public function test_member_without_valid_membership_cannot_self_check_in(): void
    {
        $other = $this->createMember($this->gym, ['email' => 'nomember@example.test'], ['portal_password' => 'password']);
        $this->actingAs($other->user);

        Livewire::test(Dashboard::class)->call('checkIn')->assertHasErrors('member');
        $this->assertSame(0, Attendance::withoutGlobalScopes()->count());
    }

    public function test_api_check_in_flow_updates_streak(): void
    {
        $token = $this->apiToken();

        $this->withToken($token)->getJson('/api/v1/me/check-in')
            ->assertOk()->assertJsonPath('data.state', 'not_checked_in')->assertJsonPath('data.self_checkin_enabled', true);

        $this->withToken($token)->postJson('/api/v1/me/check-in')
            ->assertCreated()
            ->assertJsonPath('data.attendance.method', 'self')
            ->assertJsonPath('data.streak.current_streak', 1)
            ->assertJsonPath('data.streak.checked_in_today', true);

        $this->withToken($token)->postJson('/api/v1/me/check-in')->assertStatus(409)->assertJsonPath('code', 'ALREADY_CHECKED_IN');
        $this->withToken($token)->getJson('/api/v1/me/check-in')->assertJsonPath('data.state', 'checked_in');

        $this->withToken($token)->postJson('/api/v1/me/check-out')->assertOk()->assertJsonPath('data.attendance.duration', '0h 0m');
        $this->withToken($token)->postJson('/api/v1/me/check-out')->assertStatus(409)->assertJsonPath('code', 'ALREADY_CHECKED_OUT');

        // App check-ins are not attributed to any staff user.
        $this->assertNull(Attendance::withoutGlobalScopes()->sole()->recorded_by);
    }

    public function test_api_errors_for_check_out_before_check_in_and_no_membership(): void
    {
        $token = $this->apiToken();
        $this->withToken($token)->postJson('/api/v1/me/check-out')->assertStatus(409)->assertJsonPath('code', 'NOT_CHECKED_IN');

        $this->member->memberships()->withoutGlobalScopes()->update(['status' => 'cancelled']);
        $this->withToken($token)->postJson('/api/v1/me/check-in')->assertForbidden()->assertJsonPath('code', 'NO_ACTIVE_MEMBERSHIP');
    }

    public function test_gym_admin_can_turn_off_self_check_in(): void
    {
        $this->actingAs($this->gym->owner);
        Livewire::test(Streaks::class)->set('selfCheckIn', false)->call('saveRules')->assertHasNoErrors();
        $this->assertFalse($this->gym->fresh()->allowsSelfCheckIn());

        $token = $this->apiToken();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/me/check-in')->assertForbidden()->assertJsonPath('code', 'SELF_CHECKIN_DISABLED');

        $this->withoutToken();
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->member->user);
        $this->get(route('portal.dashboard'))->assertOk()->assertDontSee('Check in now')->assertSee('Show your QR code at the front desk');
        Livewire::test(Dashboard::class)->call('checkIn')->assertHasErrors('member');
    }
}
