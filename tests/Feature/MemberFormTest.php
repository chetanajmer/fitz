<?php

namespace Tests\Feature;

use App\Livewire\Gym\Members\Form;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MemberFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_browser_autofilled_password_does_not_block_saving_a_new_member(): void
    {
        $this->actingAs($this->createGymOwner());

        // The app-login box is unticked, so a stray password (browser autofill) is ignored.
        Livewire::test(Form::class)
            ->set('form.name', 'Ravi Kumar')
            ->set('form.phone', '9811100001')
            ->set('portal_password', 'owner-saved-password')
            ->call('save')
            ->assertHasNoErrors();

        $member = Member::sole();
        $this->assertSame('Ravi Kumar', $member->name);
        $this->assertNull($member->user_id);
    }

    public function test_editing_a_member_saves_the_changes(): void
    {
        $owner = $this->createGymOwner();
        $member = $this->createMember($this->gymOf($owner), ['name' => 'Old Name', 'email' => 'old@example.test']);
        $this->actingAs($owner);

        Livewire::test(Form::class, ['member' => $member])
            ->assertSet('form.name', 'Old Name')
            ->set('form.name', 'New Name')
            ->set('form.city', 'Jaipur')
            ->set('portal_password', 'owner-saved-password')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('gym.members.show', $member));

        $member->refresh();
        $this->assertSame('New Name', $member->name);
        $this->assertSame('Jaipur', $member->city);
        $this->assertNull($member->user_id);
    }

    public function test_app_login_needs_an_email_and_reports_it_on_the_email_field(): void
    {
        $this->actingAs($this->createGymOwner());

        Livewire::test(Form::class)
            ->set('form.name', 'No Email')
            ->set('form.phone', '9811100002')
            ->set('portal_login', true)
            ->set('portal_password', 'member-pass')
            ->call('save')
            ->assertHasErrors(['form.email'])
            ->assertDispatched('member-form-invalid');

        $this->assertSame(0, Member::count());
    }

    public function test_a_login_email_that_is_already_taken_shows_on_the_email_field(): void
    {
        $owner = $this->createGymOwner();
        $this->actingAs($owner);

        Livewire::test(Form::class)
            ->set('form.name', 'Taken Email')
            ->set('form.phone', '9811100003')
            ->set('form.email', $owner->email)
            ->set('portal_login', true)
            ->set('portal_password', 'member-pass')
            ->call('save')
            ->assertHasErrors(['form.email']);

        $this->assertSame(0, Member::count());
        $this->assertSame(1, User::where('email', $owner->email)->count());
    }

    public function test_app_login_is_created_when_ticked(): void
    {
        $this->actingAs($this->createGymOwner());

        Livewire::test(Form::class)
            ->set('form.name', 'App User')
            ->set('form.phone', '9811100004')
            ->set('form.email', 'app.user@example.test')
            ->set('portal_login', true)
            ->set('portal_password', 'member-pass')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(Member::sole()->user->isMember());
    }
}
