<?php

namespace Tests\Feature;

use App\Livewire\Gym\Enquiries\Show as EnquiryShow;
use App\Livewire\Gym\Members\Form as MemberForm;
use App\Livewire\Gym\Members\Show as MemberShow;
use App\Livewire\Gym\Trainers\Show as TrainerShow;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\User;
use App\Services\EnquiryService;
use App\Services\TrainerService;
use App\Support\TrainerAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TrainerAccessTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Gym $gym;

    protected User $trainer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->createGymOwner('Access Gym');
        $this->gym = $this->gymOf($this->owner);
        $this->trainer = $this->createTrainer($this->gym);
    }

    protected function grant(array $permissions): void
    {
        $this->inGym($this->gym, fn () => app(TrainerService::class)->setPermissions($this->trainer, $permissions));
        $this->trainer->refresh()->unsetRelation('permissions');
    }

    public function test_new_trainers_get_the_default_sections(): void
    {
        $this->assertEqualsCanonicalizing(TrainerAccess::DEFAULTS, $this->trainer->getDirectPermissions()->pluck('name')->all());

        $this->actingAs($this->trainer)->get(route('gym.dashboard'))->assertOk()
            ->assertSee('My Members')->assertSee('Attendance')->assertSee('Diet Charts')->assertDontSee('Enquiries');
    }

    public function test_admin_sets_registration_only_access_from_the_trainer_page(): void
    {
        $this->actingAs($this->owner);
        Livewire::test(TrainerShow::class, ['trainer' => $this->trainer->id])
            ->assertSee('Sections this trainer can see')
            ->call('applyPreset', 'registration')
            ->call('saveAccess')
            ->assertHasNoErrors()
            ->assertSet('access', ['members.view', 'members.manage']);

        $trainer = $this->trainer->fresh();
        $this->actingAs($trainer);
        $this->get(route('gym.dashboard'))->assertOk()->assertSee('My Members')->assertDontSee('Diet Charts')->assertDontSee('Payments');
        $this->get(route('gym.members.create'))->assertOk();
        $this->get(route('gym.attendance.index'))->assertForbidden();
        $this->get(route('gym.content.index', 'diet'))->assertForbidden();
        $this->get(route('gym.payments.index'))->assertForbidden();
        $this->get(route('gym.trainers.index'))->assertForbidden();
    }

    public function test_registration_trainer_keeps_members_they_register_but_not_others(): void
    {
        $this->grant(['members.view', 'members.manage']);
        $othersMember = $this->createMember($this->gym, ['name' => 'Someone Else']);

        $this->actingAs($this->trainer);
        Livewire::test(MemberForm::class)
            ->set('form.name', 'New Walkin')
            ->set('form.phone', '9876501234')
            ->set('trainer_id', '')
            ->call('save')
            ->assertHasNoErrors();

        $created = $this->inGym($this->gym, fn () => Member::where('name', 'New Walkin')->sole());
        $this->assertSame($this->trainer->id, $created->trainer_id, 'registered members are assigned to the trainer');

        $this->get(route('gym.members.index'))->assertOk()->assertSee('New Walkin')->assertDontSee('Someone Else');
        $this->get(route('gym.members.show', $created))->assertOk();
        $this->get(route('gym.members.show', $othersMember))->assertForbidden();
        $this->get(route('gym.members.edit', $othersMember))->assertForbidden();

        // No memberships / payments section: plan and payment actions are blocked.
        Livewire::test(MemberShow::class, ['member' => $created])
            ->assertDontSee('Assign plan')
            ->call('openPlanModal')->assertForbidden();
    }

    public function test_see_all_members_grant_opens_the_whole_gym(): void
    {
        $this->grant(['members.all']);
        $this->assertEqualsCanonicalizing(['members.view', 'members.all'], $this->trainer->getDirectPermissions()->pluck('name')->all(), 'members.view added automatically');

        $member = $this->createMember($this->gym, ['name' => 'Gym Wide Person']);
        $this->actingAs($this->trainer)->get(route('gym.members.index'))->assertOk()->assertSee('Gym Wide Person')->assertSee('Members');
        $this->get(route('gym.members.show', $member))->assertOk();
        $this->get(route('gym.members.edit', $member))->assertForbidden();
    }

    public function test_enquiry_conversion_only_uses_granted_sections(): void
    {
        $this->grant(['members.view', 'members.manage', 'enquiries.manage']);
        $plan = $this->createPlan($this->gym);
        $enquiry = $this->inGym($this->gym, fn () => app(EnquiryService::class)->create(['name' => 'Lead Person', 'phone' => '9811122233']));

        $this->actingAs($this->trainer);
        $this->get(route('gym.enquiries.index'))->assertOk();
        Livewire::test(EnquiryShow::class, ['enquiry' => $enquiry])
            ->call('openConvert')
            ->assertDontSee('Membership (optional)')
            ->set('convert.plan_id', (string) $plan->id)
            ->set('convert.pay_now', true)
            ->set('convert.amount', '1000')
            ->call('convertToMember')
            ->assertHasNoErrors();

        $member = $this->inGym($this->gym, fn () => Member::where('phone', '9811122233')->sole());
        $this->assertSame($this->trainer->id, $member->trainer_id);
        $this->assertSame(0, $this->inGym($this->gym, fn () => Membership::count() + Payment::count()));
    }

    public function test_only_sections_trainers_may_hold_are_granted(): void
    {
        $this->grant(['gym.settings', 'gym.billing', 'trainers.manage', 'platform.manage', 'content.manage']);

        $this->assertEqualsCanonicalizing(['content.custom', 'content.manage'], $this->trainer->getDirectPermissions()->pluck('name')->all());
        $this->actingAs($this->trainer)->get(route('gym.settings'))->assertForbidden();
        $this->get(route('gym.billing'))->assertForbidden();
        $this->get(route('gym.members.index'))->assertForbidden();
    }

    public function test_dashboard_only_trainer_and_cross_gym_protection(): void
    {
        $this->actingAs($this->owner);
        Livewire::test(TrainerShow::class, ['trainer' => $this->trainer->id])->call('applyPreset', 'none')->call('saveAccess');
        $this->assertCount(0, $this->trainer->fresh()->getDirectPermissions());
        $this->actingAs($this->trainer->fresh())->get(route('gym.dashboard'))->assertOk()->assertDontSee('My Members');

        // Another gym's admin can neither open nor change this trainer.
        $otherOwner = $this->createGymOwner('Other Gym');
        $this->actingAs($otherOwner)->get(route('gym.trainers.show', $this->trainer->id))->assertNotFound();

        // Trainers can never edit their own access.
        $this->actingAs($this->trainer->fresh())->get(route('gym.trainers.show', $this->trainer->id))->assertForbidden();
    }
}
