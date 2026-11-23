<?php

namespace Tests\Feature;

use App\Livewire\Platform\Gyms\Show;
use App\Models\Gym;
use App\Models\GymPricingOverride;
use App\Models\ImpersonationToken;
use App\Models\User;
use App\Services\BillingService;
use App\Services\ImpersonationService;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class SuperAdminPowersTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-15 10:00'));
        $this->admin = User::factory()->create(['name' => 'Platform Boss']);
        $this->admin->assignRole('super_admin');
        $this->gym = $this->gymOf($this->createGymOwner('Premium Gym'));
    }

    /** Extract the plain token from a generated magic-link URL. */
    protected function tokenFrom(string $url): string
    {
        return basename(parse_url($url, PHP_URL_PATH));
    }

    // ---- Custom pricing ----------------------------------------------------------

    public function test_custom_price_applies_to_that_gym_only_and_invoices_snapshot_it(): void
    {
        $standardGym = $this->gymOf($this->createGymOwner('Standard Gym'));
        foreach ([$this->gym, $standardGym] as $gym) {
            $plan = $this->createPlan($gym);
            $this->inGym($gym, function () use ($gym, $plan) {
                foreach (range(1, 3) as $i) {
                    app(MembershipService::class)->assign($this->createMember($gym), $plan, '2026-12-01');
                }
            });
        }

        $this->actingAs($this->admin);
        Livewire::test(Show::class, ['gym' => $this->gym])
            ->set('tab', 'pricing')
            ->assertSet('customFrom', '2026-12-01')
            ->set('customPrice', '40')
            ->set('customNotes', 'Premium package')
            ->call('setCustomPrice')
            ->assertHasNoErrors()
            ->assertSee('Scheduled');

        $billing = app(BillingService::class);
        $this->assertFalse($billing->rateForGym($this->gym)['custom'], 'not yet in effect in November');
        $this->assertSame(40.0, $billing->rateForGym($this->gym, '2026-12-01')['rate']);
        $this->assertSame(20.0, $billing->rateForGym($standardGym, '2026-12-01')['rate']);

        $this->travelTo(Carbon::parse('2027-01-05'));
        $custom = $billing->generateInvoice($this->gym, Carbon::parse('2026-12-01'));
        $standard = $billing->generateInvoice($standardGym, Carbon::parse('2026-12-01'));

        $this->assertEquals(40.0, (float) $custom->rate_per_member);
        $this->assertEquals(120.0, (float) $custom->total);
        $this->assertSame('custom', $custom->meta['pricing']);
        $this->assertEquals(20.0, (float) $standard->rate_per_member);
        $this->assertSame('standard', $standard->meta['pricing']);

        // Changing the custom price later never alters the issued invoice.
        $billing->setCustomPrice($this->gym, 50, '2027-01-01');
        $this->assertEquals(40.0, (float) $custom->fresh()->rate_per_member);
    }

    public function test_new_custom_price_closes_previous_and_gym_can_return_to_standard(): void
    {
        $billing = app(BillingService::class);
        $billing->setCustomPrice($this->gym, 30, '2026-11-01');
        $billing->setCustomPrice($this->gym, 35, '2027-01-01');

        $first = GymPricingOverride::where('price_per_member', 30)->sole();
        $this->assertSame('2026-12-31', $first->ends_on->toDateString());
        $this->assertSame(30.0, $billing->rateForGym($this->gym, '2026-12-15')['rate']);
        $this->assertSame(35.0, $billing->rateForGym($this->gym, '2027-02-01')['rate']);

        $billing->endCustomPrice($this->gym, '2027-03-01');
        $this->assertSame(35.0, $billing->rateForGym($this->gym, '2027-02-01')['rate']);
        $this->assertSame(20.0, $billing->rateForGym($this->gym, '2027-03-01')['rate']);
        $this->assertFalse($billing->rateForGym($this->gym, '2027-03-01')['custom']);
        $this->assertSame(2, GymPricingOverride::count(), 'history kept');

        // Cannot backdate before the current month.
        $this->expectException(ValidationException::class);
        $billing->setCustomPrice($this->gym, 10, '2026-10-01');
    }

    public function test_custom_pricing_is_visible_on_pricing_and_gym_billing_pages(): void
    {
        app(BillingService::class)->setCustomPrice($this->gym, 45, '2026-11-01');

        $this->actingAs($this->admin)->get(route('platform.pricing'))->assertOk()->assertSee('Premium Gym')->assertSee('₹45.00');
        $this->actingAs($this->gym->owner)->get(route('gym.billing'))->assertOk()->assertSee('your custom price');
    }

    // ---- Magic links -----------------------------------------------------------

    public function test_super_admin_logs_in_as_gym_owner_and_returns(): void
    {
        $owner = $this->gym->owner;
        $this->actingAs($this->admin);

        $component = Livewire::test(Show::class, ['gym' => $this->gym])
            ->set('tab', 'access')
            ->assertSee($owner->email)
            ->call('magicLink', $owner->id)
            ->assertSet('showLink', true);

        $url = $component->get('linkUrl');
        $token = ImpersonationToken::sole();
        $this->assertSame(hash('sha256', $this->tokenFrom($url)), $token->token_hash, 'only the hash is stored');
        $this->assertStringNotContainsString($this->tokenFrom($url), json_encode($token->getAttributes()));

        $this->get($url)->assertRedirect(route('gym.dashboard'));
        $this->assertAuthenticatedAs($owner);
        $this->get(route('gym.dashboard'))->assertOk()->assertSee('Return to Super Admin')->assertSee('via Super Admin magic link');
        $this->assertNull($owner->fresh()->last_login_at, 'impersonation is not a real login');

        $token->refresh();
        $this->assertNotNull($token->used_at);
        $this->assertSame('127.0.0.1', $token->used_ip);

        $this->post(route('impersonate.leave'))->assertRedirect(route('platform.gyms.show', $this->gym->id));
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_links_are_single_use_and_expire(): void
    {
        $impersonation = app(ImpersonationService::class);

        $url = $impersonation->createLink($this->gym->owner, $this->admin)['url'];
        $this->get($url)->assertRedirect();
        $this->post(route('impersonate.leave'));
        $this->get($url)->assertForbidden();

        $expired = $impersonation->createLink($this->gym->owner, $this->admin)['url'];
        $this->travel(ImpersonationService::LINK_MINUTES + 1)->minutes();
        $this->get($expired)->assertForbidden();

        $this->get('/login-as/'.str_repeat('a', 64))->assertForbidden();
    }

    public function test_link_opened_in_another_browser_cannot_reach_super_admin(): void
    {
        $member = $this->createMember($this->gym, ['email' => 'm@example.test'], ['portal_password' => 'password']);
        $url = app(ImpersonationService::class)->createLink($member->user, $this->admin)['url'];

        // Fresh, logged-out browser (e.g. incognito, or a leaked link).
        $this->get($url)->assertRedirect(route('portal.dashboard'));
        $this->assertAuthenticatedAs($member->user);
        $this->get(route('portal.dashboard'))->assertSee('End session')->assertDontSee('Return to Super Admin');

        $this->post(route('impersonate.leave'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_magic_link_restrictions(): void
    {
        $impersonation = app(ImpersonationService::class);
        $otherAdmin = User::factory()->create();
        $otherAdmin->assignRole('super_admin');

        // Never for super admins.
        try {
            $impersonation->createLink($otherAdmin, $this->admin);
            $this->fail('Super admin accounts must not be impersonated.');
        } catch (ValidationException) {
            $this->assertSame(0, ImpersonationToken::count());
        }

        // Not for inactive accounts.
        $trainer = $this->createTrainer($this->gym);
        $trainer->update(['status' => 'inactive']);
        $this->expectException(ValidationException::class);
        $impersonation->createLink($trainer, $this->admin);
    }

    public function test_only_this_gyms_accounts_and_only_super_admins_can_generate(): void
    {
        $otherGym = $this->gymOf($this->createGymOwner('Other Gym'));
        $this->actingAs($this->admin);

        Livewire::test(Show::class, ['gym' => $this->gym])->call('magicLink', $otherGym->owner_id)->assertNotFound();

        $this->actingAs($this->gym->owner);
        $this->get(route('platform.gyms.show', $this->gym->id))->assertForbidden();
        $this->assertSame(0, ImpersonationToken::count());
    }

    public function test_trainer_and_member_magic_links_land_on_their_dashboards(): void
    {
        $trainer = $this->createTrainer($this->gym);
        $this->actingAs($this->admin);
        $this->get(app(ImpersonationService::class)->createLink($trainer, $this->admin)['url'])->assertRedirect(route('gym.dashboard'));
        $this->assertAuthenticatedAs($trainer);
        $this->get(route('gym.dashboard'))->assertOk()->assertSee('assigned members');
    }
}
