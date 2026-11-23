<?php

namespace Tests\Feature\Api;

use App\Models\Gym;
use App\Models\Member;
use App\Services\AttendanceService;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MemberProfileApiTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected Member $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-23 10:00'));
        $this->gym = $this->gymOf($this->createGymOwner('Profile Gym'));
        $trainer = $this->createTrainer($this->gym);
        $this->member = $this->createMember($this->gym, [
            'name' => 'Sneha Pillai', 'phone' => '8233130616', 'email' => 'sneha@example.test', 'gender' => 'female',
            'date_of_birth' => '1996-05-10', 'blood_group' => 'B+', 'city' => 'Pune', 'fitness_goal' => 'Weight loss',
            'medical_notes' => 'Mild asthma', 'notes' => 'STAFF ONLY: asked for discount',
            'emergency_contact_name' => 'Ravi', 'emergency_contact_phone' => '9811111111', 'emergency_contact_relation' => 'Brother',
            'joining_date' => '2026-08-31',
        ], ['trainer_id' => $trainer->id]);

        $plan = $this->createPlan($this->gym, ['name' => 'Quarterly Pro', 'price' => 3000]);
        $this->inGym($this->gym, function () use ($plan) {
            app(MembershipService::class)->assign($this->member, $plan, '2026-11-01', payment: ['amount' => 1000, 'method' => 'upi', 'reference' => 'UPI123']);
            app(AttendanceService::class)->checkIn($this->member);
        });
    }

    protected function api(): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->member->createToken('app', ['member'])->plainTextToken);
    }

    public function test_profile_returns_all_member_data_without_staff_notes(): void
    {
        $response = $this->api()->getJson('/api/v1/me/profile')->assertOk()
            ->assertJsonPath('data.personal.name', 'Sneha Pillai')
            ->assertJsonPath('data.personal.member_code', $this->member->member_code)
            ->assertJsonPath('data.personal.age', 30)
            ->assertJsonPath('data.personal.blood_group', 'B+')
            ->assertJsonPath('data.emergency_contact.relation', 'Brother')
            ->assertJsonPath('data.health.medical_notes', 'Mild asthma')
            ->assertJsonPath('data.account.joining_date', '2026-08-31')
            ->assertJsonPath('data.account.member_since_days', 84)
            ->assertJsonPath('data.gym.name', 'Profile Gym')
            ->assertJsonPath('data.trainer.id', $this->member->trainer_id)
            ->assertJsonPath('data.membership.plan_name', 'Quarterly Pro')
            ->assertJsonPath('data.membership.payment_status', 'partial')
            ->assertJsonCount(1, 'data.membership_history')
            ->assertJsonPath('data.payments.total_paid', 1000)
            ->assertJsonPath('data.payments.pending_amount', 2000)
            ->assertJsonPath('data.payments.recent.0.method', 'upi')
            ->assertJsonPath('data.payments.recent.0.reference', 'UPI123')
            ->assertJsonPath('data.attendance.total_visits', 1)
            ->assertJsonPath('data.attendance.checked_in_today', true)
            ->assertJsonPath('data.streak.current_streak', 1)
            ->assertJsonStructure(['data' => ['address', 'plans' => ['diet', 'workout', 'custom_for_me'], 'check_in' => ['qr_payload', 'self_check_in_enabled'], 'editable_fields']]);

        $this->assertStringContainsString($this->member->fresh()->qr_token, $response->json('data.check_in.qr_payload'));
        $this->assertStringNotContainsString('STAFF ONLY', $response->getContent());
    }

    public function test_member_updates_only_editable_fields(): void
    {
        $this->api()->patchJson('/api/v1/me/profile', [
            'city' => 'Mumbai', 'address' => '12 Marine Drive', 'blood_group' => 'O+', 'fitness_goal' => '',
            'name' => 'Hacker', 'phone' => '9999999999', 'status' => 'inactive', 'notes' => 'x', 'trainer_id' => null,
        ])->assertOk()
            ->assertJsonPath('message', 'Profile updated.')
            ->assertJsonPath('data.address.city', 'Mumbai')
            ->assertJsonPath('data.health.fitness_goal', null);

        $member = $this->member->fresh();
        $this->assertSame('Sneha Pillai', $member->name);
        $this->assertSame('8233130616', $member->phone);
        $this->assertSame('active', $member->status->value);
        $this->assertNotNull($member->trainer_id);
        $this->assertSame('STAFF ONLY: asked for discount', $member->notes);
        $this->assertSame('12 Marine Drive', $member->address);

        $this->api()->patchJson('/api/v1/me/profile', ['blood_group' => 'Z+', 'date_of_birth' => '2099-01-01', 'email' => 'nope'])
            ->assertStatus(422)->assertJsonValidationErrors(['blood_group', 'date_of_birth', 'email']);
    }

    public function test_photo_upload_and_removal(): void
    {
        Storage::fake('public');

        $url = $this->api()->post('/api/v1/me/profile/photo', ['photo' => UploadedFile::fake()->image('me.jpg', 400, 400)], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('data.has_photo', true)->json('data.photo_url');

        $path = $this->member->fresh()->photo_path;
        Storage::disk('public')->assertExists($path);
        $this->assertStringContainsString($path, $url);

        $this->api()->deleteJson('/api/v1/me/profile/photo')->assertOk()->assertJsonPath('data.has_photo', false);
        Storage::disk('public')->assertMissing($path);
        $this->assertNull($this->member->fresh()->photo_path);

        $this->api()->post('/api/v1/me/profile/photo', ['photo' => UploadedFile::fake()->create('x.pdf', 10)], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_profile_requires_a_token(): void
    {
        $this->getJson('/api/v1/me/profile')->assertUnauthorized();
    }
}
