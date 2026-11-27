<?php

namespace Tests\Feature\Api;

use App\Enums\FitnessPlanType;
use App\Jobs\SendReminderPush;
use App\Models\FitnessPlan;
use App\Models\FitnessProgram;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberMealReminder;
use App\Models\MemberReminderSetting;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\FitnessPlanService;
use App\Services\MembershipService;
use App\Services\ProgramService;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class MemberReminderApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Gym $gym;

    protected Member $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-23 10:00')); // a Monday
        $this->owner = $this->createGymOwner('Reminder Gym');
        $this->gym = $this->gymOf($this->owner);
        $this->member = $this->createMember($this->gym, ['name' => 'Aarav Shah', 'phone' => '9000000601']);
        $this->inGym($this->gym, function () {
            app(MembershipService::class)->assign($this->member, $this->createPlan($this->gym), '2026-11-01');
            app(PushNotificationService::class)->registerDevice($this->member, 'token-aarav', 'android');
        });
        Bus::fake([SendReminderPush::class]);
    }

    protected function api(): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->member->createToken('app', ['member'])->plainTextToken);
    }

    protected function settings(): MemberReminderSetting
    {
        return MemberReminderSetting::withoutGlobalScopes()->where('member_id', $this->member->id)->sole();
    }

    /** @return list<SendReminderPush> */
    protected function pushes(): array
    {
        return Bus::dispatched(SendReminderPush::class)->all();
    }

    protected function assignDiet(): FitnessPlan
    {
        $this->actingAs($this->owner);

        return $this->inGym($this->gym, function () {
            $plan = app(FitnessPlanService::class)->savePlan(null, FitnessPlanType::Diet, ['title' => 'Lean Indian diet'], [
                ['title' => 'Breakfast', 'subtitle' => '8:30 AM · ~280 kcal', 'body' => "• 2 moong dal chillas\n• Mint chutney\n• 1 bowl curd"],
                ['title' => 'Lunch', 'subtitle' => '1:30 PM · ~400 kcal', 'body' => "1 roti\n1 katori dal"],
                ['title' => 'Daily guidelines', 'subtitle' => 'Apply to every day', 'body' => 'Drink water.'],
            ]);
            $program = app(ProgramService::class)->save(null, FitnessPlanType::Diet, ['title' => 'Diet program', 'goal' => 'weight_loss', 'duration_weeks' => 4, 'is_active' => true], [
                ['fitness_plan_id' => $plan->id],
            ]);
            app(ProgramService::class)->assign($this->member, $program);

            return $plan;
        });
    }

    protected function assignWorkout(): FitnessProgram
    {
        $this->actingAs($this->owner);

        return $this->inGym($this->gym, function () {
            $plan = app(FitnessPlanService::class)->savePlan(null, FitnessPlanType::Workout, ['title' => 'Full body'], [
                ['title' => 'Warm-up', 'subtitle' => '5–8 minutes', 'body' => 'Walk'],
                ['title' => 'Squat', 'subtitle' => '4 sets × 10 reps', 'body' => null],
                ['title' => 'Bench press', 'subtitle' => '3 sets × 12 reps', 'body' => null],
                ['title' => 'Cool-down', 'subtitle' => '5 minutes', 'body' => 'Stretch'],
            ]);
            $program = app(ProgramService::class)->save(null, FitnessPlanType::Workout, ['title' => 'Strength', 'goal' => 'muscle_gain', 'duration_weeks' => 4, 'is_active' => true], [
                ['title' => 'Full body strength A', 'fitness_plan_id' => $plan->id],
                ['is_rest' => true],
            ]);
            app(ProgramService::class)->assign($this->member, $program);

            return $program;
        });
    }

    public function test_defaults_are_created_on_first_read_and_updates_are_validated(): void
    {
        $this->api()->getJson('/api/v1/me/reminders')->assertOk()
            ->assertJsonPath('data.paused_by_master_switch', false)
            ->assertJsonPath('data.hydration.enabled', false)
            ->assertJsonPath('data.hydration.interval_minutes', 60)
            ->assertJsonPath('data.hydration.start_time', '08:00')
            ->assertJsonPath('data.hydration.end_time', '21:00')
            ->assertJsonPath('data.hydration.goal_ml', 2500)
            ->assertJsonPath('data.hydration.allowed_intervals', [30, 45, 60, 90, 120, 180])
            ->assertJsonPath('data.diet.enabled', false)
            ->assertJsonPath('data.diet.has_plan', false)
            ->assertJsonPath('data.workout.time', '18:00')
            ->assertJsonPath('data.home.hydration.goal_glasses', 10)
            ->assertJsonPath('data.home.highlight.enabled_count', 0)
            ->assertJsonPath('data.home.highlight.suggest', 'hydration')
            ->assertJsonPath('data.timezone', 'Asia/Kolkata');

        $this->api()->putJson('/api/v1/me/reminders', ['hydration' => ['interval_minutes' => 50, 'goal_ml' => 9000], 'diet' => ['lead_minutes' => 5], 'workout' => ['time' => '25:00']])
            ->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonValidationErrors(['hydration.interval_minutes', 'hydration.goal_ml', 'diet.lead_minutes', 'workout.time']);

        $this->api()->putJson('/api/v1/me/reminders', ['hydration' => ['start_time' => '20:30']])
            ->assertStatus(422)->assertJsonValidationErrors(['hydration.end_time']);

        $this->api()->putJson('/api/v1/me/reminders', ['hydration' => ['enabled' => true, 'interval_minutes' => 45]])->assertOk()
            ->assertJsonPath('data.hydration.enabled', true)
            ->assertJsonPath('data.hydration.interval_minutes', 45)
            ->assertJsonPath('data.hydration.next_at', '2026-11-23T10:45:00+05:30')
            ->assertJsonPath('data.home.highlight.enabled_count', 1)
            ->assertJsonPath('data.home.highlight.next.type', 'hydration')
            ->assertJsonPath('data.home.highlight.next.title', 'Water · 10:45 AM');

        // Changing another card leaves the water schedule alone.
        $this->travelTo(Carbon::parse('2026-11-23 10:20'));
        $this->api()->putJson('/api/v1/me/reminders', ['workout' => ['enabled' => true, 'time' => '06:30']])->assertOk()
            ->assertJsonPath('data.hydration.next_at', '2026-11-23T10:45:00+05:30')
            ->assertJsonPath('data.workout.next_at', '2026-11-24T06:30:00+05:30');
    }

    public function test_hydration_reminders_follow_the_interval_and_roll_over_to_the_next_morning(): void
    {
        $this->api()->putJson('/api/v1/me/reminders', ['hydration' => ['enabled' => true]])->assertOk();
        $this->assertSame('2026-11-23 11:00', $this->settings()->hydration_next_at->format('Y-m-d H:i'));

        $this->travelTo(Carbon::parse('2026-11-23 10:55'));
        $this->artisan('push:hydration')->assertSuccessful();
        $this->assertCount(0, $this->pushes());

        $this->travelTo(Carbon::parse('2026-11-23 11:02'));
        $this->artisan('push:hydration')->assertSuccessful();
        $this->assertCount(1, $this->pushes());
        $push = $this->pushes()[0];
        $this->assertSame(['type' => 'hydration', 'screen' => 'home', 'channel' => 'water'], $push->data);
        $this->assertSame('1800s', $push->ttl);
        $this->assertSame('Start with a glass now. Goal: 10 glasses today.', $push->body);
        $this->assertSame('2026-11-23 12:00', $this->settings()->hydration_next_at->format('Y-m-d H:i'), 'No drift from the 5-minute scheduler.');

        $this->api()->postJson('/api/v1/me/hydration', ['ml' => 500])->assertCreated();
        $this->travelTo(Carbon::parse('2026-11-23 12:01'));
        $this->artisan('push:hydration');
        $this->assertSame('2 of 10 glasses today. One more now?', $this->pushes()[1]->body);

        // The last one is at the end time; after it, tomorrow at the start time.
        $this->settings()->forceFill(['hydration_next_at' => '2026-11-23 21:00:00'])->save();
        $this->travelTo(Carbon::parse('2026-11-23 21:03'));
        $this->artisan('push:hydration');
        $this->assertCount(3, $this->pushes());
        $this->assertSame('2026-11-24 08:00', $this->settings()->hydration_next_at->format('Y-m-d H:i'));
    }

    public function test_reaching_the_water_goal_stops_reminders_for_the_day_and_is_celebrated_once(): void
    {
        $this->api()->putJson('/api/v1/me/reminders', ['hydration' => ['enabled' => true]])->assertOk();

        $this->api()->postJson('/api/v1/me/hydration')->assertCreated()
            ->assertJsonPath('data.today_ml', 250)->assertJsonPath('data.glasses', 1)->assertJsonPath('data.goal_reached_now', false);
        $this->api()->postJson('/api/v1/me/hydration', ['ml' => 20])->assertStatus(422)->assertJsonValidationErrors(['ml']);

        $this->api()->postJson('/api/v1/me/hydration', ['ml' => 1250])->assertCreated()->assertJsonPath('data.goal_reached_now', false);
        $this->api()->postJson('/api/v1/me/hydration', ['ml' => 1000])->assertCreated()
            ->assertJsonPath('message', 'Goal reached. Well done!')
            ->assertJsonPath('data.today_ml', 2500)
            ->assertJsonPath('data.percent', 100)
            ->assertJsonPath('data.goal_met', true)
            ->assertJsonPath('data.goal_reached_now', true)
            ->assertJsonPath('data.next_at', null);
        $this->assertSame('2026-11-24 08:00', $this->settings()->hydration_next_at->format('Y-m-d H:i'));

        $this->api()->postJson('/api/v1/me/hydration')->assertCreated()->assertJsonPath('data.goal_reached_now', false);

        // Undo: below the goal again, reminders resume today; no second celebration.
        $this->api()->deleteJson('/api/v1/me/hydration/last')->assertOk()->assertJsonPath('data.today_ml', 2500);
        $this->api()->deleteJson('/api/v1/me/hydration/last')->assertOk()->assertJsonPath('data.today_ml', 1500);
        $this->assertSame('2026-11-23 11:00', $this->settings()->hydration_next_at->format('Y-m-d H:i'));
        $this->api()->postJson('/api/v1/me/hydration', ['ml' => 1000])->assertJsonPath('data.goal_reached_now', false);

        // Goal met: a reminder that is due anyway is not sent.
        $this->settings()->forceFill(['hydration_next_at' => '2026-11-23 15:00:00'])->save();
        $this->travelTo(Carbon::parse('2026-11-23 15:00'));
        $this->artisan('push:hydration');
        $this->assertCount(0, $this->pushes());

        $this->api()->deleteJson('/api/v1/me/hydration/last');
        $this->api()->deleteJson('/api/v1/me/hydration/last');
        $this->api()->deleteJson('/api/v1/me/hydration/last');
        $this->api()->deleteJson('/api/v1/me/hydration/last')->assertStatus(409)->assertJsonPath('code', 'NOTHING_TO_UNDO');

        $week = $this->api()->getJson('/api/v1/me/hydration')->assertOk()->assertJsonCount(7, 'data.week')->json('data.week');
        $this->assertSame(['2026-11-17', '2026-11-23'], [$week[0]['date'], $week[6]['date']]);
    }

    public function test_the_reminders_master_switch_pauses_every_reminder_but_keeps_the_settings(): void
    {
        $this->api()->putJson('/api/v1/me/reminders', ['hydration' => ['enabled' => true]])->assertOk();
        $this->api()->putJson('/api/v1/me/notification-settings', ['reminders' => false])->assertOk();

        $this->api()->getJson('/api/v1/me/reminders')->assertJsonPath('data.paused_by_master_switch', true)
            ->assertJsonPath('data.hydration.enabled', true)
            ->assertJsonPath('data.home.highlight.next', null);

        $this->travelTo(Carbon::parse('2026-11-23 11:00'));
        $this->artisan('push:hydration');
        $this->assertCount(0, $this->pushes());
    }

    public function test_meal_reminders_use_the_lead_time_skip_guidelines_and_stale_ones_and_never_duplicate(): void
    {
        $this->assignDiet();
        $this->api()->getJson('/api/v1/me/reminders')->assertJsonPath('data.diet.has_plan', true)->assertJsonPath('data.home.highlight.suggest', 'diet');

        $this->api()->putJson('/api/v1/me/reminders', ['diet' => ['enabled' => true, 'lead_minutes' => 10]])->assertOk()
            ->assertJsonCount(2, 'data.diet.today')
            ->assertJsonPath('data.diet.today.0.title', 'Breakfast')
            ->assertJsonPath('data.diet.today.0.time', '08:30')
            ->assertJsonPath('data.diet.today.0.remind_at', '2026-11-23T08:20:00+05:30')
            ->assertJsonPath('data.diet.today.1.remind_at', '2026-11-23T13:20:00+05:30')
            ->assertJsonPath('data.home.highlight.next.title', 'Lunch · 1:30 PM');

        $this->api()->putJson('/api/v1/me/reminders', ['diet' => ['lead_minutes' => 15]])->assertOk();
        $this->assertSame(2, MemberMealReminder::withoutGlobalScopes()->count());
        $this->assertSame('13:15', MemberMealReminder::withoutGlobalScopes()->where('title', 'Lunch')->sole()->send_at->format('H:i'));

        // Breakfast (8:15) is more than 30 minutes ago: skipped. Lunch goes out once.
        $this->travelTo(Carbon::parse('2026-11-23 13:16'));
        $this->artisan('push:meal-reminders')->assertSuccessful();
        $this->artisan('push:meal-reminders')->assertSuccessful();
        $this->assertCount(1, $this->pushes());
        $push = $this->pushes()[0];
        $this->assertSame('Lunch · 1:30 PM', $push->title);
        $this->assertSame('1 roti + 1 katori dal', $push->body);
        $this->assertSame('diet_meal', $push->data['type']);
        $this->assertSame('diet', $push->data['screen']);
        $this->assertSame('2026-11-23', $push->data['date']);
        $this->assertSame('3600s', $push->ttl);
        $this->api()->getJson('/api/v1/me/reminders')->assertJsonPath('data.diet.today.1.sent', true)->assertJsonPath('data.diet.today.0.sent', false);

        // Tomorrow's are built just after midnight.
        $this->travelTo(Carbon::parse('2026-11-24 00:05'));
        $this->artisan('push:build-meal-reminders')->assertSuccessful();
        $this->assertSame(2, MemberMealReminder::withoutGlobalScopes()->whereDate('date', '2026-11-24')->count());

        $this->travelTo(Carbon::parse('2026-11-24 08:15'));
        $this->artisan('push:meal-reminders');
        $this->assertSame('Breakfast · 8:30 AM', $this->pushes()[1]->title);
        $this->assertSame('2 moong dal chillas + Mint chutney', $this->pushes()[1]->body);

        // Switching them off removes the unsent ones.
        $this->api()->putJson('/api/v1/me/reminders', ['diet' => ['enabled' => false]])->assertOk();
        $this->assertSame(0, MemberMealReminder::withoutGlobalScopes()->whereDate('date', '2026-11-24')->whereNull('sent_at')->count());
    }

    public function test_changing_the_meal_time_in_the_diet_chart_moves_todays_reminder(): void
    {
        $plan = $this->assignDiet();
        $this->api()->putJson('/api/v1/me/reminders', ['diet' => ['enabled' => true]])->assertOk();

        $this->inGym($this->gym, function () use ($plan) {
            $sections = $plan->sections->map(fn ($s) => ['id' => $s->id, 'title' => $s->title, 'subtitle' => $s->subtitle, 'body' => $s->body, 'meal_time' => $s->mealClock()])->all();
            $sections[1]['meal_time'] = '14:00';
            app(FitnessPlanService::class)->savePlan($plan, FitnessPlanType::Diet, ['title' => $plan->title], $sections);
        });

        $this->assertSame('14:00', MemberMealReminder::withoutGlobalScopes()->where('title', 'Lunch')->sole()->send_at->format('H:i'));
    }

    public function test_workout_reminders_skip_rest_days_done_days_and_check_ins(): void
    {
        $this->assignWorkout();
        $this->api()->getJson('/api/v1/me/reminders')->assertJsonPath('data.workout.has_plan', true)->assertJsonPath('data.home.highlight.suggest', 'workout');
        $this->api()->putJson('/api/v1/me/reminders', ['workout' => ['enabled' => true, 'time' => '18:00']])->assertOk()
            ->assertJsonPath('data.workout.today.title', 'Full body strength A')
            ->assertJsonPath('data.workout.today.remind_at', '2026-11-23T18:00:00+05:30');

        // Monday: workout day.
        $this->travelTo(Carbon::parse('2026-11-23 18:01'));
        $this->artisan('push:workout')->assertSuccessful();
        $this->assertCount(1, $this->pushes());
        $this->assertSame('Today: Full body strength A', $this->pushes()[0]->title);
        $this->assertSame("2 exercises · about 25 min. Let's go!", $this->pushes()[0]->body);
        $this->assertSame(['type' => 'workout_reminder', 'screen' => 'workout', 'date' => '2026-11-23', 'channel' => 'reminders'], $this->pushes()[0]->data);
        $this->assertSame('2026-11-24 18:00', $this->settings()->workout_next_at->format('Y-m-d H:i'));

        // Tuesday: rest day.
        $this->travelTo(Carbon::parse('2026-11-24 18:01'));
        $this->artisan('push:workout');
        $this->assertCount(1, $this->pushes());

        // Wednesday: already marked done.
        $this->travelTo(Carbon::parse('2026-11-25 17:00'));
        $this->api()->postJson('/api/v1/me/schedule/log', ['type' => 'workout', 'status' => 'done'])->assertSuccessful();
        $this->travelTo(Carbon::parse('2026-11-25 18:01'));
        $this->artisan('push:workout');
        $this->assertCount(1, $this->pushes());

        // Friday: already checked in at the gym.
        $this->travelTo(Carbon::parse('2026-11-27 09:00'));
        $this->inGym($this->gym, fn () => app(AttendanceService::class)->checkIn($this->member));
        $this->settings()->forceFill(['workout_next_at' => '2026-11-27 18:00:00'])->save();
        $this->travelTo(Carbon::parse('2026-11-27 18:01'));
        $this->artisan('push:workout');
        $this->assertCount(1, $this->pushes());
    }

    public function test_the_gyms_daily_workout_reminder_is_not_sent_to_members_with_their_own(): void
    {
        $this->assignWorkout();
        $this->api()->putJson('/api/v1/me/reminders', ['workout' => ['enabled' => true]])->assertOk();

        $sent = $this->inGym($this->gym, fn () => app(PushNotificationService::class)->workoutReminder($this->gym));

        $this->assertSame(0, $sent);
    }

    public function test_reminders_are_only_for_members_with_a_valid_membership(): void
    {
        $this->api()->putJson('/api/v1/me/reminders', ['hydration' => ['enabled' => true]])->assertOk();
        $this->member->memberships()->withoutGlobalScopes()->update(['status' => 'cancelled']);

        $this->travelTo(Carbon::parse('2026-11-23 11:00'));
        $this->artisan('push:hydration');

        $this->assertCount(0, $this->pushes());
    }
}
