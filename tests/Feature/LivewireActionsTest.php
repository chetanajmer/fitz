<?php

namespace Tests\Feature;

use App\Livewire\Gym\Attendance\Index as AttendanceIndex;
use App\Livewire\Gym\Enquiries\Show as EnquiryShow;
use App\Livewire\Gym\Members\Form as MemberForm;
use App\Livewire\Gym\Members\Show as MemberShow;
use App\Livewire\Gym\Plans\Index as PlansIndex;
use App\Livewire\Gym\Trainers\Index as TrainersIndex;
use App\Livewire\Platform\Gyms\Show as PlatformGymShow;
use App\Livewire\Platform\Pricing;
use App\Models\Attendance;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\PricingRate;
use App\Models\User;
use App\Services\EnquiryService;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LivewireActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_plan_member_assigns_and_collects_payment(): void
    {
        $owner = $this->createGymOwner();
        $this->actingAs($owner);

        Livewire::test(PlansIndex::class)
            ->call('create')
            ->set('form.name', 'Quarterly Gold')
            ->set('form.duration_type', 'quarterly')
            ->set('form.price', '4500')
            ->call('save')
            ->assertHasNoErrors();
        $plan = MembershipPlan::sole();

        Livewire::test(MemberForm::class)
            ->set('form.name', 'Priya Nair')
            ->set('form.phone', '9811122233')
            ->set('form.email', 'priya@example.test')
            ->set('portal_login', true)
            ->set('portal_password', 'member-pass')
            ->call('save')
            ->assertHasNoErrors();
        $member = Member::sole();
        $this->assertSame('M0001', $member->member_code);
        $this->assertTrue($member->user->isMember());

        Livewire::test(MemberShow::class, ['member' => $member])
            ->call('openPlanModal', 'assign')
            ->set('planForm.plan_id', (string) $plan->id)
            ->assertSet('planForm.amount', '4500')
            ->set('planForm.amount', '2000')
            ->call('savePlan')
            ->assertHasNoErrors()
            ->call('openPaymentModal')
            ->assertSet('paymentForm.amount', '2500')
            ->call('savePayment')
            ->assertHasNoErrors()
            ->call('checkIn')
            ->assertHasNoErrors();

        $this->assertEquals(4500.0, (float) Payment::sum('amount'));
        $this->assertEquals(0.0, $member->pendingAmount());
        $this->assertSame(1, Attendance::count());
    }

    public function test_duplicate_phone_is_rejected_within_a_gym_only(): void
    {
        $ownerA = $this->createGymOwner('A');
        $ownerB = $this->createGymOwner('B');
        $this->createMember($this->gymOf($ownerB), ['phone' => '9000000001']);
        $this->createMember($this->gymOf($ownerA), ['phone' => '9000000002']);

        $this->actingAs($ownerA);

        Livewire::test(MemberForm::class)->set('form.name', 'X')->set('form.phone', '9000000001')->call('save')->assertHasNoErrors();
        Livewire::test(MemberForm::class)->set('form.name', 'Y')->set('form.phone', '9000000002')->call('save')->assertHasErrors(['form.phone']);
    }

    public function test_enquiry_conversion_from_component(): void
    {
        $owner = $this->createGymOwner();
        $this->actingAs($owner);
        $plan = $this->createPlan($this->gymOf($owner));
        $enquiry = app(EnquiryService::class)->create(['name' => 'Kunal', 'phone' => '9888877777', 'interested_plan_id' => $plan->id]);

        Livewire::test(EnquiryShow::class, ['enquiry' => $enquiry])
            ->set('newStatus', 'follow_up')
            ->set('note', 'Call back Monday')
            ->call('updateStatus')
            ->assertHasNoErrors()
            ->call('openConvert')
            ->assertSet('convert.plan_id', (string) $plan->id)
            ->call('convertToMember')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertSame('converted', $enquiry->fresh()->status->value);
        $this->assertSame(1, Member::count());
    }

    public function test_attendance_desk_and_trainer_management(): void
    {
        $owner = $this->createGymOwner();
        $gym = $this->gymOf($owner);
        $this->actingAs($owner);
        $member = $this->createMember($gym, ['name' => 'Desk Member']);
        app(MembershipService::class)->assign($member, $this->createPlan($gym));

        Livewire::test(AttendanceIndex::class)
            ->set('search', 'Desk')
            ->assertSee('Desk Member')
            ->call('checkIn', $member->id)
            ->assertHasNoErrors()
            ->call('checkIn', $member->id)
            ->assertHasErrors('member')
            ->set('scan', route('gym.attendance.scan', $member->qr_token))
            ->call('submitScan')
            ->assertHasNoErrors();

        $this->assertNotNull(Attendance::sole()->check_out_at);

        Livewire::test(TrainersIndex::class)
            ->call('create')
            ->set('form.name', 'Coach')
            ->set('form.email', 'coach@example.test')
            ->set('form.password', 'coach-pass')
            ->call('save')
            ->assertHasNoErrors();

        $trainer = User::where('email', 'coach@example.test')->sole();
        $this->assertTrue($trainer->isTrainer());
        $this->assertSame($gym->id, $trainer->gym_id);
    }

    public function test_super_admin_suspends_gym_and_sets_price(): void
    {
        $owner = $this->createGymOwner();
        $gym = $this->gymOf($owner);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        Livewire::test(PlatformGymShow::class, ['gym' => $gym])
            ->call('openStatus', 'suspended')
            ->call('changeStatus')
            ->assertHasErrors('reason')
            ->set('reason', 'Overdue invoices')
            ->call('changeStatus')
            ->assertHasNoErrors();

        $this->assertSame('suspended', $gym->fresh()->status->value);
        $this->assertFalse($gym->fresh()->is_active);

        Livewire::test(Pricing::class)
            ->set('price', '25')
            ->set('effectiveFrom', now()->addMonthNoOverflow()->startOfMonth()->toDateString())
            ->call('save')
            ->assertHasNoErrors()
            ->set('effectiveFrom', now()->subMonths(2)->toDateString())
            ->call('save')
            ->assertHasErrors('effectiveFrom');

        $this->assertEquals(25.0, PricingRate::rateOn(now()->addMonthNoOverflow()->startOfMonth()));
        $this->assertEquals(20.0, PricingRate::rateOn());
    }
}
