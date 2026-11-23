<?php

namespace Tests\Feature;

use App\Livewire\Gym\Members\WeightProgress;
use App\Livewire\Gym\Progress;
use App\Models\Gym;
use App\Models\User;
use App\Models\WeightGoal;
use App\Services\WeightTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class WeightTrackingPanelTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-20 10:00'));
        $this->owner = $this->createGymOwner('Panel Gym');
        $this->gym = $this->gymOf($this->owner);
    }

    public function test_staff_record_weights_and_set_targets_from_the_progress_tab(): void
    {
        $member = $this->createMember($this->gym, ['name' => 'Asha', 'height_cm' => 160]);
        $this->actingAs($this->owner);

        $this->get(route('gym.members.show', [$member, 'tab' => 'progress']))->assertOk()->assertSee('Record weigh-in');

        Livewire::test(WeightProgress::class, ['member' => $member])
            ->set('weight', '68')->set('target', '62')->call('setGoal')->assertHasNoErrors()
            ->assertSeeInOrder(['68 kg', '62 kg'])
            ->set('measuredOn', '2026-11-20')->set('weight', '61.8')->call('record')->assertHasNoErrors()
            ->assertSee('Achieved');

        $goal = WeightGoal::sole();
        $this->assertSame('achieved', $goal->status);
        $this->assertSame('staff', $goal->set_by);
        $this->assertSame($this->owner->id, $goal->created_by);
    }

    public function test_gym_page_lists_due_near_and_achieved_and_saves_interval(): void
    {
        $never = $this->createMember($this->gym, ['name' => 'Never Weighed']);
        $recent = $this->createMember($this->gym, ['name' => 'Fresh Reading']);
        $close = $this->createMember($this->gym, ['name' => 'Close Call']);

        $this->inGym($this->gym, function () use ($recent, $close) {
            $weights = app(WeightTrackingService::class);
            $weights->record($recent, 70);
            $weights->setGoal($close, 70, null, 80);
            $weights->record($close, 70.8);
        });

        $this->actingAs($this->owner);
        Livewire::test(Progress::class)
            ->assertSee('Never Weighed')->assertDontSee('Fresh Reading')
            ->set('view', 'near')->assertSee('Close Call')->assertSee('Almost there')
            ->set('everyDays', 30)->call('saveInterval')->assertHasNoErrors();

        $this->assertSame(30, $this->gym->fresh()->weighInEveryDays());
        $this->get(route('gym.progress'))->assertOk()->assertSee('Weight tracking');
    }

    public function test_trainers_only_track_their_assigned_members(): void
    {
        $trainer = $this->createTrainer($this->gym);
        $mine = $this->createMember($this->gym, ['name' => 'My Client'], ['trainer_id' => $trainer->id]);
        $other = $this->createMember($this->gym, ['name' => 'Not Mine']);

        $this->actingAs($trainer);
        $this->get(route('gym.progress'))->assertOk()->assertSee('My Client')->assertDontSee('Not Mine');
        Livewire::test(WeightProgress::class, ['member' => $mine])->set('weight', '75')->call('record')->assertHasNoErrors();
        Livewire::test(WeightProgress::class, ['member' => $other])->assertForbidden();

        // Interval setting is for gym admins only.
        Livewire::test(Progress::class)->set('everyDays', 7)->call('saveInterval')->assertForbidden();
    }
}
