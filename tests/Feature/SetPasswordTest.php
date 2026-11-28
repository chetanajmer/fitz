<?php

namespace Tests\Feature;

use App\Livewire\Gym\Members\Show as MemberShow;
use App\Livewire\Platform\Gyms\Show as PlatformGymShow;
use App\Models\Gym;
use App\Models\User;
use App\Services\MemberService;
use App\Services\TrainerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class SetPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->createGymOwner('Iron Den');
        $this->gym = $this->gymOf($this->owner);
    }

    protected function staffToken(User $user, string $password = 'password'): string
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/v1/admin/auth/login', ['email' => $user->email, 'password' => $password])
            ->assertOk()->json('data.token');
    }

    public function test_a_gym_admin_resets_a_trainers_password_from_the_app_and_signs_them_out(): void
    {
        $trainer = $this->createTrainer($this->gym);
        $trainerToken = $this->staffToken($trainer);
        $adminToken = $this->staffToken($this->owner);

        $this->withToken($adminToken)->postJson("/api/v1/admin/trainers/{$trainer->id}/password", ['password' => 'new-secret-1', 'password_confirmation' => 'nope'])
            ->assertStatus(422)->assertJsonValidationErrors(['password'], 'errors');

        $this->withToken($adminToken)->postJson("/api/v1/admin/trainers/{$trainer->id}/password", ['password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1'])
            ->assertOk()->assertJsonPath('data.trainer.id', $trainer->id);

        $this->app['auth']->forgetGuards();
        $this->withToken($trainerToken)->getJson('/api/v1/admin/me')->assertUnauthorized();
        $this->postJson('/api/v1/admin/auth/login', ['email' => $trainer->email, 'password' => 'password'])->assertStatus(422)->assertJsonPath('code', 'INVALID_CREDENTIALS');
        $this->staffToken($trainer, 'new-secret-1');

        $other = $this->createTrainer($this->gymOf($this->createGymOwner('Other')));
        $this->withToken($this->staffToken($this->owner))->postJson("/api/v1/admin/trainers/{$other->id}/password", ['password' => 'x-secret-12', 'password_confirmation' => 'x-secret-12'])
            ->assertNotFound();
        $this->withToken($this->staffToken($trainer, 'new-secret-1'))->postJson("/api/v1/admin/trainers/{$trainer->id}/password", ['password' => 'x-secret-12', 'password_confirmation' => 'x-secret-12'])
            ->assertForbidden();
    }

    public function test_a_gym_admin_sets_a_members_website_password_from_the_app(): void
    {
        $noEmail = $this->createMember($this->gym);
        $member = $this->createMember($this->gym, ['email' => 'asha@example.test']);
        $appToken = $member->createToken('phone')->plainTextToken;
        $api = $this->withToken($this->staffToken($this->owner));

        $api->postJson("/api/v1/admin/members/{$noEmail->id}/password", ['password' => 'member-pass', 'password_confirmation' => 'member-pass'])
            ->assertStatus(422)->assertJsonValidationErrors(['email'], 'errors');

        $api->postJson("/api/v1/admin/members/{$member->id}/password", ['password' => 'member-pass', 'password_confirmation' => 'member-pass'])
            ->assertOk()->assertJsonPath('data.login_email', 'asha@example.test');

        $user = $member->fresh('user')->user;
        $this->assertTrue($user->isMember());
        $this->assertTrue(Hash::check('member-pass', $user->password));

        $api->postJson("/api/v1/admin/members/{$member->id}/password", ['password' => 'member-pass-2', 'password_confirmation' => 'member-pass-2'])->assertOk();
        $this->assertTrue(Hash::check('member-pass-2', $user->fresh()->password));
        $this->assertSame($user->id, $member->fresh()->user_id);

        // The member app signs in with an OTP and stays signed in.
        $this->app['auth']->forgetGuards();
        $this->withToken($appToken)->getJson('/api/v1/me')->assertOk();
    }

    public function test_a_trainer_without_member_editing_cannot_set_a_member_password(): void
    {
        $trainer = $this->createTrainer($this->gym);
        $member = $this->createMember($this->gym, ['email' => 'ravi@example.test']);
        $this->inGym($this->gym, fn () => app(MemberService::class)->assignTrainer($member, $trainer->id));

        $this->withToken($this->staffToken($trainer))->postJson("/api/v1/admin/members/{$member->id}/password", ['password' => 'member-pass', 'password_confirmation' => 'member-pass'])
            ->assertForbidden();
    }

    public function test_the_super_admin_sets_a_gym_admins_password_on_the_web(): void
    {
        $ownerToken = $this->staffToken($this->owner);
        $member = $this->createMember($this->gym, ['email' => 'm@example.test'], ['portal_password' => 'member-pass']);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        Livewire::test(PlatformGymShow::class, ['gym' => $this->gym])
            ->set('tab', 'access')
            ->assertSee('Set password')
            ->call('openPassword', $this->owner->id)
            ->assertSet('showPassword', true)
            ->set('newPassword', 'owner-new-1')
            ->set('newPassword_confirmation', 'other')
            ->call('savePassword')
            ->assertHasErrors(['newPassword'])
            ->set('newPassword_confirmation', 'owner-new-1')
            ->call('savePassword')
            ->assertHasNoErrors()
            ->assertSet('showPassword', false)
            ->call('openPassword', $member->user_id)
            ->assertForbidden();

        $this->assertTrue(Hash::check('owner-new-1', $this->owner->fresh()->password));

        $this->app['auth']->forgetGuards();
        $this->withToken($ownerToken)->getJson('/api/v1/admin/me')->assertUnauthorized();

        $otherOwner = $this->createGymOwner('Other');
        Livewire::actingAs($admin)->test(PlatformGymShow::class, ['gym' => $this->gym])->call('openPassword', $otherOwner->id)->assertNotFound();
    }

    public function test_a_gym_admin_sets_a_members_website_password_on_the_web(): void
    {
        $member = $this->createMember($this->gym, ['email' => 'priya@example.test']);
        $this->actingAs($this->owner);

        Livewire::test(MemberShow::class, ['member' => $member])
            ->assertSee('Set website password')
            ->call('openPasswordModal')
            ->set('portalPassword', 'member-pass')
            ->set('portalPassword_confirmation', 'member-pass')
            ->call('savePassword')
            ->assertHasNoErrors()
            ->assertSet('showPasswordModal', false);

        $this->assertTrue(Hash::check('member-pass', $member->fresh('user')->user->password));
    }

    public function test_changing_a_trainers_password_on_the_web_form_signs_them_out_of_the_app(): void
    {
        $trainer = $this->createTrainer($this->gym);
        $token = $this->staffToken($trainer);

        $this->inGym($this->gym, fn () => app(TrainerService::class)->update($trainer, ['password' => 'fresh-pass-1']));

        $this->assertTrue(Hash::check('fresh-pass-1', $trainer->fresh()->password));
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/admin/me')->assertUnauthorized();
    }
}
