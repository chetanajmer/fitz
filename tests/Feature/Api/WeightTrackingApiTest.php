<?php

namespace Tests\Feature\Api;

use App\Models\Gym;
use App\Models\Member;
use App\Models\WeightGoal;
use App\Models\WeightLog;
use App\Services\WeightTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WeightTrackingApiTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected Member $member;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-01 09:00'));
        $this->gym = $this->gymOf($this->createGymOwner('Weight Gym'));
        $this->member = $this->createMember($this->gym, ['height_cm' => 170]);
        $this->token = $this->member->createToken('app', ['member'])->plainTextToken;
    }

    protected function api(): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->token);
    }

    public function test_new_member_is_asked_for_a_first_weigh_in(): void
    {
        $this->api()->getJson('/api/v1/me/weight')->assertOk()
            ->assertJsonPath('data.current_weight_kg', null)
            ->assertJsonPath('data.goal', null)
            ->assertJsonPath('data.reminder.is_due', true)
            ->assertJsonPath('data.reminder.every_days', 15)
            ->assertJsonPath('data.popup.type', 'weigh_in_due');
    }

    public function test_weight_loss_journey_from_goal_to_almost_there_to_achieved(): void
    {
        // Set target with current weight in one call.
        $this->api()->postJson('/api/v1/me/weight/goal', ['current_weight_kg' => 82, 'target_weight_kg' => 72, 'target_date' => '2027-03-01'])
            ->assertCreated()
            ->assertJsonPath('data.current_weight_kg', 82)
            ->assertJsonPath('data.goal.direction', 'lose')
            ->assertJsonPath('data.goal.total_change_kg', 10)
            ->assertJsonPath('data.goal.remaining_kg', 10)
            ->assertJsonPath('data.goal.progress_percent', 0)
            ->assertJsonPath('data.bmi.value', 28.4)
            ->assertJsonPath('data.bmi.category', 'Overweight')
            ->assertJsonPath('data.reminder.next_due_on', '2026-11-16')
            ->assertJsonPath('data.popup', null);

        // Halfway, not yet near.
        $this->travelTo(Carbon::parse('2026-11-20 09:00'));
        $this->api()->getJson('/api/v1/me/weight')->assertJsonPath('data.reminder.is_due', true)->assertJsonPath('data.reminder.overdue_days', 4)
            ->assertJsonPath('data.popup.type', 'weigh_in_due');
        $this->api()->postJson('/api/v1/me/weight', ['weight_kg' => 77])->assertCreated()
            ->assertJsonPath('data.goal_achieved', false)
            ->assertJsonPath('data.change_since_last_kg', -5)
            ->assertJsonPath('data.goal.progress_percent', 50)
            ->assertJsonPath('data.goal.is_near_target', false)
            ->assertJsonPath('data.popup', null);

        // 1 kg away → "almost there" popup until acknowledged.
        $this->travelTo(Carbon::parse('2026-12-05 09:00'));
        $this->api()->postJson('/api/v1/me/weight', ['weight_kg' => 73])
            ->assertJsonPath('data.goal.remaining_kg', 1)
            ->assertJsonPath('data.goal.is_near_target', true)
            ->assertJsonPath('data.popup.type', 'almost_there');
        $this->api()->postJson('/api/v1/me/weight/popup/ack', ['type' => 'almost_there'])->assertOk()->assertJsonPath('data.popup', null);

        // Target reached → achieved + celebration popup until acknowledged.
        $this->travelTo(Carbon::parse('2026-12-18 09:00'));
        $this->api()->postJson('/api/v1/me/weight', ['weight_kg' => 71.6])
            ->assertJsonPath('data.goal_achieved', true)
            ->assertJsonPath('message', 'Congratulations! You reached your target weight.')
            ->assertJsonPath('data.goal.status', 'achieved')
            ->assertJsonPath('data.goal.progress_percent', 100)
            ->assertJsonPath('data.popup.type', 'goal_achieved')
            ->assertJsonPath('data.change_since_start_kg', -10.4)
            ->assertJsonCount(4, 'data.history');
        $this->assertSame('achieved', WeightGoal::withoutGlobalScopes()->sole()->status);

        $this->api()->postJson('/api/v1/me/weight/popup/ack', ['type' => 'goal_achieved'])->assertJsonPath('data.popup', null)
            ->assertJsonPath('data.goal.status', 'achieved');

        // A new goal replaces nothing (old one already achieved) and starts from the latest weight.
        $this->api()->postJson('/api/v1/me/weight/goal', ['target_weight_kg' => 70])->assertCreated()
            ->assertJsonPath('data.goal.status', 'active')->assertJsonPath('data.goal.start_weight_kg', 71.6);
    }

    public function test_weight_gain_goal_and_same_day_correction(): void
    {
        $this->api()->postJson('/api/v1/me/weight', ['weight_kg' => 55])->assertCreated();
        $this->api()->postJson('/api/v1/me/weight/goal', ['target_weight_kg' => 60])->assertJsonPath('data.goal.direction', 'gain');

        // Re-entering today's weight corrects it instead of adding a second reading.
        $this->api()->postJson('/api/v1/me/weight', ['weight_kg' => 56])->assertJsonCount(1, 'data.history')->assertJsonPath('data.current_weight_kg', 56);

        $this->travelTo(Carbon::parse('2026-11-25'));
        $this->api()->postJson('/api/v1/me/weight', ['weight_kg' => 60.2])->assertJsonPath('data.goal_achieved', true);
    }

    public function test_validation_and_deleting_a_reading(): void
    {
        $this->api()->postJson('/api/v1/me/weight', ['weight_kg' => 5])->assertStatus(422)->assertJsonValidationErrors('weight_kg');
        $this->api()->postJson('/api/v1/me/weight', ['weight_kg' => 70, 'measured_on' => '2026-12-01'])->assertStatus(422)->assertJsonValidationErrors('measured_on');
        $this->api()->postJson('/api/v1/me/weight/goal', ['target_weight_kg' => 70])->assertStatus(422)->assertJsonValidationErrors('current_weight_kg');

        $this->api()->postJson('/api/v1/me/weight', ['weight_kg' => 70])->assertCreated();
        $this->api()->postJson('/api/v1/me/weight/goal', ['target_weight_kg' => 70.2])->assertStatus(422)->assertJsonValidationErrors('target_weight_kg');

        $log = WeightLog::withoutGlobalScopes()->sole();
        $this->api()->deleteJson("/api/v1/me/weight/{$log->id}")->assertOk()->assertJsonPath('data.current_weight_kg', null);

        // Cannot delete another member's reading.
        $other = $this->createMember($this->gym);
        $otherLog = $this->inGym($this->gym, fn () => app(WeightTrackingService::class)->record($other, 80)['log']);
        $this->api()->deleteJson("/api/v1/me/weight/{$otherLog->id}")->assertNotFound();
    }

    public function test_reminder_interval_follows_gym_setting_and_profile_includes_weight(): void
    {
        $this->gym->settings = array_merge($this->gym->settings ?? [], ['progress' => ['weigh_in_every_days' => 30]]);
        $this->gym->save();

        $this->api()->postJson('/api/v1/me/weight', ['weight_kg' => 70])->assertJsonPath('data.reminder.next_due_on', '2026-12-01');
        $this->api()->getJson('/api/v1/me/profile')->assertOk()
            ->assertJsonPath('data.personal.height_cm', 170)
            ->assertJsonPath('data.weight.current_weight_kg', 70)
            ->assertJsonMissingPath('data.weight.history');
        $this->api()->patchJson('/api/v1/me/profile', ['height_cm' => 165.5])->assertOk()->assertJsonPath('data.personal.height_cm', 165.5);
    }
}
