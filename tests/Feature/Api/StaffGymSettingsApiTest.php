<?php

namespace Tests\Feature\Api;

use App\Models\City;
use App\Models\Gym;
use App\Models\GymPhoto;
use App\Models\User;
use App\Services\GymProfileService;
use App\Services\TrainerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaffGymSettingsApiTest extends TestCase
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

    protected function asStaff(User $user): static
    {
        $token = $this->postJson('/api/v1/admin/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()->json('data.token');

        return $this->withToken($token);
    }

    public function test_gym_admin_gets_the_full_settings_and_trainers_are_forbidden(): void
    {
        $this->asStaff($this->owner)->getJson('/api/v1/admin/gym')
            ->assertOk()
            ->assertJsonPath('data.profile.name', 'Iron Den')
            ->assertJsonPath('data.branding.primary_color', '#ff6b00')
            ->assertJsonPath('data.dashboard_themes.admin.custom', false)
            ->assertJsonCount(6, 'data.dashboard_themes.presets')
            ->assertJsonCount(7, 'data.opening_hours')
            ->assertJsonPath('data.rules.weigh_in_every_days', 15)
            ->assertJsonPath('data.links.check_in_qr_mode', 'printed')
            ->assertJsonStructure(['data' => ['gym', 'profile', 'branding', 'social', 'photos', 'rules', 'links', 'completeness' => ['percent', 'missing']]]);

        $trainer = $this->createTrainer($this->gym);
        $this->inGym($this->gym, fn () => app(TrainerService::class)->setPermissions($trainer, ['members.view']));

        $this->app['auth']->forgetGuards();
        $this->asStaff($trainer->fresh())->getJson('/api/v1/admin/gym')->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_profile_update_is_partial_and_merges_social_links_and_hours(): void
    {
        $api = $this->asStaff($this->owner);

        $api->putJson('/api/v1/admin/gym', [
            'tagline' => 'Lift heavy',
            'primary_color' => '#3B82F6',
            'facilities' => ['Sauna', 'Parking'],
            'social' => ['instagram' => 'https://instagram.com/irondena'],
            'opening_hours' => [['day' => 'sunday', 'closed' => false, 'open' => '09:00', 'close' => '13:00']],
        ])->assertOk();

        $api->putJson('/api/v1/admin/gym', [
            'description' => 'Best gym in town',
            'social' => ['website' => 'https://irondena.test'],
        ])->assertOk()
            ->assertJsonPath('data.profile.name', 'Iron Den')
            ->assertJsonPath('data.profile.description', 'Best gym in town')
            ->assertJsonPath('data.branding.tagline', 'Lift heavy')
            ->assertJsonPath('data.branding.primary_color', '#3b82f6')
            ->assertJsonPath('data.branding.secondary_color', '#111111')
            ->assertJsonPath('data.facilities', ['Sauna', 'Parking'])
            ->assertJsonPath('data.social.instagram', 'https://instagram.com/irondena')
            ->assertJsonPath('data.social.website', 'https://irondena.test')
            ->assertJsonPath('data.opening_hours.6', ['day' => 'sunday', 'open' => '09:00', 'close' => '13:00', 'closed' => false])
            ->assertJsonPath('data.opening_hours.0.open', '06:00');

        $api->putJson('/api/v1/admin/gym', ['primary_color' => 'orange', 'name' => '', 'opening_hours' => [['day' => 'funday']]])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonValidationErrors(['primary_color', 'name', 'opening_hours.0.day'], 'errors');
    }

    public function test_city_and_locality_lists_and_changing_the_city(): void
    {
        $jaipur = City::factory()->create(['name' => 'Jaipur']);
        City::factory()->inactive()->create(['name' => 'Hidden Town']);
        $api = $this->asStaff($this->owner);

        $names = collect($api->getJson('/api/v1/admin/gym/cities')->assertOk()->json('data.cities'))->pluck('name');
        $this->assertContains('Jaipur', $names);
        $this->assertNotContains('Hidden Town', $names);

        $api->putJson('/api/v1/admin/gym', ['city_id' => $jaipur->id, 'locality' => 'Malviya Nagar'])
            ->assertOk()
            ->assertJsonPath('data.profile.city.name', 'Jaipur')
            ->assertJsonPath('data.profile.locality', 'Malviya Nagar');

        $api->getJson("/api/v1/admin/gym/cities/{$jaipur->id}/localities")
            ->assertOk()->assertJsonPath('data.localities.0.name', 'Malviya Nagar');
    }

    public function test_logo_banner_and_photos_can_be_uploaded_and_removed(): void
    {
        Storage::fake('public');
        $api = $this->asStaff($this->owner);

        $logoUrl = $api->post('/api/v1/admin/gym/logo', ['logo' => UploadedFile::fake()->image('logo.png', 900, 900)], ['Accept' => 'application/json'])
            ->assertOk()->json('data.branding.logo_url');
        $this->assertStringContainsString("/storage/gyms/{$this->gym->id}/branding/logo-", $logoUrl);
        Storage::disk('public')->assertExists($this->gym->fresh()->logo_path);

        $api->post('/api/v1/admin/gym/banner', ['banner' => UploadedFile::fake()->image('banner.jpg', 2000, 800)], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('data.branding.banner_url', fn ($url) => str_contains($url, '/branding/banner-'));

        $api->deleteJson('/api/v1/admin/gym/logo')->assertOk()->assertJsonPath('data.branding.logo_url', null);
        $api->deleteJson('/api/v1/admin/gym/banner')->assertOk()->assertJsonPath('data.branding.banner_url', null);

        $photos = $api->post('/api/v1/admin/gym/photos', [
            'photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
            'caption' => 'Floor',
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonCount(2, 'data.photos')->json('data.photos');

        $api->deleteJson('/api/v1/admin/gym/photos/'.$photos[0]['id'])->assertOk()->assertJsonCount(1, 'data.photos');

        $api->post('/api/v1/admin/gym/logo', ['logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors(['logo'], 'errors');
    }

    public function test_photo_limit_and_other_gyms_photos(): void
    {
        Storage::fake('public');
        $this->inGym($this->gym, function () {
            foreach (range(1, 30) as $i) {
                app(GymProfileService::class)->addPhoto($this->gym, UploadedFile::fake()->image("p{$i}.jpg"));
            }
        });
        $other = $this->gymOf($this->createGymOwner('Other Gym'));
        $foreign = $this->inGym($other, fn () => app(GymProfileService::class)->addPhoto($other, UploadedFile::fake()->image('x.jpg')));

        $api = $this->asStaff($this->owner);
        $api->post('/api/v1/admin/gym/photos', ['photos' => [UploadedFile::fake()->image('one.jpg')]], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('code', 'PHOTO_LIMIT_REACHED')->assertJsonPath('data.limit', 30);

        $api->deleteJson("/api/v1/admin/gym/photos/{$foreign->id}")->assertNotFound()->assertJsonPath('code', 'NOT_FOUND');
        $this->assertTrue(GymPhoto::withoutGlobalScopes()->whereKey($foreign->id)->exists());
    }

    public function test_dashboard_theme_is_checked_for_readability_and_can_be_reset(): void
    {
        $api = $this->asStaff($this->owner);

        $api->putJson('/api/v1/admin/gym/theme/member', ['primary' => '#ffff00', 'secondary' => '#eeeeee'])
            ->assertStatus(422)->assertJsonValidationErrors(['primary', 'secondary'], 'errors');

        $api->putJson('/api/v1/admin/gym/theme/member', ['primary' => '#059669', 'secondary' => '#06140F'])
            ->assertOk()
            ->assertJsonPath('data.dashboard_themes.member', ['primary' => '#059669', 'secondary' => '#06140f', 'custom' => true])
            ->assertJsonPath('data.dashboard_themes.admin.custom', false);

        $api->deleteJson('/api/v1/admin/gym/theme/member')->assertOk()->assertJsonPath('data.dashboard_themes.member.custom', false);
        $api->putJson('/api/v1/admin/gym/theme/public', ['primary' => '#059669', 'secondary' => '#06140f'])->assertNotFound();
    }

    public function test_rules_update_check_in_streak_and_weigh_in_settings(): void
    {
        $api = $this->asStaff($this->owner);

        $api->putJson('/api/v1/admin/gym/attendance-settings', ['qr_checkin' => false])
            ->assertOk()
            ->assertJsonPath('data.rules.attendance', ['member_self_checkin' => true, 'qr_checkin' => false, 'checkin_hours_only' => false]);
        $api->putJson('/api/v1/admin/gym/attendance-settings', ['qr_checkin' => 'maybe'])->assertStatus(422);

        $api->putJson('/api/v1/admin/gym/rules', ['weigh_in_every_days' => 7])
            ->assertOk()->assertJsonPath('data.streaks_recalculated', null);

        $api->putJson('/api/v1/admin/gym/rules', ['streak' => ['grace_days' => 2, 'milestones' => [30, 7, 7]], 'weigh_in_every_days' => 30])
            ->assertOk()
            ->assertJsonPath('data.rules.streak', ['grace_days' => 2, 'skip_closed_days' => true, 'milestones' => [7, 30]])
            ->assertJsonPath('data.rules.weigh_in_every_days', 30)
            ->assertJsonPath('data.rules.attendance.qr_checkin', false)
            ->assertJsonPath('data.streaks_recalculated', 0);

        $api->putJson('/api/v1/admin/gym/rules', ['weigh_in_every_days' => 10, 'streak' => ['grace_days' => 9]])
            ->assertStatus(422)->assertJsonValidationErrors(['weigh_in_every_days', 'streak.grace_days'], 'errors');
    }

    public function test_qr_mode_and_regenerating_links(): void
    {
        $api = $this->asStaff($this->owner);
        $before = $this->gym->only(['join_token', 'register_token', 'checkin_token']);

        $api->putJson('/api/v1/admin/gym/qr/check-in-mode', ['mode' => 'screen'])->assertOk()->assertJsonPath('data.links.check_in_qr_mode', 'screen');
        $api->putJson('/api/v1/admin/gym/qr/check-in-mode', ['mode' => 'paper'])->assertStatus(422);

        $joinUrl = $api->postJson('/api/v1/admin/gym/qr/regenerate', ['which' => 'join'])->assertOk()->json('data.links.join_url');
        $api->postJson('/api/v1/admin/gym/qr/regenerate', ['which' => 'register'])->assertOk();
        $api->postJson('/api/v1/admin/gym/qr/regenerate', ['which' => 'check_in'])->assertOk();
        $api->postJson('/api/v1/admin/gym/qr/regenerate', ['which' => 'everything'])->assertStatus(422);
        $api->getJson('/api/v1/admin/gym/qr')->assertOk()->assertJsonPath('data.join_url', $joinUrl)->assertJsonPath('data.check_in_qr_mode', 'screen');

        $after = $this->gym->fresh();
        $this->assertNotSame($before['join_token'], $after->join_token);
        $this->assertNotSame($before['register_token'], $after->register_token);
        $this->assertNotSame($before['checkin_token'], $after->checkin_token);
        $this->assertSame($after->joinUrl(), $joinUrl);
    }
}
