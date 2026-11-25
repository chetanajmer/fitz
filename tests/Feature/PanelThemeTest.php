<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_panels_default_to_dark_and_show_the_toggle(): void
    {
        $this->actingAs($this->createGymOwner());

        $this->get(route('gym.dashboard'))->assertOk()
            ->assertSee('panel-theme bg-gray-50 theme-dark', false)
            ->assertSee('data-theme-toggle', false);
    }

    public function test_light_theme_cookie_renders_the_light_panel(): void
    {
        $this->actingAs($this->createGymOwner());

        $this->withUnencryptedCookie('theme', 'light')
            ->get(route('gym.dashboard'))->assertOk()
            ->assertSee('panel-theme bg-gray-50"', false)
            ->assertDontSee('panel-theme bg-gray-50 theme-dark', false);
    }

    public function test_member_portal_honours_the_theme_cookie(): void
    {
        $gym = $this->gymOf($this->createGymOwner());
        $member = $this->createMember($gym, ['email' => 'theme-member@example.com'], ['portal_password' => 'password']);
        $this->actingAs($member->user);

        $this->get(route('portal.dashboard'))->assertOk()->assertSee('theme-dark', false)->assertSee('data-theme-toggle', false);
        $this->withUnencryptedCookie('theme', 'light')->get(route('portal.dashboard'))->assertOk()
            ->assertDontSee('panel-theme bg-gray-50 theme-dark', false);
    }
}
