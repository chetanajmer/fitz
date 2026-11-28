<?php

namespace Tests\Feature\Api;

use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\PaymentService;
use App\Services\TrainerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class StaffFeeApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Gym $gym;

    protected MembershipPlan $monthly;

    protected MembershipPlan $quarterly;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->createGymOwner('Iron Den');
        $this->gym = $this->gymOf($this->owner);
        $this->monthly = $this->createPlan($this->gym, ['name' => 'Monthly', 'price' => 1000]);
        $this->quarterly = $this->createPlan($this->gym, ['name' => 'Quarterly', 'duration_type' => 'quarterly', 'duration_days' => 90, 'price' => 2700]);
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

    protected function sell(Member $member, MembershipPlan $plan, float $paid = 0, ?string $start = null, string $method = 'cash'): Membership
    {
        return $this->inGym($this->gym, fn () => app(MembershipService::class)->assign(
            $member, $plan, $start ?? now()->toDateString(), 0, null, $paid > 0 ? ['amount' => $paid, 'method' => $method] : null,
        ));
    }

    public function test_assigning_a_plan_with_a_part_payment_returns_the_members_fee_summary(): void
    {
        $member = $this->createMember($this->gym);
        $api = $this->asStaff($this->owner);

        $api->postJson("/api/v1/admin/members/{$member->id}/memberships", ['plan_id' => $this->monthly->id, 'start_date' => now()->toDateString(), 'pay_now' => true, 'amount' => 400])
            ->assertStatus(422)->assertJsonValidationErrors(['method'], 'errors');

        $api->postJson("/api/v1/admin/members/{$member->id}/memberships", [
            'plan_id' => $this->monthly->id, 'start_date' => now()->toDateString(), 'discount' => 100,
            'pay_now' => true, 'amount' => 400, 'method' => 'upi', 'reference' => 'UTR123',
        ])->assertCreated()
            ->assertJsonPath('data.membership.total_amount', 900)
            ->assertJsonPath('data.membership.balance', 500)
            ->assertJsonPath('data.membership.payment_status', 'partial')
            ->assertJsonPath('data.payment.amount', 400)
            ->assertJsonPath('data.payment.method', 'upi')
            ->assertJsonPath('data.payment.collected_by.id', $this->owner->id)
            ->assertJsonPath('data.fees.totals', ['billed' => 900, 'paid' => 400, 'due' => 500, 'discount' => 100, 'memberships' => 1])
            ->assertJsonPath('data.fees.payment_status', 'partial');

        // Renewing starts the day after the current membership ends.
        $api->postJson("/api/v1/admin/members/{$member->id}/memberships", ['plan_id' => $this->quarterly->id, 'mode' => 'renew', 'pay_now' => true, 'amount' => 0])
            ->assertCreated()
            ->assertJsonPath('data.membership.start_date', now()->addMonthNoOverflow()->toDateString())
            ->assertJsonPath('data.membership.status', 'upcoming')
            ->assertJsonPath('data.payment', null)
            ->assertJsonPath('data.fees.totals.due', 3200);

        $api->getJson("/api/v1/admin/members/{$member->id}/fees")->assertOk()
            ->assertJsonCount(2, 'data.memberships')
            ->assertJsonPath('data.memberships.0.plan_name', 'Quarterly')
            ->assertJsonPath('data.memberships.1.payments.0.reference', 'UTR123')
            ->assertJsonPath('data.can_collect', true);

        // Overlapping dates are refused.
        $api->postJson("/api/v1/admin/members/{$member->id}/memberships", ['plan_id' => $this->monthly->id, 'start_date' => now()->toDateString()])
            ->assertStatus(422)->assertJsonValidationErrors(['start_date'], 'errors');
    }

    public function test_marking_fees_as_received(): void
    {
        $member = $this->createMember($this->gym);
        $membership = $this->sell($member, $this->monthly);
        $api = $this->asStaff($this->owner);

        $api->postJson('/api/v1/admin/payments', ['membership_id' => $membership->id, 'amount' => 1500, 'method' => 'cash'])
            ->assertStatus(422)->assertJsonValidationErrors(['amount'], 'errors');
        $api->postJson('/api/v1/admin/payments', ['membership_id' => $membership->id, 'amount' => 100, 'method' => 'cash', 'paid_at' => now()->addDay()->toDateTimeString()])
            ->assertStatus(422)->assertJsonValidationErrors(['paid_at'], 'errors');

        $key = (string) Str::uuid();
        $first = $api->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/admin/payments', ['membership_id' => $membership->id, 'amount' => 600, 'method' => 'card'])
            ->assertCreated()->assertJsonPath('data.fees.totals.due', 400)->json('data.payment.receipt_no');
        $api->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/admin/payments', ['membership_id' => $membership->id, 'amount' => 600, 'method' => 'card'])
            ->assertCreated()->assertHeader('Idempotent-Replayed', 'true')->assertJsonPath('data.payment.receipt_no', $first);
        $this->assertSame(1, Payment::withoutGlobalScopes()->count());

        $api->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/v1/admin/payments', ['membership_id' => $membership->id, 'amount' => 400, 'method' => 'cash'])
            ->assertCreated()->assertJsonPath('data.fees.payment_status', 'paid')->assertJsonPath('data.payment.membership.balance', 0);
        $api->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/v1/admin/payments', ['membership_id' => $membership->id, 'amount' => 1, 'method' => 'cash'])
            ->assertStatus(409)->assertJsonPath('code', 'ALREADY_PAID');
    }

    public function test_cancelling_a_membership(): void
    {
        $member = $this->createMember($this->gym);
        $paid = $this->sell($member, $this->monthly, 500);
        $unpaid = $this->sell($member, $this->monthly, 0, now()->addMonths(2)->toDateString());
        $api = $this->asStaff($this->owner);

        $api->postJson("/api/v1/admin/memberships/{$paid->id}/cancel")->assertStatus(409)->assertJsonPath('code', 'MEMBERSHIP_HAS_PAYMENTS');
        $api->postJson("/api/v1/admin/memberships/{$unpaid->id}/cancel", ['reason' => 'Changed mind'])
            ->assertOk()
            ->assertJsonPath('data.membership.status', 'cancelled')
            ->assertJsonPath('data.membership.can_cancel', false)
            ->assertJsonPath('data.fees.totals.due', 500);
        $api->postJson("/api/v1/admin/memberships/{$unpaid->id}/cancel")->assertStatus(409)->assertJsonPath('code', 'ALREADY_CANCELLED');
        $api->postJson('/api/v1/admin/payments', ['membership_id' => $unpaid->id, 'amount' => 10, 'method' => 'cash'])
            ->assertStatus(409)->assertJsonPath('code', 'MEMBERSHIP_CANCELLED');
    }

    public function test_membership_lists_and_pending_fees_by_membership_or_member(): void
    {
        $asha = $this->createMember($this->gym, ['name' => 'Asha']);
        $ravi = $this->createMember($this->gym, ['name' => 'Ravi']);
        $this->sell($asha, $this->monthly, 1000, now()->subMonths(3)->toDateString());
        $this->sell($asha, $this->quarterly, 700);
        $this->sell($ravi, $this->monthly, 200);
        $api = $this->asStaff($this->owner);

        $api->getJson('/api/v1/admin/memberships?filter=active')->assertOk()->assertJsonCount(2, 'data.memberships');
        $api->getJson('/api/v1/admin/memberships?filter=dues')->assertOk()
            ->assertJsonCount(2, 'data.memberships')
            ->assertJsonPath('data.memberships.0.member.name', 'Asha')
            ->assertJsonPath('data.memberships.0.balance', 2000);
        $api->getJson('/api/v1/admin/memberships?filter=all&search=Ravi')->assertOk()->assertJsonCount(1, 'data.memberships');

        $api->getJson('/api/v1/admin/pending-fees')->assertOk()
            ->assertJsonPath('data.totals', ['due' => 2800, 'memberships' => 2, 'members' => 2])
            ->assertJsonPath('data.rows.0.balance', 2000);
        $api->getJson('/api/v1/admin/pending-fees?group=member')->assertOk()
            ->assertJsonCount(2, 'data.rows')
            ->assertJsonPath('data.rows.0.member.name', 'Asha')
            ->assertJsonPath('data.rows.0.due', 2000)
            ->assertJsonPath('data.rows.1.due', 800)
            ->assertJsonPath('data.pagination.total', 2);
    }

    public function test_payments_list_totals_by_method_and_date_range(): void
    {
        $member = $this->createMember($this->gym, ['name' => 'Asha']);
        $membership = $this->sell($member, $this->quarterly);
        $this->inGym($this->gym, function () use ($membership) {
            app(PaymentService::class)->record($membership, 500, 'cash', null, now()->subMonths(2)->toDateTimeString());
            app(PaymentService::class)->record($membership->fresh(), 300, 'upi', 'UTR9');
            app(PaymentService::class)->record($membership->fresh(), 200, 'cash');
        });
        $api = $this->asStaff($this->owner);

        $api->getJson('/api/v1/admin/payments')->assertOk()
            ->assertJsonPath('data.totals.amount', 500)
            ->assertJsonPath('data.totals.count', 2)
            ->assertJsonPath('data.totals.by_method.upi', ['amount' => 300, 'count' => 1])
            ->assertJsonPath('data.totals.by_method.card', ['amount' => 0, 'count' => 0])
            ->assertJsonCount(2, 'data.payments');

        $from = now()->subMonths(3)->toDateString();
        $api->getJson("/api/v1/admin/payments?from={$from}&method=cash")->assertOk()->assertJsonPath('data.totals.amount', 700);
        $api->getJson("/api/v1/admin/payments?from={$from}&member_id={$member->id}&search=Asha")->assertOk()->assertJsonPath('data.totals.count', 3);
    }

    public function test_receipt_and_invoice_links_are_signed_and_expire(): void
    {
        $member = $this->createMember($this->gym);
        $membership = $this->sell($member, $this->monthly, 1000);
        $payment = Payment::withoutGlobalScopes()->firstOrFail();
        $api = $this->asStaff($this->owner);

        $open = $api->getJson("/api/v1/admin/payments/{$payment->id}/receipt")->assertOk()->assertJsonPath('data.shareable', false)->json('data');
        $this->assertTrue(Carbon::parse($open['expires_at'])->between(now()->addMinutes(9), now()->addMinutes(11)));
        $share = $api->getJson("/api/v1/admin/payments/{$payment->id}/receipt?share=1")->assertOk()->json('data');
        $this->assertTrue(Carbon::parse($share['expires_at'])->isAfter(now()->addDays(6)));

        $this->app['auth']->forgetGuards();
        $this->withoutToken()->get($share['url'])->assertOk()->assertSee($payment->receipt_number);
        $this->get($share['url'].'x')->assertForbidden();

        $invoice = $this->asStaff($this->owner)->getJson("/api/v1/admin/memberships/{$membership->id}/invoice")->assertOk()->json('data.url');
        $this->app['auth']->forgetGuards();
        $this->withoutToken()->get($invoice)->assertOk()->assertSee('Monthly');

        $this->travel(11)->minutes();
        $this->get($open['url'])->assertForbidden();
        $this->get($share['url'])->assertOk();

        $other = $this->gymOf($this->createGymOwner('Other'));
        $foreign = $this->inGym($other, fn () => app(MembershipService::class)->assign($this->createMember($other), $this->createPlan($other), now()->toDateString(), 0, null, ['amount' => 100, 'method' => 'cash']));
        $this->asStaff($this->owner)->getJson('/api/v1/admin/payments/'.$foreign->payments()->withoutGlobalScopes()->first()->id.'/receipt')->assertNotFound();
        $this->asStaff($this->owner)->getJson("/api/v1/admin/memberships/{$foreign->id}/invoice")->assertNotFound();
    }

    public function test_trainers_only_handle_fees_they_are_allowed_to(): void
    {
        $trainer = $this->trainerWith(['members.view', 'members.manage', 'memberships.manage']);
        $mine = $this->createMember($this->gym);
        $mine->forceFill(['trainer_id' => $trainer->id])->save();
        $notMine = $this->createMember($this->gym);
        $this->sell($notMine, $this->monthly);
        $api = $this->asStaff($trainer);

        $api->postJson("/api/v1/admin/members/{$mine->id}/memberships", ['plan_id' => $this->monthly->id, 'start_date' => now()->toDateString(), 'pay_now' => true, 'amount' => 100, 'method' => 'cash'])
            ->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
        $api->postJson("/api/v1/admin/members/{$mine->id}/memberships", ['plan_id' => $this->monthly->id, 'start_date' => now()->toDateString()])
            ->assertCreated()->assertJsonPath('data.fees.can_collect', false);

        $api->postJson("/api/v1/admin/members/{$notMine->id}/memberships", ['plan_id' => $this->monthly->id, 'start_date' => now()->addMonths(2)->toDateString()])->assertNotFound();
        $api->getJson('/api/v1/admin/pending-fees')->assertOk()->assertJsonPath('data.totals.members', 1);
        $api->getJson('/api/v1/admin/payments')->assertForbidden();
        $api->getJson('/api/v1/admin/reports/overview')->assertForbidden();
        $api->postJson('/api/v1/admin/payments', ['membership_id' => 1, 'amount' => 1, 'method' => 'cash'])->assertForbidden();

        $viewer = $this->trainerWith(['members.view']);
        $this->asStaff($viewer)->getJson("/api/v1/admin/members/{$mine->id}/fees")->assertForbidden();
    }

    public function test_overview_report_for_a_date_range(): void
    {
        Carbon::setTestNow('2026-11-20 12:00:00');
        $asha = $this->createMember($this->gym, ['name' => 'Asha', 'joining_date' => '2026-11-02']);
        $ravi = $this->createMember($this->gym, ['name' => 'Ravi', 'joining_date' => '2026-11-10']);
        $old = $this->createMember($this->gym, ['name' => 'Old', 'joining_date' => '2026-10-05']);

        // October: one sale fully paid (previous period).
        Carbon::setTestNow('2026-10-05 10:00:00');
        $this->sell($old, $this->monthly, 1000, '2026-10-05');

        // November: two monthly sales and one quarterly, partly paid.
        Carbon::setTestNow('2026-11-02 10:00:00');
        $this->sell($asha, $this->monthly, 1000, '2026-11-02', 'upi');
        Carbon::setTestNow('2026-11-10 10:00:00');
        $this->sell($ravi, $this->quarterly, 1500, '2026-11-10');
        Carbon::setTestNow('2026-11-12 10:00:00');
        $this->sell($old, $this->monthly, 0, '2026-11-12');
        Carbon::setTestNow('2026-11-20 12:00:00');

        $api = $this->asStaff($this->owner);
        $report = $api->getJson('/api/v1/admin/reports/overview?from=2026-11-01&to=2026-11-20')->assertOk();

        $report->assertJsonPath('data.range', ['from' => '2026-11-01', 'to' => '2026-11-20', 'days' => 20])
            ->assertJsonPath('data.collection.total', 2500)
            ->assertJsonPath('data.collection.payments', 2)
            ->assertJsonPath('data.collection.paying_members', 2)
            ->assertJsonPath('data.collection.by_method.upi.amount', 1000)
            ->assertJsonPath('data.sales.memberships_sold', 3)
            ->assertJsonPath('data.sales.billed', 4700)
            ->assertJsonPath('data.sales.paid', 2500)
            ->assertJsonPath('data.sales.pending', 2200)
            ->assertJsonPath('data.dues.total_due', 2200)
            ->assertJsonPath('data.dues.members', 2)
            ->assertJsonPath('data.members.new', 2)
            ->assertJsonPath('data.members.total', 3)
            ->assertJsonPath('data.plans.most_selling.plan_name', 'Monthly')
            ->assertJsonPath('data.plans.most_selling.sold', 2)
            ->assertJsonPath('data.plans.top_earning.plan_name', 'Quarterly')
            // Recorded here without a signed-in staff user (from the app it is the staff member).
            ->assertJsonPath('data.collected_by.0.user', null)
            ->assertJsonPath('data.collected_by.0.amount', 2500)
            ->assertJsonPath('data.previous_period.from', '2026-10-12')
            ->assertJsonPath('data.series.interval', 'day')
            ->assertJsonCount(20, 'data.series.points')
            ->assertJsonPath('data.series.points.9', ['key' => '2026-11-10', 'label' => '10 Nov', 'collected' => 1500, 'memberships_sold' => 1, 'new_members' => 1]);

        $api->getJson('/api/v1/admin/reports/overview?from=2026-01-01&to=2026-11-20')->assertOk()
            ->assertJsonPath('data.series.interval', 'month')
            ->assertJsonCount(11, 'data.series.points')
            ->assertJsonPath('data.series.points.9.collected', 1000)
            ->assertJsonPath('data.collection.total', 3500);

        $api->getJson('/api/v1/admin/reports/overview?from=2024-01-01&to=2026-11-20')->assertStatus(422)->assertJsonPath('code', 'RANGE_TOO_LONG');
        $api->getJson('/api/v1/admin/reports/overview?from=2026-11-20&to=2026-11-01')->assertStatus(422);
    }
}
