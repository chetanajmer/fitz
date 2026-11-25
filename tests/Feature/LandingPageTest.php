<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\PricingRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_renders_every_section_with_seo_meta(): void
    {
        $response = $this->get('/')->assertOk();

        foreach (['home', 'features', 'stats', 'members', 'ai-coach', 'how-it-works', 'discover', 'pricing', 'about', 'faq', 'contact'] as $anchor) {
            $response->assertSee('id="'.$anchor.'"', false);
        }

        $response->assertSee('Lift your')
            ->assertSee('14 modules.')
            ->assertSee('Your members get')
            ->assertSee('Progress report')
            ->assertSee('Get found.')
            ->assertSee('data-lp-period="12"', false)
            ->assertSee('<meta name="description"', false)
            ->assertSee('rel="canonical"', false);
    }

    public function test_every_call_to_action_links_to_its_page(): void
    {
        $this->get('/')->assertOk()
            ->assertSee(route('register.gym'), false)
            ->assertSee(route('member.join'), false)
            ->assertSee(route('gyms.browse'), false)
            ->assertSee(route('login'), false)
            ->assertSee('Register Your Gym')
            ->assertSee('Register as Member')
            ->assertSee('Browse gyms');
    }

    public function test_pricing_uses_the_live_per_member_rate(): void
    {
        PricingRate::forceCreate(['price_per_member' => 35, 'effective_from' => now()->subDay()->toDateString()]);

        $this->get('/')->assertOk()
            ->assertSee('₹35')
            ->assertSee('data-rate="35"', false)
            ->assertSee(route('register.gym'), false);
    }

    public function test_ctas_fall_back_to_contact_when_registrations_are_closed(): void
    {
        PlatformSetting::put(['allow_registrations' => '0']);

        $this->get('/')->assertOk()
            ->assertSee('Contact Sales')
            ->assertSee('New registrations are currently paused')
            ->assertDontSee(route('register.gym'), false);
    }
}
