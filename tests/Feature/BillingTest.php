<?php

namespace Tests\Feature;

use App\Models\Enquiry;
use App\Models\PricingRate;
use App\Models\SaasInvoice;
use App\Services\BillingService;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_counts_only_active_members_with_valid_memberships(): void
    {
        $this->travelTo(Carbon::parse('2026-03-15'));

        $owner = $this->createGymOwner();
        $gym = $this->gymOf($owner);
        $plan = $this->createPlan($gym);
        $memberships = app(MembershipService::class);

        $this->inGym($gym, function () use ($gym, $plan, $memberships) {
            // Billable: valid during February.
            $memberships->assign($this->createMember($gym), $plan, '2026-02-01');
            $memberships->assign($this->createMember($gym), $plan, '2026-01-20'); // ends 19 Feb — overlaps
            // Not billable: expired before February.
            $memberships->assign($this->createMember($gym), $plan, '2025-11-01');
            // Not billable: inactive member.
            $memberships->assign($this->createMember($gym, ['status' => 'inactive']), $plan, '2026-02-01');
            // Not billable: member without any membership, and an enquiry.
            $this->createMember($gym);
            Enquiry::create(['name' => 'Lead', 'phone' => '9000011111']);
        });

        $invoice = app(BillingService::class)->generateInvoice($gym, Carbon::parse('2026-02-01'));

        $this->assertSame(2, $invoice->active_members);
        $this->assertEquals(20.0, (float) $invoice->rate_per_member);
        $this->assertEquals(40.0, (float) $invoice->total);
        $this->assertSame('unpaid', $invoice->status->value);
    }

    public function test_price_changes_do_not_alter_existing_invoices(): void
    {
        $this->travelTo(Carbon::parse('2026-03-15'));
        $gym = $this->gymOf($this->createGymOwner());
        $plan = $this->createPlan($gym);
        $this->inGym($gym, fn () => app(MembershipService::class)->assign($this->createMember($gym), $plan, '2026-01-25'));

        $billing = app(BillingService::class);
        $feb = $billing->generateInvoice($gym, Carbon::parse('2026-02-01'));

        PricingRate::create(['price_per_member' => 30, 'effective_from' => '2026-03-01']);

        $this->assertEquals(20.0, (float) $feb->fresh()->rate_per_member);
        $this->assertEquals(20.0, (float) $feb->fresh()->total);
        // Regenerating February returns the same snapshot instead of a new invoice.
        $this->assertSame($feb->id, $billing->generateInvoice($gym, Carbon::parse('2026-02-01'))->id);
        $this->assertEquals(30.0, PricingRate::rateOn('2026-03-01'));
    }

    public function test_payments_update_outstanding_balance(): void
    {
        $this->travelTo(Carbon::parse('2026-03-15'));
        $gym = $this->gymOf($this->createGymOwner());
        $plan = $this->createPlan($gym);
        $this->inGym($gym, function () use ($gym, $plan) {
            foreach (range(1, 5) as $i) {
                app(MembershipService::class)->assign($this->createMember($gym), $plan, '2026-02-01');
            }
        });

        $billing = app(BillingService::class);
        $invoice = $billing->generateInvoice($gym, Carbon::parse('2026-02-01'));
        $this->assertEquals(100.0, $billing->outstandingFor($gym));

        $billing->recordPayment($invoice, 60, 'upi');
        $this->assertSame('partial', $invoice->fresh()->status->value);
        $this->assertEquals(40.0, $billing->outstandingFor($gym));

        $billing->recordPayment($invoice, 40, 'bank_transfer');
        $this->assertSame('paid', $invoice->fresh()->status->value);
        $this->assertEquals(0.0, $billing->outstandingFor($gym));
    }

    public function test_generate_invoices_command_runs_for_all_gyms(): void
    {
        $this->createGymOwner('A');
        $this->createGymOwner('B');
        $this->travelTo(now()->addMonthNoOverflow());

        $this->artisan('gymai:generate-invoices')->assertSuccessful();
        $this->assertSame(2, SaasInvoice::count());

        $this->artisan('gymai:generate-invoices')->assertSuccessful();
        $this->assertSame(2, SaasInvoice::count());
    }
}
