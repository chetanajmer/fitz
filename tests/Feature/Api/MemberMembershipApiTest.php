<?php

namespace Tests\Feature\Api;

use App\Enums\PlanDuration;
use App\Models\Gym;
use App\Models\Member;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MemberMembershipApiTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-23 12:00'));
        $this->gym = $this->gymOf($this->createGymOwner('Iron Gym'));
    }

    protected function assign(Member $member, string $start, int $days = 91, string $name = 'Quarterly Pro', ?array $payment = null): void
    {
        // Back to the staff (web) guard after any earlier app request in this test.
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
        $plan = $this->createPlan($this->gym, ['name' => $name, 'duration_type' => PlanDuration::Custom, 'duration_days' => $days, 'price' => 3000]);
        $this->inGym($this->gym, fn () => app(MembershipService::class)->assign($member, $plan, $start, payment: $payment));
    }

    protected function membershipOf(Member $member): TestResponse
    {
        $this->app['auth']->forgetGuards(); // each call is a different member's app

        return $this->withToken($member->createToken('app', ['member'])->plainTextToken)->getJson('/api/v1/me/membership');
    }

    public function test_card_for_membership_expiring_soon_matches_the_app_card(): void
    {
        $member = $this->createMember($this->gym);
        // 31 Aug + 91 days → ends 29 Nov: 6 days left on 23 Nov.
        $this->assign($member, '2026-08-31', payment: ['amount' => 3000, 'method' => 'cash']);

        $this->membershipOf($member)->assertOk()
            ->assertJsonPath('data.card.state', 'expiring_soon')
            ->assertJsonPath('data.card.state_label', 'Expiring soon')
            ->assertJsonPath('data.card.plan_name', 'Quarterly Pro')
            ->assertJsonPath('data.card.start_date', '2026-08-31')
            ->assertJsonPath('data.card.end_date', '2026-11-29')
            ->assertJsonPath('data.card.days_left', 6)
            ->assertJsonPath('data.card.total_days', 91)
            ->assertJsonPath('data.card.days_used', 85)
            ->assertJsonPath('data.card.progress_percent', 93.4)
            ->assertJsonPath('data.card.payment_status', 'paid')
            ->assertJsonPath('data.card.show_renew', true)
            ->assertJsonPath('data.card.renewal', null)
            ->assertJsonCount(1, 'data.history');
    }

    public function test_active_upcoming_expired_none_and_already_renewed_states(): void
    {
        $active = $this->createMember($this->gym);
        $this->assign($active, '2026-11-01', 60); // ends 30 Dec
        $this->membershipOf($active)->assertJsonPath('data.card.state', 'active')->assertJsonPath('data.card.show_renew', false)
            ->assertJsonPath('data.card.days_left', 37)->assertJsonPath('data.card.payment_status', 'unpaid');

        $renewed = $this->createMember($this->gym);
        $this->assign($renewed, '2026-08-31');
        $this->assign($renewed, '2026-11-30', 30, 'Monthly');
        $this->membershipOf($renewed)->assertJsonPath('data.card.state', 'expiring_soon')
            ->assertJsonPath('data.card.show_renew', false)
            ->assertJsonPath('data.card.renewal.plan_name', 'Monthly')
            ->assertJsonPath('data.card.renewal.start_date', '2026-11-30')
            ->assertJsonCount(2, 'data.history');

        $upcoming = $this->createMember($this->gym);
        $this->assign($upcoming, '2026-12-01', 30);
        $this->membershipOf($upcoming)->assertJsonPath('data.card.state', 'upcoming')->assertJsonPath('data.card.starts_in_days', 8)
            ->assertJsonPath('data.card.days_used', 0)->assertJsonPath('data.card.progress_percent', 0);

        $expired = $this->createMember($this->gym);
        $this->assign($expired, '2026-09-01', 30);
        $this->membershipOf($expired)->assertJsonPath('data.card.state', 'expired')->assertJsonPath('data.card.days_left', 0)
            ->assertJsonPath('data.card.show_renew', true);

        $none = $this->createMember($this->gym);
        $this->membershipOf($none)->assertJsonPath('data.card.state', 'none')->assertJsonPath('data.card.has_membership', false)
            ->assertJsonPath('data.history', []);
    }

    public function test_profile_endpoint_and_login_include_the_card(): void
    {
        $member = $this->createMember($this->gym);
        $this->assign($member, '2026-08-31');

        $this->withToken($member->createToken('app', ['member'])->plainTextToken)->getJson('/api/v1/me')->assertOk()
            ->assertJsonPath('data.id', $member->id)
            ->assertJsonPath('data.membership_state', 'active')
            ->assertJsonPath('data.membership.days_left', 6);

        $this->app['auth']->forgetGuards();
        $this->withoutToken()->getJson('/api/v1/me/membership')->assertUnauthorized();
    }
}
