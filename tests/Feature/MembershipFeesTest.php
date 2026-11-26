<?php

namespace Tests\Feature;

use App\Livewire\Gym\Members\Show;
use App\Livewire\Gym\Settings\Profile;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MembershipFeesTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected Member $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gym = $this->gymOf($this->createGymOwner());
        $this->member = $this->createMember($this->gym);
        $this->createPlan($this->gym, ['price' => 1180]);
        $this->actingAs($this->gym->owner);
    }

    protected function saveFees(array $fees)
    {
        return Livewire::test(Profile::class)->set('tab', 'fees')->set('fees', $fees + ['joining_fee' => '0', 'gst_enabled' => false, 'gstin' => '', 'gst_rate' => '18'])->call('saveFees');
    }

    protected function assignFirst(bool $joiningFee): Membership
    {
        $plan = $this->inGym($this->gym, fn () => MembershipPlan::sole());
        $page = Livewire::test(Show::class, ['member' => $this->member])->call('openPlanModal', 'assign')
            ->assertSet('planForm.joining_fee', true)
            ->set('planForm.plan_id', (string) $plan->id)->set('planForm.joining_fee', $joiningFee)->set('planForm.pay_now', false)
            ->call('savePlan')->assertHasNoErrors();

        return Membership::withoutGlobalScopes()->latest('id')->first();
    }

    public function test_the_gym_sets_a_valid_gstin_and_joining_fee(): void
    {
        $this->saveFees(['gst_enabled' => true, 'gstin' => 'not-a-gstin'])->assertHasErrors('fees.gstin');
        $this->saveFees(['gst_enabled' => true, 'gstin' => ''])->assertHasErrors('fees.gstin');
        $this->saveFees(['joining_fee' => '500', 'gst_enabled' => true, 'gstin' => '08abcde1234f1z5', 'gst_rate' => '18'])->assertHasNoErrors();

        $this->assertSame(['joining_fee' => 500.0, 'gst_enabled' => true, 'gstin' => '08ABCDE1234F1Z5', 'gst_rate' => 18.0], $this->gym->fresh()->feeSettings());
    }

    public function test_the_first_membership_adds_the_joining_fee_and_gst_is_split_out(): void
    {
        $this->saveFees(['joining_fee' => '500', 'gst_enabled' => true, 'gstin' => '08ABCDE1234F1Z5']);
        $membership = $this->assignFirst(true);

        $this->assertSame([500.0, 1680.0, 18.0, '08ABCDE1234F1Z5'], [(float) $membership->joining_fee, (float) $membership->total_amount, (float) $membership->gst_rate, $membership->gstin]);
        $this->assertSame(['taxable' => 1423.73, 'cgst' => 128.14, 'sgst' => 128.13, 'rate' => 18.0], $membership->gstBreakup());

        $this->get(route('gym.memberships.invoice', $membership))->assertOk()
            ->assertSee('Tax invoice')->assertSee('GSTIN: 08ABCDE1234F1Z5')->assertSee('Joining fee')->assertSee('CGST 9%')->assertSee('Total (incl. GST)');

        // Renewals never add the joining fee.
        $renewal = $this->inGym($this->gym, fn () => app(MembershipService::class)->renew($this->member->fresh(), MembershipPlan::sole()));
        $this->assertSame([0.0, 1180.0], [(float) $renewal->joining_fee, (float) $renewal->total_amount]);
    }

    public function test_without_gst_nothing_changes_and_staff_can_skip_the_joining_fee(): void
    {
        $this->saveFees(['joining_fee' => '500']);
        $membership = $this->assignFirst(false);

        $this->assertSame([0.0, 1180.0, null], [(float) $membership->joining_fee, (float) $membership->total_amount, $membership->gst_rate]);
        $this->assertNull($membership->gstBreakup());
        $this->get(route('gym.memberships.invoice', $membership))->assertOk()->assertSee('Invoice')->assertDontSee('Tax invoice')->assertDontSee('GSTIN');
    }

    public function test_changing_gst_later_keeps_old_invoices(): void
    {
        $this->saveFees(['gst_enabled' => true, 'gstin' => '08ABCDE1234F1Z5']);
        $membership = $this->assignFirst(false);
        $this->saveFees([]);

        $this->assertSame(18.0, (float) $membership->fresh()->gst_rate);
    }
}
