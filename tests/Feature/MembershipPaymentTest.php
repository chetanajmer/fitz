<?php

namespace Tests\Feature;

use App\Enums\PlanDuration;
use App\Models\Payment;
use App\Services\MembershipService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MembershipPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_end_dates_follow_plan_durations(): void
    {
        $gym = $this->gymOf($this->createGymOwner());
        $service = app(MembershipService::class);
        $start = Carbon::parse('2026-01-31');

        $cases = [
            [PlanDuration::Monthly, null, '2026-02-27'],
            [PlanDuration::Quarterly, null, '2026-04-29'],
            [PlanDuration::HalfYearly, null, '2026-07-30'],
            [PlanDuration::Yearly, null, '2027-01-30'],
            [PlanDuration::Custom, 10, '2026-02-09'],
        ];

        foreach ($cases as [$type, $days, $expected]) {
            $plan = $this->createPlan($gym, ['duration_type' => $type, 'duration_days' => $days]);
            $this->assertSame($expected, $service->calculateEndDate($plan, $start)->toDateString(), $type->value);
        }
    }

    public function test_partial_payments_track_balance_and_receipts_are_sequential(): void
    {
        $owner = $this->createGymOwner();
        $gym = $this->gymOf($owner);
        $this->actingAs($owner);
        $member = $this->createMember($gym);
        $plan = $this->createPlan($gym, ['price' => 3000]);

        $membership = app(MembershipService::class)->assign($member, $plan, null, 500, null, ['amount' => 1000, 'method' => 'cash']);
        $this->assertEquals(2500.0, (float) $membership->total_amount);
        $this->assertEquals(1500.0, $membership->balance());
        $this->assertSame('partial', $membership->paymentStatus());

        app(PaymentService::class)->record($membership, 1500, 'upi', 'UPI123');
        $this->assertTrue($membership->fresh()->isPaid());

        $this->assertSame(
            ['RCPT-'.$gym->id.'-00001', 'RCPT-'.$gym->id.'-00002'],
            Payment::orderBy('sequence')->pluck('receipt_number')->all(),
        );

        $this->get(route('gym.payments.receipt', Payment::first()))->assertOk()->assertSee('RCPT-'.$gym->id.'-00001');
        $this->get(route('gym.memberships.invoice', $membership))->assertOk();
    }

    public function test_overpayment_is_rejected(): void
    {
        $owner = $this->createGymOwner();
        $gym = $this->gymOf($owner);
        $this->actingAs($owner);
        $membership = app(MembershipService::class)->assign($this->createMember($gym), $this->createPlan($gym, ['price' => 1000]));

        $this->expectException(ValidationException::class);
        app(PaymentService::class)->record($membership, 1000.01, 'cash');
    }

    public function test_overlapping_memberships_are_rejected_and_renewal_starts_after_current(): void
    {
        $owner = $this->createGymOwner();
        $gym = $this->gymOf($owner);
        $this->actingAs($owner);
        $member = $this->createMember($gym);
        $plan = $this->createPlan($gym);
        $service = app(MembershipService::class);

        $first = $service->assign($member, $plan, now()->toDateString());
        $renewal = $service->renew($member, $plan);
        $this->assertSame($first->end_date->copy()->addDay()->toDateString(), $renewal->start_date->toDateString());
        $this->assertSame($first->id, $renewal->renewed_from_id);

        $this->expectException(ValidationException::class);
        $service->assign($member, $plan, now()->addDays(3)->toDateString());
    }

    public function test_refresh_command_expires_lapsed_memberships(): void
    {
        $owner = $this->createGymOwner();
        $gym = $this->gymOf($owner);
        $this->actingAs($owner);
        $membership = app(MembershipService::class)->assign($this->createMember($gym), $this->createPlan($gym), now()->subMonths(3)->toDateString());
        $this->assertSame('active', $membership->status->value);

        $this->artisan('gymai:refresh-memberships')->assertSuccessful();

        $this->assertSame('expired', $membership->fresh()->status->value);
    }
}
