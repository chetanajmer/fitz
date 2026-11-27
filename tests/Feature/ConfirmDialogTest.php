<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ConfirmDialogTest extends TestCase
{
    use RefreshDatabase;

    public function test_gym_panel_pages_render_the_styled_confirm_dialog(): void
    {
        $owner = $this->createGymOwner();
        $member = $this->createMember($this->gymOf($owner));
        $this->actingAs($owner);

        $this->get(route('gym.members.show', $member))->assertOk()
            ->assertSee('x-data="confirmDialog"', false)
            ->assertSee('wire:confirm="Delete', false);
    }

    public function test_member_portal_renders_the_styled_confirm_dialog(): void
    {
        $gym = $this->gymOf($this->createGymOwner());
        $member = $this->createMember($gym, ['email' => 'dialog-member@example.com'], ['portal_password' => 'password']);
        $this->actingAs($member->user);

        $this->get(route('portal.dashboard'))->assertOk()->assertSee('x-data="confirmDialog"', false);
    }

    public function test_flashed_messages_render_as_toasts(): void
    {
        $this->actingAs($this->createGymOwner());

        $this->withSession(['warning' => 'Membership ends soon.'])
            ->get(route('gym.dashboard'))->assertOk()
            ->assertSee("add('Membership ends soon.', 'warning')", false);
    }

    public function test_app_code_does_not_open_native_browser_dialogs(): void
    {
        $offenders = collect([resource_path('views'), resource_path('js')])
            ->flatMap(fn (string $directory) => File::allFiles($directory))
            ->filter(fn ($file) => preg_match('/(?<![\w.$])(confirm|alert|prompt)\s*\(/', $file->getContents()) === 1)
            ->map(fn ($file) => $file->getRelativePathname())
            ->values()
            ->all();

        $this->assertSame([], $offenders, 'Use wire:confirm or window.confirmDialog() instead of native browser dialogs.');
    }
}
