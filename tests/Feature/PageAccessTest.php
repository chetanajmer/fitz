<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BillingService;
use App\Services\EnquiryService;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Smoke test: every page renders for the right role and is blocked for the wrong ones. */
class PageAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_render(): void
    {
        $gym = $this->gymOf($this->createGymOwner('Public Gym'));
        $this->createPlan($gym, ['name' => 'Gold Plan']);

        $this->get('/')->assertOk()->assertSee('₹20');
        $this->get(route('login'))->assertOk();
        $this->get(route('register.gym'))->assertOk();
        $this->get(route('gym.public', $gym->slug))->assertOk()->assertSee('Public Gym')->assertSee('Gold Plan');
        $this->get(route('gym.join', $gym->join_token))->assertOk();
        $this->get('/no-such-gym')->assertNotFound();
    }

    public function test_gym_admin_pages_render(): void
    {
        $owner = $this->createGymOwner();
        $gym = $this->gymOf($owner);
        $trainer = $this->createTrainer($gym);
        $member = $this->createMember($gym, [], ['trainer_id' => $trainer->id]);
        $this->inGym($gym, fn () => app(MembershipService::class)->assign($member, $this->createPlan($gym), null, 0, null, ['amount' => 100, 'method' => 'cash']));
        $enquiry = $this->inGym($gym, fn () => app(EnquiryService::class)->create(['name' => 'Lead', 'phone' => '9000000011']));

        $this->actingAs($owner);

        foreach ([
            route('gym.dashboard'), route('gym.members.index'), route('gym.members.create'), route('gym.members.import'),
            route('gym.members.show', $member), route('gym.members.show', [$member, 'tab' => 'memberships']),
            route('gym.members.show', [$member, 'tab' => 'payments']), route('gym.members.show', [$member, 'tab' => 'attendance']),
            route('gym.members.edit', $member), route('gym.members.qr', $member), route('gym.members.import.sample'),
            route('gym.enquiries.index'), route('gym.enquiries.show', $enquiry), route('gym.plans.index'),
            route('gym.memberships.index'), route('gym.memberships.index', ['filter' => 'expired']), route('gym.memberships.index', ['filter' => 'dues']),
            route('gym.payments.index'), route('gym.attendance.index'), route('gym.attendance.reports'),
            route('gym.attendance.reports', ['view' => 'monthly']), route('gym.trainers.index'), route('gym.trainers.show', $trainer->id),
            route('gym.settings'), route('gym.settings', ['tab' => 'hours']), route('gym.settings', ['tab' => 'photos']),
            route('gym.settings', ['tab' => 'qr']), route('gym.settings.join-qr'), route('gym.billing'), route('gym.account'),
        ] as $url) {
            $this->get($url)->assertOk();
        }

        $this->get(route('platform.dashboard'))->assertForbidden();
        $this->get(route('portal.dashboard'))->assertForbidden();
    }

    public function test_trainer_has_limited_access(): void
    {
        $gym = $this->gymOf($this->createGymOwner());
        $trainer = $this->createTrainer($gym);
        $this->actingAs($trainer);

        $this->get(route('gym.dashboard'))->assertOk()->assertSee('assigned members');
        $this->get(route('gym.members.index'))->assertOk();
        $this->get(route('gym.attendance.index'))->assertOk();

        foreach (['gym.members.create', 'gym.enquiries.index', 'gym.plans.index', 'gym.payments.index', 'gym.trainers.index', 'gym.settings', 'gym.billing', 'gym.memberships.index'] as $name) {
            $this->get(route($name))->assertForbidden();
        }
    }

    public function test_member_portal_renders(): void
    {
        $gym = $this->gymOf($this->createGymOwner());
        $member = $this->createMember($gym, ['email' => 'm@example.test'], ['portal_password' => 'password']);
        $this->inGym($gym, fn () => app(MembershipService::class)->assign($member, $this->createPlan($gym), null, 0, null, ['amount' => 500, 'method' => 'upi']));
        $payment = $this->inGym($gym, fn () => $member->payments()->first());

        $this->actingAs($member->user);

        $this->get(route('portal.dashboard'))->assertOk()->assertSee($member->member_code);
        $this->get(route('portal.qr'))->assertOk();
        $this->get(route('portal.receipt', $payment))->assertOk();
        $this->get(route('portal.account'))->assertOk();
        $this->get(route('gym.dashboard'))->assertForbidden();
    }

    public function test_super_admin_pages_render(): void
    {
        $owner = $this->createGymOwner('Platform Gym');
        $gym = $this->gymOf($owner);
        $this->travelTo(now()->addMonth());
        $invoice = app(BillingService::class)->generateInvoice($gym, now()->subMonthNoOverflow());

        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        foreach ([
            route('platform.dashboard'), route('platform.gyms.index'), route('platform.gyms.show', $gym->id),
            route('platform.gyms.show', [$gym->id, 'tab' => 'invoices']), route('platform.pricing'), route('platform.invoices'),
            route('platform.invoices', ['tab' => 'payments']), route('platform.invoices.show', $invoice), route('platform.settings'), route('platform.account'),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_suspended_gym_users_are_redirected(): void
    {
        $owner = $this->createGymOwner();
        $this->gymOf($owner)->forceFill(['status' => 'suspended', 'is_active' => false, 'status_reason' => 'Unpaid'])->save();

        $this->actingAs($owner)->get(route('gym.dashboard'))->assertRedirect(route('gym.suspended'));
        $this->get(route('gym.suspended'))->assertOk()->assertSee('Unpaid');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('gym.dashboard'))->assertRedirect(route('login'));
        $this->get(route('platform.dashboard'))->assertRedirect(route('login'));
    }
}
