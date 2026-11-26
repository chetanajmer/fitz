<?php

namespace Tests\Feature;

use App\Livewire\Portal\WeightGoal;
use App\Models\Member;
use App\Models\WeightGoal as WeightGoalModel;
use App\Models\WeightLog;
use App\Services\WeightTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PortalWeightGoalTest extends TestCase
{
    use RefreshDatabase;

    protected Member $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->member = $this->createMember($this->gymOf($this->createGymOwner()), ['email' => 'weigh@example.test'], ['portal_password' => 'password']);
        $this->actingAs($this->member->user);
    }

    public function test_the_dashboard_shows_the_card_and_invites_a_first_weigh_in(): void
    {
        $this->get(route('portal.dashboard'))->assertOk()->assertSee('Weight goal')->assertSee('Log your weight, then set a goal');
    }

    public function test_member_logs_weights_and_sets_a_goal_to_lose(): void
    {
        Livewire::test(WeightGoal::class)
            ->call('open', 'weight')->set('weight', '92')->set('measuredOn', now()->subDays(10)->toDateString())->call('record')->assertHasNoErrors()
            ->call('open', 'goal')->set('target', '84')->set('targetDate', now()->addMonths(2)->toDateString())->call('setGoal')->assertHasNoErrors()
            ->call('open', 'weight')->set('weight', '90')->call('record')->assertHasNoErrors()->assertDispatched('weight-saved')
            ->assertSee('Lose 8 kg')->assertSee('92 kg → 84 kg')->assertSee('2 kg')->assertSee('6 kg')->assertSee('to go')->assertSee('On track');

        $logs = WeightLog::withoutGlobalScopes()->where('member_id', $this->member->id)->get();
        $this->assertSame(['app', 'app'], $logs->pluck('source')->all());
        $this->assertSame('app', WeightGoalModel::withoutGlobalScopes()->sole()->set_by);
    }

    public function test_a_gain_goal_that_falls_behind_says_so(): void
    {
        $this->travelTo(now()->subWeeks(6));
        $this->inGym($this->member->gym, fn () => app(WeightTrackingService::class)->setGoal($this->member, 70, now()->addWeeks(8)->toDateString(), 60));
        $this->travelBack();

        Livewire::test(WeightGoal::class)->call('open', 'weight')->set('weight', '61')->call('record')
            ->assertSee('Gain 10 kg')->assertSee('Behind your date')->assertSee('a week needed now');
    }

    public function test_bad_input_is_refused(): void
    {
        Livewire::test(WeightGoal::class)
            ->set('weight', '5')->call('record')->assertHasErrors('weight')
            ->set('weight', '80')->set('measuredOn', now()->addDay()->toDateString())->call('record')->assertHasErrors('measuredOn')
            ->set('measuredOn', now()->toDateString())->set('target', '70')->set('weight', '')->call('setGoal')->assertHasErrors('weight');

        $this->assertSame(0, WeightLog::withoutGlobalScopes()->count());
    }

    public function test_staff_cannot_use_the_member_card(): void
    {
        $this->actingAs($this->member->gym->owner);
        Livewire::test(WeightGoal::class)->assertForbidden();
    }
}
