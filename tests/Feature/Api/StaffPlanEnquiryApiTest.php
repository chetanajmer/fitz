<?php

namespace Tests\Feature\Api;

use App\Enums\MemberStatus;
use App\Models\Enquiry;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\EnquiryService;
use App\Services\MembershipService;
use App\Services\TrainerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffPlanEnquiryApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->createGymOwner('Iron Den');
        $this->gym = $this->gymOf($this->owner);
    }

    protected function asStaff(User $user): static
    {
        $this->app['auth']->forgetGuards();
        $token = $this->postJson('/api/v1/admin/auth/login', ['email' => $user->email, 'password' => 'password'])->assertOk()->json('data.token');

        return $this->withToken($token);
    }

    /** @param  list<string>  $permissions */
    protected function trainerWith(array $permissions): User
    {
        $trainer = $this->createTrainer($this->gym);
        $this->inGym($this->gym, fn () => app(TrainerService::class)->setPermissions($trainer, $permissions));

        return $trainer->fresh();
    }

    public function test_plans_can_be_created_edited_switched_off_and_archived(): void
    {
        $api = $this->asStaff($this->owner);

        $api->postJson('/api/v1/admin/plans', ['name' => 'Custom', 'duration_type' => 'custom', 'price' => 500])
            ->assertStatus(422)->assertJsonValidationErrors(['duration_days'], 'errors');

        $id = $api->postJson('/api/v1/admin/plans', [
            'name' => 'Quarterly', 'duration_type' => 'quarterly', 'price' => 2700, 'features' => "Locker\n \nDiet chart",
        ])->assertCreated()
            ->assertJsonPath('data.plan.duration_days', 91)
            ->assertJsonPath('data.plan.features', ['Locker', 'Diet chart'])
            ->assertJsonPath('data.plan.is_active', true)
            ->json('data.plan.id');

        $api->putJson("/api/v1/admin/plans/{$id}", ['price' => 2500, 'duration_type' => 'custom', 'duration_days' => 75])->assertOk()
            ->assertJsonPath('data.plan.price', 2500)
            ->assertJsonPath('data.plan.duration_days', 75)
            ->assertJsonPath('data.plan.name', 'Quarterly');

        $member = $this->createMember($this->gym);
        $this->inGym($this->gym, fn () => app(MembershipService::class)->assign($member, MembershipPlan::find($id), now()->toDateString()));

        $api->postJson("/api/v1/admin/plans/{$id}/toggle")->assertOk()->assertJsonPath('data.plan.is_active', false);
        $api->getJson('/api/v1/admin/plans?status=inactive')->assertOk()
            ->assertJsonPath('data.plans.0.id', $id)
            ->assertJsonPath('data.plans.0.active_members', 1);

        $api->deleteJson("/api/v1/admin/plans/{$id}")->assertOk();
        $api->getJson("/api/v1/admin/plans/{$id}")->assertNotFound()->assertJsonPath('code', 'NOT_FOUND');
        $this->assertSame(1, $member->memberships()->count());

        $other = $this->createPlan($this->gymOf($this->createGymOwner('Other')));
        $api->putJson("/api/v1/admin/plans/{$other->id}", ['price' => 1])->assertNotFound();
    }

    public function test_plans_need_the_plans_permission(): void
    {
        $this->asStaff($this->trainerWith(['members.view']))->getJson('/api/v1/admin/plans')->assertForbidden();
        $this->asStaff($this->trainerWith(['plans.manage']))->getJson('/api/v1/admin/plans')->assertOk();
    }

    public function test_enquiries_are_listed_followed_up_and_noted(): void
    {
        $api = $this->asStaff($this->owner);

        $id = $api->postJson('/api/v1/admin/enquiries', ['name' => 'Ravi', 'phone' => '9811111111', 'follow_up_date' => now()->toDateString()])
            ->assertCreated()
            ->assertJsonPath('data.enquiry.status', 'new')
            ->assertJsonPath('data.enquiry.source', 'walk_in')
            ->assertJsonPath('data.timeline.0.type', 'created')
            ->json('data.enquiry.id');

        $api->getJson('/api/v1/admin/enquiries?follow_up=due')->assertOk()
            ->assertJsonPath('data.counts.new', 1)
            ->assertJsonPath('data.counts.follow_up_due', 1)
            ->assertJsonPath('data.enquiries.0.follow_up_due', true);

        $api->postJson("/api/v1/admin/enquiries/{$id}/status", ['status' => 'converted'])->assertStatus(422);
        $api->postJson("/api/v1/admin/enquiries/{$id}/status", ['status' => 'follow_up'])->assertStatus(422)->assertJsonValidationErrors(['follow_up_date'], 'errors');
        $api->getJson('/api/v1/admin/enquiries?follow_up=today')->assertOk()->assertJsonCount(1, 'data.enquiries');
        $api->getJson('/api/v1/admin/enquiries?follow_up=overdue')->assertOk()->assertJsonCount(0, 'data.enquiries');
        $api->postJson("/api/v1/admin/enquiries/{$id}/status", ['status' => 'interested', 'follow_up_date' => now()->addDays(3)->toDateString(), 'note' => 'Called'])
            ->assertOk()
            ->assertJsonPath('data.enquiry.status', 'interested')
            ->assertJsonPath('data.timeline.0.text', 'Called');

        $api->postJson("/api/v1/admin/enquiries/{$id}/notes", ['note' => 'Wants mornings'])->assertCreated()
            ->assertJsonPath('data.timeline.0.text', 'Wants mornings');

        $api->putJson("/api/v1/admin/enquiries/{$id}", ['email' => 'ravi@example.test'])->assertOk()
            ->assertJsonPath('data.enquiry.email', 'ravi@example.test')
            ->assertJsonPath('data.enquiry.name', 'Ravi');

        $api->getJson('/api/v1/admin/enquiries/options')->assertOk()->assertJsonCount(7, 'data.sources');
    }

    public function test_converting_an_enquiry_creates_the_member_with_a_plan_and_payment_once(): void
    {
        $plan = $this->createPlan($this->gym, ['price' => 1200]);
        $enquiry = $this->inGym($this->gym, fn () => app(EnquiryService::class)->create(['name' => 'Asha', 'phone' => '9822222222']));
        $api = $this->asStaff($this->owner);

        $api->postJson("/api/v1/admin/enquiries/{$enquiry->id}/convert", ['plan_id' => $plan->id, 'pay_now' => true, 'amount' => 500])
            ->assertStatus(422)->assertJsonValidationErrors(['method'], 'errors');

        $memberId = $api->postJson("/api/v1/admin/enquiries/{$enquiry->id}/convert", [
            'plan_id' => $plan->id, 'pay_now' => true, 'amount' => 500, 'method' => 'upi', 'gender' => 'female',
        ])->assertCreated()
            ->assertJsonPath('data.enquiry.status', 'converted')
            ->json('data.member_id');

        $member = $this->inGym($this->gym, fn () => Member::with('memberships')->find($memberId));
        $this->assertSame('Asha', $member->name);
        $this->assertEquals(500, $member->memberships->first()->amount_paid);

        $api->postJson("/api/v1/admin/enquiries/{$enquiry->id}/convert", [])->assertStatus(409)
            ->assertJsonPath('code', 'ALREADY_CONVERTED')
            ->assertJsonPath('data.member_id', $memberId);
    }

    public function test_a_front_desk_trainer_converts_without_plan_rights_only_without_a_plan(): void
    {
        $plan = $this->createPlan($this->gym);
        $enquiry = $this->inGym($this->gym, fn () => app(EnquiryService::class)->create(['name' => 'Kiran', 'phone' => '9833333333']));
        $trainer = $this->trainerWith(['members.view', 'members.manage', 'enquiries.manage']);
        $api = $this->asStaff($trainer);

        $api->postJson("/api/v1/admin/enquiries/{$enquiry->id}/convert", ['plan_id' => $plan->id])->assertForbidden();
        $memberId = $api->postJson("/api/v1/admin/enquiries/{$enquiry->id}/convert", [])->assertCreated()->json('data.member_id');

        $this->assertSame($trainer->id, $this->inGym($this->gym, fn () => Member::find($memberId)->trainer_id));
    }

    public function test_join_requests_are_approved_or_declined(): void
    {
        $first = $this->createMember($this->gym, ['status' => MemberStatus::Pending->value]);
        $second = $this->createMember($this->gym, ['status' => MemberStatus::Pending->value]);
        $active = $this->createMember($this->gym);
        $api = $this->asStaff($this->owner);

        $api->getJson('/api/v1/admin/join-requests')->assertOk()->assertJsonCount(2, 'data.join_requests');
        $api->getJson('/api/v1/admin/enquiries')->assertOk()->assertJsonPath('data.join_requests', 2);

        $api->postJson("/api/v1/admin/join-requests/{$first->id}/approve")->assertOk();
        $this->assertSame(MemberStatus::Active, $first->fresh()->status);

        $api->postJson("/api/v1/admin/join-requests/{$second->id}/decline")->assertOk();
        $this->assertSoftDeleted('members', ['id' => $second->id]);

        $api->postJson("/api/v1/admin/join-requests/{$active->id}/approve")->assertStatus(409)->assertJsonPath('code', 'NOT_PENDING');

        $this->asStaff($this->trainerWith(['enquiries.manage']))->getJson('/api/v1/admin/join-requests')->assertForbidden();
        $this->assertSame(0, $this->inGym($this->gym, fn () => Enquiry::count()));
    }
}
