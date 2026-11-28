<?php

namespace Tests\Feature\Api;

use App\Models\Gym;
use App\Models\User;
use App\Services\TrainerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class StaffTrainerApiTest extends TestCase
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
        return $this->postJson('/api/v1/admin/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()->json('data.token');
    }

    protected function asStaff(User $user): static
    {
        return $this->withToken($this->tokenFor($user));
    }

    public function test_gym_admin_adds_a_trainer_who_can_then_sign_in_with_default_access(): void
    {
        Storage::fake('public');

        $response = $this->asStaff($this->owner)->post('/api/v1/admin/trainers', [
            'name' => 'Ravi Kumar',
            'email' => 'Ravi@Example.test',
            'phone' => '9876500000',
            'specialization' => 'Strength',
            'password' => 'secret123',
            'photo' => UploadedFile::fake()->image('ravi.jpg'),
        ], ['Accept' => 'application/json', 'Idempotency-Key' => (string) Str::uuid()]);

        $response->assertCreated()
            ->assertJsonPath('data.trainer.email', 'ravi@example.test')
            ->assertJsonPath('data.trainer.status', 'active')
            ->assertJsonPath('data.trainer.permissions', ['attendance.manage', 'content.custom', 'members.view'])
            ->assertJsonPath('data.trainer.photo_url', fn ($url) => str_contains($url, "/storage/gyms/{$this->gym->id}/trainers/"))
            ->assertJsonCount(4, 'data.access.presets');

        $this->postJson('/api/v1/admin/auth/login', ['email' => 'ravi@example.test', 'password' => 'secret123'])
            ->assertOk()->assertJsonPath('data.user.role', 'trainer');

        $this->asStaff($this->owner)->postJson('/api/v1/admin/trainers', ['name' => 'Dup', 'email' => 'ravi@example.test'])
            ->assertStatus(422)->assertJsonValidationErrors(['email', 'password'], 'errors');
    }

    public function test_list_search_and_status_filter(): void
    {
        $ravi = $this->createTrainer($this->gym);
        $ravi->forceFill(['name' => 'Ravi', 'status' => 'inactive'])->save();
        $this->createTrainer($this->gym)->forceFill(['name' => 'Asha'])->save();
        $this->createTrainer($this->gymOf($this->createGymOwner('Other')));
        $this->createMember($this->gym)->forceFill(['trainer_id' => $ravi->id])->save();

        $api = $this->asStaff($this->owner);
        $api->getJson('/api/v1/admin/trainers')->assertOk()
            ->assertJsonCount(2, 'data.trainers')
            ->assertJsonPath('data.trainers.0.name', 'Asha')
            ->assertJsonPath('data.trainers.1.members_count', 1);

        $api->getJson('/api/v1/admin/trainers?status=inactive')->assertOk()->assertJsonCount(1, 'data.trainers')->assertJsonPath('data.trainers.0.name', 'Ravi');
        $api->getJson('/api/v1/admin/trainers?search=ash')->assertOk()->assertJsonCount(1, 'data.trainers');
    }

    public function test_update_is_partial_and_password_only_changes_when_sent(): void
    {
        $trainer = $this->createTrainer($this->gym);
        $api = $this->asStaff($this->owner);

        $api->putJson("/api/v1/admin/trainers/{$trainer->id}", ['specialization' => 'Yoga', 'bio' => 'Ten years'])
            ->assertOk()
            ->assertJsonPath('data.trainer.name', $trainer->name)
            ->assertJsonPath('data.trainer.specialization', 'Yoga')
            ->assertJsonPath('data.trainer.bio', 'Ten years');
        $this->postJson('/api/v1/admin/auth/login', ['email' => $trainer->email, 'password' => 'password'])->assertOk();

        $api->putJson("/api/v1/admin/trainers/{$trainer->id}", ['password' => 'newpass99'])->assertOk();
        $this->postJson('/api/v1/admin/auth/login', ['email' => $trainer->email, 'password' => 'newpass99'])->assertOk();

        $api->putJson("/api/v1/admin/trainers/{$trainer->id}", ['email' => $this->owner->email, 'password' => 'short'])
            ->assertStatus(422)->assertJsonValidationErrors(['email', 'password'], 'errors');

        Storage::fake('public');
        $api->post("/api/v1/admin/trainers/{$trainer->id}/photo", ['photo' => UploadedFile::fake()->image('t.jpg')], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('data.trainer.photo_url', fn ($url) => str_contains($url, '/trainers/'));
    }

    public function test_switching_off_blocks_the_trainers_app(): void
    {
        $trainer = $this->createTrainer($this->gym);
        $trainerToken = $this->tokenFor($trainer);
        $adminToken = $this->tokenFor($this->owner);

        $this->withToken($adminToken)->postJson("/api/v1/admin/trainers/{$trainer->id}/toggle")
            ->assertOk()->assertJsonPath('data.trainer.status', 'inactive');

        $this->app['auth']->forgetGuards();
        $this->withToken($trainerToken)->getJson('/api/v1/admin/me')->assertForbidden()->assertJsonPath('code', 'ACCOUNT_DISABLED');

        $this->app['auth']->forgetGuards();
        $this->withToken($adminToken)->postJson("/api/v1/admin/trainers/{$trainer->id}/toggle", ['status' => 'active'])
            ->assertOk()->assertJsonPath('data.trainer.status', 'active');
        $this->withToken($adminToken)->postJson("/api/v1/admin/trainers/{$trainer->id}/toggle", ['status' => 'active'])
            ->assertOk()->assertJsonPath('data.trainer.status', 'active');
    }

    public function test_access_by_permissions_or_preset(): void
    {
        $trainer = $this->createTrainer($this->gym);
        $api = $this->asStaff($this->owner);

        // members.manage brings members.view with it; gym.settings is never grantable.
        $api->putJson("/api/v1/admin/trainers/{$trainer->id}/access", ['permissions' => ['members.manage']])
            ->assertOk()->assertJsonPath('data.trainer.permissions', ['members.manage', 'members.view']);
        $api->putJson("/api/v1/admin/trainers/{$trainer->id}/access", ['permissions' => ['gym.settings']])
            ->assertStatus(422)->assertJsonValidationErrors(['permissions.0'], 'errors');

        $api->putJson("/api/v1/admin/trainers/{$trainer->id}/access", ['preset' => 'none'])
            ->assertOk()->assertJsonPath('data.trainer.permissions', []);
        $api->putJson("/api/v1/admin/trainers/{$trainer->id}/access", ['preset' => 'front_desk'])
            ->assertOk()->assertJsonCount(7, 'data.trainer.permissions')
            ->assertJsonPath('data.access.sections.0.permissions.0', fn ($p) => $p['key'] === 'members.view' && $p['granted'] === true);

        $this->app['auth']->forgetGuards();
        $this->asStaff($trainer->fresh())->getJson('/api/v1/admin/me')
            ->assertOk()->assertJsonPath('data.permissions', fn ($perms) => in_array('payments.manage', $perms, true));
    }

    public function test_assign_and_unassign_members_ignoring_other_gyms(): void
    {
        $trainer = $this->createTrainer($this->gym);
        $mine = $this->createMember($this->gym);
        $foreign = $this->createMember($this->gymOf($this->createGymOwner('Other')));
        $api = $this->asStaff($this->owner);

        $api->postJson("/api/v1/admin/trainers/{$trainer->id}/members", ['member_ids' => [$mine->id, $foreign->id]])
            ->assertOk()
            ->assertJsonPath('data.assigned', 1)
            ->assertJsonCount(1, 'data.members')
            ->assertJsonPath('data.members.0.id', $mine->id)
            ->assertJsonPath('data.trainer.members_count', 1);
        $this->assertNull($foreign->fresh()->trainer_id);

        $api->deleteJson("/api/v1/admin/trainers/{$trainer->id}/members/{$mine->id}")
            ->assertOk()->assertJsonCount(0, 'data.members');
        $this->assertNull($mine->fresh()->trainer_id);

        $api->deleteJson("/api/v1/admin/trainers/{$trainer->id}/members/{$foreign->id}")->assertNotFound();
    }

    public function test_delete_unassigns_members_and_ends_the_trainers_sessions(): void
    {
        $trainer = $this->createTrainer($this->gym);
        $member = $this->createMember($this->gym);
        $member->forceFill(['trainer_id' => $trainer->id])->save();
        $trainerToken = $this->tokenFor($trainer);

        $this->asStaff($this->owner)->deleteJson("/api/v1/admin/trainers/{$trainer->id}")->assertOk();

        $this->assertNull($member->fresh()->trainer_id);
        $this->assertModelMissing($trainer);
        $this->app['auth']->forgetGuards();
        $this->withToken($trainerToken)->getJson('/api/v1/admin/me')->assertUnauthorized();
    }

    public function test_trainers_and_other_gyms_cannot_manage_trainers(): void
    {
        $trainer = $this->createTrainer($this->gym);
        $this->inGym($this->gym, fn () => app(TrainerService::class)->setPermissions($trainer, ['members.view', 'members.all', 'members.manage']));
        $foreignTrainer = $this->createTrainer($this->gymOf($this->createGymOwner('Other')));

        $this->asStaff($trainer->fresh())->getJson('/api/v1/admin/trainers')->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');

        $this->app['auth']->forgetGuards();
        $api = $this->asStaff($this->owner);
        $api->getJson("/api/v1/admin/trainers/{$foreignTrainer->id}")->assertNotFound()->assertJsonPath('code', 'NOT_FOUND');
        $api->deleteJson("/api/v1/admin/trainers/{$foreignTrainer->id}")->assertNotFound();
        $api->getJson("/api/v1/admin/trainers/{$this->owner->id}")->assertNotFound();
        $this->assertModelExists($foreignTrainer);
    }
}
