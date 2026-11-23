<?php

namespace Tests\Feature;

use App\Contracts\SmsSender;
use App\Livewire\Gym\Streaks;
use App\Models\Attendance;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberStreak;
use App\Services\AttendanceService;
use App\Services\GymProfileService;
use App\Services\MembershipService;
use App\Services\StreakService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class StreakTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();
        // Saturday 28 Nov 2026. Default opening hours: Sunday closed.
        $this->travelTo(Carbon::parse('2026-11-28 18:00:00'));
        $this->gym = $this->gymOf($this->createGymOwner());
    }

    protected function setRules(array $rules): void
    {
        $this->gym->settings = array_merge($this->gym->settings ?? [], ['streak' => $rules + $this->gym->streakRules()]);
        $this->gym->save();
        app(StreakService::class)->forgetRules();
    }

    /** @param list<string> $dates */
    protected function visits(Member $member, array $dates): void
    {
        $this->inGym($this->gym, function () use ($member, $dates) {
            foreach ($dates as $date) {
                $a = new Attendance;
                $a->member_id = $member->id;
                $a->date = $date;
                $a->check_in_at = Carbon::parse($date.' 07:00');
                $a->method = 'manual';
                $a->save();
            }
        });
    }

    public function test_screenshot_scenario_missed_day_within_grace_keeps_streak(): void
    {
        $member = $this->createMember($this->gym);
        // Previous week Mon–Sat, Sunday closed, then this week Mon, Tue, (Wed missed), Thu, Fri.
        $this->visits($member, ['2026-11-16', '2026-11-17', '2026-11-18', '2026-11-19', '2026-11-20', '2026-11-21',
            '2026-11-23', '2026-11-24', '2026-11-26', '2026-11-27']);

        $summary = app(StreakService::class)->summary($member);

        $this->assertSame(10, $summary['current_streak']);
        $this->assertTrue($summary['is_active']);
        $this->assertFalse($summary['at_risk']);
        $this->assertSame(10, $summary['visits_this_month']);
        $this->assertSame(
            ['attended', 'attended', 'missed', 'attended', 'attended', 'today', 'upcoming'],
            array_column($summary['week'], 'status'),
        );
        $this->assertSame(['M', 'T', 'W', 'T', 'F', 'S', 'S'], array_column($summary['week'], 'label'));
        $this->assertSame(['target' => 14, 'remaining' => 4], $summary['next_milestone']);
    }

    public function test_gap_longer_than_grace_breaks_streak_but_longest_is_kept(): void
    {
        $member = $this->createMember($this->gym);
        // 5-day chain, then a 2-open-day gap (> grace 1), then a 2-day chain.
        $this->visits($member, ['2026-11-16', '2026-11-17', '2026-11-18', '2026-11-19', '2026-11-20', '2026-11-25', '2026-11-26']);

        $calc = app(StreakService::class)->calculate($member);

        $this->assertSame(2, $calc['current']);
        $this->assertSame(5, $calc['longest']);
        $this->assertSame('2026-11-16', $calc['longest_started_on']->toDateString());
        $this->assertSame('2026-11-20', $calc['longest_ended_on']->toDateString());
    }

    public function test_streak_lapses_when_member_stops_coming(): void
    {
        $member = $this->createMember($this->gym);
        $this->visits($member, ['2026-11-23', '2026-11-24']);

        // Wed, Thu, Fri missed (3 > grace 1) — today Saturday.
        $this->assertSame(0, app(StreakService::class)->calculate($member)['current']);
        $this->assertSame(2, app(StreakService::class)->calculate($member)['longest']);
    }

    public function test_closed_days_are_skipped_and_grace_zero_requires_every_open_day(): void
    {
        $member = $this->createMember($this->gym);
        $this->setRules(['grace_days' => 0, 'skip_closed_days' => true]);
        $this->visits($member, ['2026-11-21', '2026-11-23', '2026-11-24', '2026-11-25', '2026-11-26', '2026-11-27']);

        // Sat → (Sun closed) → Mon…Fri: unbroken even with zero rest days.
        $this->assertSame(6, app(StreakService::class)->calculate($member)['current']);

        $this->setRules(['skip_closed_days' => false]);
        $this->assertSame(5, app(StreakService::class)->calculate($member)['current']);
    }

    public function test_at_risk_when_skipping_today_would_break_the_streak(): void
    {
        $member = $this->createMember($this->gym);
        $this->visits($member, ['2026-11-24', '2026-11-26']); // Fri missed → grace used up

        $calc = app(StreakService::class)->calculate($member);
        $this->assertTrue($calc['alive']);
        $this->assertTrue($calc['at_risk']);
        $this->assertSame(2, $calc['current']);

        $this->visits($member, ['2026-11-28']); // came today
        $calc = app(StreakService::class)->calculate($member);
        $this->assertFalse($calc['at_risk']);
        $this->assertSame(3, $calc['current']);
    }

    public function test_check_in_updates_stored_streak_and_milestone(): void
    {
        $owner = $this->gym->owner;
        $member = $this->createMember($this->gym);
        $this->inGym($this->gym, fn () => app(MembershipService::class)->assign($member, $this->createPlan($this->gym), '2026-11-01'));
        $this->visits($member, ['2026-11-20', '2026-11-21', '2026-11-23', '2026-11-24', '2026-11-25', '2026-11-26', '2026-11-27']);

        $this->actingAs($owner);
        app(AttendanceService::class)->checkIn($member);

        $stored = MemberStreak::where('member_id', $member->id)->sole();
        $this->assertSame(8, $stored->current_streak);
        $this->assertSame(8, $stored->total_visit_days);
        $this->assertSame('2026-11-28', $stored->last_visit_on->toDateString());
        $this->assertSame(7, $stored->highest_milestone);
    }

    public function test_member_api_returns_streak(): void
    {
        $this->app->instance(SmsSender::class, new class implements SmsSender
        {
            public ?string $code = null;

            public function send(string $phone, string $message): void
            {
                $this->code = substr($message, 0, 6);
            }
        });

        $member = $this->createMember($this->gym, ['phone' => '9000000301']);
        $this->visits($member, ['2026-11-26', '2026-11-27']);

        $this->postJson('/api/v1/auth/send-otp', ['phone' => '9000000301'])->assertOk();
        $token = $this->postJson('/api/v1/auth/verify-otp', ['phone' => '9000000301', 'otp' => app(SmsSender::class)->code])->json('data.token');

        $this->withToken($token)->getJson('/api/v1/me/streak')
            ->assertOk()
            ->assertJsonPath('data.current_streak', 2)
            ->assertJsonPath('data.checked_in_today', false)
            ->assertJsonPath('data.visits_this_month', 2)
            ->assertJsonCount(7, 'data.week')
            ->assertJsonPath('data.week.5.status', 'today')
            ->assertJsonStructure(['data' => ['longest_streak', 'is_active', 'at_risk', 'next_milestone', 'milestones', 'rules']]);

        $this->app['auth']->forgetGuards();
        $this->withoutToken()->getJson('/api/v1/me/streak')->assertStatus(401);
    }

    public function test_admin_manages_streak_rules_and_trainer_cannot_open_page(): void
    {
        $member = $this->createMember($this->gym, ['name' => 'Streaky']);
        $this->visits($member, ['2026-11-23', '2026-11-25', '2026-11-27']); // every other day
        $this->inGym($this->gym, fn () => app(StreakService::class)->refreshGym($this->gym));
        $this->assertSame(3, MemberStreak::withoutGlobalScopes()->sole()->current_streak);

        $this->actingAs($this->gym->owner);
        $this->get(route('gym.streaks'))->assertOk()->assertSee('Streaky');

        Livewire::test(Streaks::class)
            ->set('graceDays', 0)
            ->set('milestones', '5, 10, 3')
            ->call('saveRules')
            ->assertHasNoErrors();

        $this->gym->refresh();
        $this->assertSame(0, $this->gym->streakRules()['grace_days']);
        $this->assertSame([3, 5, 10], $this->gym->streakRules()['milestones']);
        $this->assertSame(1, MemberStreak::withoutGlobalScopes()->sole()->current_streak);

        // Saving the gym profile must not wipe streak rules.
        app(GymProfileService::class)->update($this->gym, ['name' => 'Renamed'], ['primary_color' => '#123456']);
        $this->assertSame(0, $this->gym->fresh()->streakRules()['grace_days']);

        $trainer = $this->createTrainer($this->gym);
        $this->actingAs($trainer)->get(route('gym.streaks'))->assertForbidden();
    }

    public function test_streak_cards_render_on_member_profile_and_portal(): void
    {
        $member = $this->createMember($this->gym, ['email' => 'p@example.test'], ['portal_password' => 'password']);
        $this->visits($member, ['2026-11-26', '2026-11-27']);

        $this->actingAs($this->gym->owner)->get(route('gym.members.show', $member))->assertOk()->assertSee('2 day streak');
        $this->actingAs($member->user)->get(route('portal.dashboard'))->assertOk()->assertSee('2 day streak');
    }
}
