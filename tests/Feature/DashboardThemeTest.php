<?php

namespace Tests\Feature;

use App\Livewire\Gym\Settings\Profile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_colours_are_saved_and_repaint_the_gym_panel(): void
    {
        $owner = $this->createGymOwner('Iron Temple');
        $this->actingAs($owner);

        Livewire::test(Profile::class)
            ->set('tab', 'theme')
            ->set('theme.admin.primary', '#3B82F6')
            ->set('theme.admin.secondary', '#0b1220')
            ->call('saveTheme', 'admin')
            ->assertHasNoErrors()
            ->assertRedirect(route('gym.settings', ['tab' => 'theme']));

        $colors = $this->gymOf($owner)->dashboardColors('admin');
        $this->assertSame(['primary' => '#3b82f6', 'secondary' => '#0b1220', 'custom' => true], $colors);
        $this->assertFalse($this->gymOf($owner)->dashboardColors('member')['custom']);

        $this->get(route('gym.dashboard'))->assertOk()
            ->assertSee('--gym-orange: #3b82f6;', false)
            ->assertSee('--gym-black-deep: #0b1220;', false);
    }

    public function test_member_colours_apply_to_the_member_dashboard_only(): void
    {
        $owner = $this->createGymOwner('Iron Temple');
        $gym = $this->gymOf($owner);
        $member = $this->createMember($gym, ['email' => 'theme@example.test'], ['portal_password' => 'password']);

        $this->actingAs($owner);
        Livewire::test(Profile::class)
            ->call('applyThemePreset', 'member', 2)
            ->assertSet('theme.member.primary', '#059669')
            ->call('saveTheme', 'member')
            ->assertHasNoErrors();

        $this->get(route('gym.dashboard'))->assertOk()->assertDontSee('--gym-orange: #059669;', false);

        $this->actingAs($member->user);
        $this->get(route('portal.dashboard'))->assertOk()->assertSee('--gym-orange: #059669;', false);
    }

    public function test_unreadable_colours_are_rejected(): void
    {
        $this->actingAs($this->createGymOwner());

        Livewire::test(Profile::class)
            ->set('theme.admin.primary', '#ffff66')
            ->set('theme.admin.secondary', '#f5f5f5')
            ->call('saveTheme', 'admin')
            ->assertHasErrors(['theme.admin.primary', 'theme.admin.secondary']);

        Livewire::test(Profile::class)
            ->set('theme.admin.primary', 'orange')
            ->call('saveTheme', 'admin')
            ->assertHasErrors(['theme.admin.primary' => 'regex']);
    }

    public function test_every_quick_theme_passes_the_readability_rules(): void
    {
        $this->actingAs($this->createGymOwner());

        foreach (array_keys(Profile::THEME_PRESETS) as $preset) {
            Livewire::test(Profile::class)
                ->call('applyThemePreset', 'member', $preset)
                ->call('saveTheme', 'member')
                ->assertHasNoErrors();
        }
    }

    public function test_reset_returns_to_the_gymai_theme(): void
    {
        $owner = $this->createGymOwner();
        $this->actingAs($owner);

        Livewire::test(Profile::class)
            ->set('theme.admin.primary', '#e11d48')
            ->call('saveTheme', 'admin')
            ->call('resetTheme', 'admin')
            ->assertHasNoErrors();

        $this->assertFalse($this->gymOf($owner)->dashboardColors('admin')['custom']);
        $this->get(route('gym.dashboard'))->assertOk()->assertDontSee('--gym-orange: #e11d48;', false);
    }

    public function test_saving_the_gym_profile_keeps_dashboard_colours(): void
    {
        $owner = $this->createGymOwner();
        $this->actingAs($owner);

        Livewire::test(Profile::class)->set('theme.admin.primary', '#8b5cf6')->call('saveTheme', 'admin');
        Livewire::test(Profile::class)->set('profile.description', 'Updated')->call('save')->assertHasNoErrors();

        $this->assertSame('#8b5cf6', $this->gymOf($owner)->dashboardColors('admin')['primary']);
    }
}
