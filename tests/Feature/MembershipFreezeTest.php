<?php

namespace Tests\Feature;

use App\Livewire\Gym\Members\Show;
use App\Livewire\Gym\Plans\Index as Plans;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipFreeze;
use App\Models\MembershipPlan;
use App\Services\AttendanceService;
use App\Services\MemberMembershipService;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class MembershipFreezeTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected Member $member;

    protected Membership $membership;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-10 10:00'));
        $this->gym = $this->gymOf($this->createGymOwner());
        $this->member = $this->createMember($this->gym);
        $plan = $this->createPlan($this->gym, ['max_freeze_days' => 15]);
        $this->membership = $this->inGym($this->gym, fn () => app(MembershipService::class)->assign($this->member, $plan, '2026-11-01'));
        $this->actingAs($this->gym->owner);
    }

    protected function show()
    {
        return Livewire::test(Show::class, ['member' => $this->member]);
    }

    public function test_a_plan_sets_the_freeze_days_and_new_memberships_keep_them(): void
    {
        Livewire::test(Plans::class)->call('create')->set('form.name', 'Gold')->set('form.price', '3000')->set('form.max_freeze_days', '20')->call('save')->assertHasNoErrors();

        $this->assertSame(20, $this->inGym($this->gym, fn () => MembershipPlan::where('name', 'Gold')->sole()->max_freeze_days));
        $this->assertSame(15, $this->membership->max_freeze_days);
    }

    public function test_staff_freeze_moves_the_end_date_and_blocks_check_in(): void
    {
        $end = $this->membership->end_date->copy();

        $this->show()->call('openFreeze', $this->membership->id)
            ->set('freezeForm.start_date', '2026-11-10')->set('freezeForm.days', '10')->set('freezeForm.reason', 'Travel')
            ->call('saveFreeze')->assertHasNoErrors()->assertSet('showFreezeModal', false);

        $this->assertSame($end->copy()->addDays(10)->toDateString(), $this->membership->fresh()->end_date->toDateString());
        $this->assertSame('Travel', MembershipFreeze::withoutGlobalScopes()->sole()->reason);
        $this->show()->call('checkIn')->assertHasErrors('member');

        // Ended early on day 4: the 6 unused days come off the end date again, and check-in works.
        $this->travelTo(Carbon::parse('2026-11-14 09:00'));
        $this->show()->call('endFreeze', MembershipFreeze::withoutGlobalScopes()->sole()->id);
        $freeze = MembershipFreeze::withoutGlobalScopes()->sole();
        $this->assertSame([4, '2026-11-13'], [$freeze->days, $freeze->end_date->toDateString()]);
        $this->assertSame($end->copy()->addDays(4)->toDateString(), $this->membership->fresh()->end_date->toDateString());
        $this->inGym($this->gym, fn () => app(AttendanceService::class)->checkIn($this->member->fresh()));
    }

    public function test_limits_are_enforced(): void
    {
        $freeze = fn (string $from, string $days) => $this->show()->call('openFreeze', $this->membership->id)
            ->set('freezeForm.start_date', $from)->set('freezeForm.days', $days)->call('saveFreeze');

        $freeze('2026-11-12', '16')->assertHasErrors('freeze');   // more than the plan allows
        $freeze('2026-11-09', '2')->assertHasErrors('freeze');    // in the past
        $freeze('2026-11-12', '10')->assertHasNoErrors();
        $freeze('2026-11-15', '3')->assertHasErrors('freeze');    // overlaps
        $freeze('2026-11-25', '6')->assertHasErrors('freeze');    // only 5 left
        $freeze('2026-11-25', '5')->assertHasNoErrors();

        // A planned freeze that has not started can be removed; its days come back.
        $planned = MembershipFreeze::withoutGlobalScopes()->whereDate('start_date', '2026-11-25')->sole();
        $this->show()->call('endFreeze', $planned->id);
        $this->assertSame(1, MembershipFreeze::withoutGlobalScopes()->count());
        $this->assertSame(5, $this->membership->fresh()->load('freezes')->freezeDaysLeft());
    }

    public function test_plans_without_freeze_days_and_booked_renewals_cannot_be_frozen(): void
    {
        $plain = $this->createPlan($this->gym, ['name' => 'No freeze']);
        $other = $this->createMember($this->gym);
        $noFreeze = $this->inGym($this->gym, fn () => app(MembershipService::class)->assign($other, $plain, '2026-11-01'));
        Livewire::test(Show::class, ['member' => $other])->call('openFreeze', $noFreeze->id)
            ->set('freezeForm.start_date', '2026-11-11')->set('freezeForm.days', '3')->call('saveFreeze')->assertHasErrors('freeze');

        $this->inGym($this->gym, fn () => app(MembershipService::class)->renew($this->member, $plain));
        $this->show()->call('openFreeze', $this->membership->id)
            ->set('freezeForm.start_date', '2026-11-11')->set('freezeForm.days', '3')->call('saveFreeze')->assertHasErrors('freeze');
    }

    public function test_the_member_sees_the_pause_in_the_app_and_portal(): void
    {
        $this->inGym($this->gym, fn () => app(MembershipService::class)->freeze($this->membership, '2026-11-10', 5));

        $card = $this->inGym($this->gym, fn () => app(MemberMembershipService::class)->card($this->member->fresh()));
        $this->assertSame(['2026-11-14', 5, 15], [$card['frozen_until'], $card['freeze_days_used'], $card['freeze_days_allowed']]);
        $this->assertStringStartsWith('Paused until 14 Nov 2026', $card['message']);
    }

    public function test_trainers_cannot_freeze(): void
    {
        $trainer = $this->createTrainer($this->gym);
        $this->member->forceFill(['trainer_id' => $trainer->id])->save();
        $this->actingAs($trainer);

        $this->show()->call('openFreeze', $this->membership->id)->assertForbidden();
    }
}
