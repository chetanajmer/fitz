<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Services\AttendanceService;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_check_in_is_prevented(): void
    {
        $owner = $this->createGymOwner();
        $gym = $this->gymOf($owner);
        $this->actingAs($owner);
        $member = $this->createMember($gym);
        app(MembershipService::class)->assign($member, $this->createPlan($gym));

        $service = app(AttendanceService::class);
        $service->checkIn($member);

        try {
            $service->checkIn($member);
            $this->fail('Second check-in should be rejected.');
        } catch (ValidationException) {
            $this->assertSame(1, Attendance::count());
        }
    }

    public function test_member_without_valid_membership_cannot_check_in(): void
    {
        $owner = $this->createGymOwner();
        $this->actingAs($owner);

        $this->expectException(ValidationException::class);
        app(AttendanceService::class)->checkIn($this->createMember($this->gymOf($owner)));
    }

    public function test_qr_scan_toggles_check_in_and_check_out(): void
    {
        $owner = $this->createGymOwner();
        $gym = $this->gymOf($owner);
        $this->actingAs($owner);
        $member = $this->createMember($gym);
        app(MembershipService::class)->assign($member, $this->createPlan($gym));

        $this->get(route('gym.attendance.scan', $member->qr_token))->assertRedirect(route('gym.attendance.index'))->assertSessionHas('success');
        $this->assertSame('qr', Attendance::sole()->method);
        $this->assertNull(Attendance::sole()->check_out_at);

        $this->get(route('gym.attendance.scan', $member->qr_token))->assertSessionHas('success');
        $this->assertNotNull(Attendance::sole()->check_out_at);
    }

    public function test_qr_from_another_gym_is_rejected(): void
    {
        $ownerA = $this->createGymOwner('A');
        $gymB = $this->gymOf($this->createGymOwner('B'));
        $memberB = $this->createMember($gymB);

        $this->actingAs($ownerA)
            ->get(route('gym.attendance.scan', $memberB->qr_token))
            ->assertSessionHas('error');

        $this->assertSame(0, Attendance::withoutGlobalScopes()->count());
    }

    public function test_trainer_can_only_check_in_assigned_members(): void
    {
        $owner = $this->createGymOwner();
        $gym = $this->gymOf($owner);
        $trainer = $this->createTrainer($gym);
        $plan = $this->createPlan($gym);
        $assigned = $this->createMember($gym, [], ['trainer_id' => $trainer->id]);
        $other = $this->createMember($gym);
        $this->inGym($gym, function () use ($assigned, $other, $plan) {
            app(MembershipService::class)->assign($assigned, $plan);
            app(MembershipService::class)->assign($other, $plan);
        });

        $this->actingAs($trainer);
        $this->get(route('gym.attendance.scan', $other->qr_token))->assertSessionHas('error');
        $this->get(route('gym.attendance.scan', $assigned->qr_token))->assertSessionHas('success');
        $this->assertSame([$assigned->id], Attendance::pluck('member_id')->all());

        $this->get(route('gym.members.show', $other))->assertForbidden();
        $this->get(route('gym.members.show', $assigned))->assertOk();
    }
}
