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

        foreach (['id="home"', 'id="features"', 'id="how-it-works"', 'id="pricing"', 'id="about"', 'id="stats"', 'id="faq"', 'id="contact"'] as $anchor) {
            $response->assertSee($anchor, false);
        }

        $response->assertSee('Run Your Gym Smarter.')
            ->assertSee('id="members"', false)
            ->assertSee('AI Body Transformation')
            ->assertSee('Members register themselves with a QR code')
            ->assertSee('data-lp-period="12"', false)
            ->assertDontSee('id="testimonials"', false)
            ->assertSee('<meta name="description"', false)
            ->assertSee('rel="canonical"', false);
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
