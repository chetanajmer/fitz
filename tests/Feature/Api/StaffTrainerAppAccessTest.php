<?php

namespace Tests\Feature\Api;

use App\Models\Gym;
use App\Models\User;
use App\Support\StaffAppSections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffTrainerAppAccessTest extends TestCase
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

    protected function tokenFor(User $user): string
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/v1/admin/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()->json('data.token');
    }

    public function test_a_trainer_signs_in_and_sees_only_the_default_sections(): void
    {
        $trainer = $this->createTrainer($this->gym);

        $login = $this->postJson('/api/v1/admin/auth/login', ['email' => $trainer->email, 'password' => 'password'])->assertOk()
            ->assertJsonPath('data.user.role', 'trainer')
            ->assertJsonPath('data.sections.dashboard', true)
            ->assertJsonPath('data.sections.members', true)
            ->assertJsonPath('data.sections.check_in', true)
            ->assertJsonPath('data.sections.attendance', true)
            ->assertJsonPath('data.sections.programs', true)
            ->assertJsonPath('data.sections.members_all', false)
            ->assertJsonPath('data.sections.payments', false)
            ->assertJsonPath('data.sections.enquiries', false)
            ->assertJsonPath('data.sections.trainers', false)
            ->assertJsonPath('data.sections.gym_settings', false)
            ->assertJsonPath('data.sections.billing', false);

        $this->assertCount(count(StaffAppSections::RULES), $login->json('data.sections'));
    }

    public function test_a_gym_admin_sees_every_section(): void
    {
        $sections = $this->withToken($this->tokenFor($this->owner))->getJson('/api/v1/admin/me')->assertOk()->json('data.sections');

        $this->assertSame(array_keys(StaffAppSections::RULES), array_keys(array_filter($sections)));
    }

    public function test_access_the_admin_switches_on_shows_up_for_the_signed_in_trainer(): void
    {
        $trainer = $this->createTrainer($this->gym);
        $trainerToken = $this->tokenFor($trainer);
        $adminToken = $this->tokenFor($this->owner);

        $before = $this->withToken($trainerToken)->getJson('/api/v1/admin/me')->assertOk();
        $this->withToken($trainerToken)->getJson('/api/v1/admin/payments')->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN')
            ->assertJsonPath('data.sections.payments', false);

        $this->app['auth']->forgetGuards();
        $this->withToken($adminToken)->putJson("/api/v1/admin/trainers/{$trainer->id}/access", [
            'permissions' => ['members.view', 'payments.manage', 'enquiries.manage'],
        ])->assertOk();

        $this->app['auth']->forgetGuards();
        $after = $this->withToken($trainerToken)->getJson('/api/v1/admin/payments')->assertOk();
        $this->assertNotSame($before->json('data.access_version'), $after->headers->get('X-Staff-Access'));

        $this->withToken($trainerToken)->getJson('/api/v1/admin/me')->assertOk()
            ->assertHeader('X-Staff-Access', $after->headers->get('X-Staff-Access'))
            ->assertJsonPath('data.access_version', $after->headers->get('X-Staff-Access'))
            ->assertJsonPath('data.sections.payments', true)
            ->assertJsonPath('data.sections.fees', true)
            ->assertJsonPath('data.sections.enquiries', true)
            ->assertJsonPath('data.sections.join_requests', false)
            ->assertJsonPath('data.sections.check_in', false);

        $this->withToken($trainerToken)->getJson('/api/v1/admin/attendance/today')->assertForbidden();
    }

    public function test_the_add_trainer_screen_gets_the_access_checklist(): void
    {
        $options = $this->withToken($this->tokenFor($this->owner))->getJson('/api/v1/admin/trainers/access-options')->assertOk()
            ->assertJsonPath('data.access.defaults', ['members.view', 'attendance.manage', 'content.custom'])
            ->assertJsonPath('data.access.sections.0.title', 'Members')
            ->assertJsonPath('data.access.sections.0.permissions.0.granted', true)
            ->assertJsonPath('data.access.sections.0.permissions.1.granted', false);
        $this->assertSame('members.view', $options->json('data.access.requires')['members.all']);

        $this->withToken($this->tokenFor($this->createTrainer($this->gym)))->getJson('/api/v1/admin/trainers/access-options')->assertForbidden();
    }
}
