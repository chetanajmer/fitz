<?php

namespace Tests\Feature\Api;

use App\Contracts\SmsSender;
use App\Models\Member;
use App\Models\MemberOtp;
use App\Models\PlatformSetting;
use App\Services\MemberService;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberApiTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{phone:string, message:string}> */
    protected array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->sent = [];
        $this->app->instance(SmsSender::class, new class($this->sent) implements SmsSender
        {
            public function __construct(private array &$sent) {}

            public function send(string $phone, string $message): void
            {
                $this->sent[] = compact('phone', 'message');
            }
        });
    }

    protected function lastOtp(): string
    {
        preg_match('/^(\d{6})/', end($this->sent)['message'], $m);

        return $m[1];
    }

    protected function login(string $phone, ?int $gymId = null): array
    {
        $this->postJson('/api/v1/auth/send-otp', ['phone' => $phone, 'gym_id' => $gymId])->assertOk();

        return $this->postJson('/api/v1/auth/verify-otp', ['phone' => $phone, 'otp' => $this->lastOtp(), 'gym_id' => $gymId])
            ->assertOk()->json('data');
    }

    // ---- Version ---------------------------------------------------------------

    public function test_version_check_reports_force_and_optional_updates(): void
    {
        PlatformSetting::put(['android_latest_version' => '1.4.0', 'android_min_version' => '1.2.0']);

        $this->getJson('/api/v1/app/version?platform=android&version=1.1.9')
            ->assertOk()->assertJsonPath('data.force_update', true)->assertJsonPath('data.update_available', true);

        $this->getJson('/api/v1/app/version?platform=android&version=1.3.0')
            ->assertOk()->assertJsonPath('data.force_update', false)->assertJsonPath('data.update_available', true)
            ->assertJsonPath('data.latest_version', '1.4.0');

        $this->getJson('/api/v1/app/version?platform=android&version=1.4.0')
            ->assertOk()->assertJsonPath('data.update_available', false)->assertJsonPath('message', 'App is up to date.');

        $this->getJson('/api/v1/app/version?platform=windows&version=abc')
            ->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR')->assertJsonValidationErrors(['platform', 'version']);
    }

    // ---- Send / verify OTP ---------------------------------------------------------

    public function test_full_login_flow_returns_token_and_gym_id(): void
    {
        $gym = $this->gymOf($this->createGymOwner('Flex Gym'));
        $member = $this->createMember($gym, ['phone' => '98765 43210']);

        $this->postJson('/api/v1/auth/send-otp', ['phone' => '+91-9876543210'])
            ->assertOk()
            ->assertJsonPath('data.phone', 'XXXXXX3210')
            ->assertJsonPath('data.gym_id', $gym->id);

        $this->assertCount(1, $this->sent);
        $this->assertStringContainsString('Flex Gym', $this->sent[0]['message']);
        $this->assertNotSame($this->lastOtp(), MemberOtp::sole()->code_hash, 'OTP must be stored hashed');

        $data = $this->postJson('/api/v1/auth/verify-otp', ['phone' => '9876543210', 'otp' => $this->lastOtp(), 'device_name' => 'Pixel'])
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.gym_id', $gym->id)
            ->assertJsonPath('data.member.id', $member->id)
            ->json('data');

        $this->assertNotEmpty($data['token']);
        $this->assertSame(1, $member->tokens()->count());

        // OTP is single use.
        $this->postJson('/api/v1/auth/verify-otp', ['phone' => '9876543210', 'otp' => $this->lastOtp()])
            ->assertStatus(422)->assertJsonPath('code', 'OTP_NOT_FOUND');
    }

    public function test_login_reports_the_members_real_membership_state(): void
    {
        $gym = $this->gymOf($this->createGymOwner('State Gym'));
        $plan = $this->createPlan($gym);
        $member = $this->createMember($gym, ['phone' => '9123400001']);
        $this->inGym($gym, fn () => app(MembershipService::class)->assign($member, $plan, now()->toDateString()));
        $this->createMember($gym, ['phone' => '9123400002']);

        $login = $this->login('9123400001')['member'];
        $this->assertSame('active', $login['membership_state']);
        $this->assertSame('Monthly', $login['membership']['plan_name']);
        $this->assertSame(now()->toDateString(), $login['membership']['start_date']);
        $this->assertSame('none', $this->login('9123400002')['member']['membership_state']);
    }

    public function test_unknown_inactive_and_suspended_gym_numbers_cannot_log_in(): void
    {
        $owner = $this->createGymOwner();
        $gym = $this->gymOf($owner);
        $this->createMember($gym, ['phone' => '9000000001', 'status' => 'inactive']);
        $this->createMember($gym, ['phone' => '9000000002']);

        $this->postJson('/api/v1/auth/send-otp', ['phone' => '9999999999'])->assertNotFound()->assertJsonPath('code', 'NO_GYM_CONNECTED');
        $this->postJson('/api/v1/auth/send-otp', ['phone' => '9000000001'])->assertNotFound()->assertJsonPath('code', 'NO_GYM_CONNECTED');

        $gym->forceFill(['status' => 'suspended', 'is_active' => false])->save();
        $this->postJson('/api/v1/auth/send-otp', ['phone' => '9000000002'])->assertNotFound();
        $this->assertCount(0, $this->sent);
    }

    public function test_wrong_otp_attempts_are_counted_and_locked_out(): void
    {
        $this->createMember($this->gymOf($this->createGymOwner()), ['phone' => '9000000003']);
        $this->postJson('/api/v1/auth/send-otp', ['phone' => '9000000003'])->assertOk();
        $correct = $this->lastOtp();
        $wrong = $correct === '111111' ? '222222' : '111111';

        foreach (range(4, 0) as $left) {
            $this->postJson('/api/v1/auth/verify-otp', ['phone' => '9000000003', 'otp' => $wrong])
                ->assertStatus(422)->assertJsonPath('code', 'OTP_INVALID')->assertJsonPath('data.attempts_left', $left);
        }

        $this->assertSame(5, MemberOtp::sole()->attempts);

        // Even the correct code is refused after too many attempts.
        $this->postJson('/api/v1/auth/verify-otp', ['phone' => '9000000003', 'otp' => $correct])
            ->assertStatus(429)->assertJsonPath('code', 'OTP_ATTEMPTS_EXCEEDED');
    }

    public function test_expired_otp_and_resend_cooldown(): void
    {
        $this->createMember($this->gymOf($this->createGymOwner()), ['phone' => '9000000004']);

        $this->postJson('/api/v1/auth/send-otp', ['phone' => '9000000004'])->assertOk();
        $this->postJson('/api/v1/auth/send-otp', ['phone' => '9000000004'])->assertStatus(429)->assertJsonPath('code', 'OTP_COOLDOWN');

        $this->travel(6)->minutes();
        $this->postJson('/api/v1/auth/verify-otp', ['phone' => '9000000004', 'otp' => $this->lastOtp()])
            ->assertStatus(422)->assertJsonPath('code', 'OTP_EXPIRED');

        // A new OTP invalidates the previous one.
        $this->postJson('/api/v1/auth/send-otp', ['phone' => '9000000004'])->assertOk();
        $this->assertSame(1, MemberOtp::whereNull('consumed_at')->count());
    }

    public function test_phone_registered_at_multiple_gyms_requires_gym_choice(): void
    {
        $gymA = $this->gymOf($this->createGymOwner('Alpha Gym'));
        $gymB = $this->gymOf($this->createGymOwner('Beta Gym'));
        $this->createMember($gymA, ['phone' => '9000000005']);
        $memberB = $this->createMember($gymB, ['phone' => '09000000005']);

        $this->postJson('/api/v1/auth/send-otp', ['phone' => '9000000005'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'MULTIPLE_GYMS')
            ->assertJsonCount(2, 'data.gyms');

        $data = $this->login('9000000005', $gymB->id);
        $this->assertSame($gymB->id, $data['gym_id']);
        $this->assertSame($memberB->id, $data['member']['id']);
    }

    // ---- Branding -----------------------------------------------------------------

    public function test_branding_returns_gym_details_for_own_gym_only(): void
    {
        $gymA = $this->gymOf($this->createGymOwner('Alpha Gym'));
        $gymB = $this->gymOf($this->createGymOwner('Beta Gym'));
        $gymA->settings = array_replace($gymA->allSettings(), ['primary_color' => '#112233', 'tagline' => 'Lift more']);
        $gymA->save();
        $this->createMember($gymA, ['phone' => '9000000006']);

        $token = $this->login('9000000006')['token'];

        $this->getJson("/api/v1/gyms/{$gymA->id}/branding")->assertStatus(401)->assertJsonPath('code', 'UNAUTHENTICATED');

        $this->withToken($token)->getJson("/api/v1/gyms/{$gymA->id}/branding")
            ->assertOk()
            ->assertJsonPath('data.gym_id', $gymA->id)
            ->assertJsonPath('data.name', 'Alpha Gym')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.colors.primary', '#112233')
            ->assertJsonPath('data.tagline', 'Lift more')
            ->assertJsonStructure(['data' => ['logo_url', 'banner_url', 'description', 'contact', 'social', 'opening_hours', 'facilities', 'photos']]);

        $this->withToken($token)->getJson("/api/v1/gyms/{$gymB->id}/branding")->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
        $this->withToken($token)->getJson('/api/v1/gyms/99999/branding')->assertNotFound()->assertJsonPath('code', 'GYM_NOT_FOUND');
    }

    public function test_branding_reports_suspended_gym(): void
    {
        $gym = $this->gymOf($this->createGymOwner());
        $this->createMember($gym, ['phone' => '9000000007']);
        $token = $this->login('9000000007')['token'];

        $gym->forceFill(['status' => 'suspended', 'is_active' => false])->save();

        $this->withToken($token)->getJson("/api/v1/gyms/{$gym->id}/branding")
            ->assertForbidden()
            ->assertJsonPath('code', 'GYM_INACTIVE')
            ->assertJsonPath('data.gym_status', 'suspended')
            ->assertJsonPath('data.is_active', false);
    }

    // ---- Token lifecycle ------------------------------------------------------------

    public function test_logout_revokes_token_and_deactivated_members_are_signed_out(): void
    {
        $gym = $this->gymOf($this->createGymOwner());
        $member = $this->createMember($gym, ['phone' => '9000000008']);

        $token = $this->login('9000000008')['token'];
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertSame(0, $member->tokens()->count());

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson("/api/v1/gyms/{$gym->id}/branding")->assertStatus(401);

        // Member marked inactive by the gym after login.
        $this->travel(1)->minutes();
        $token = $this->login('9000000008')['token'];
        $member->forceFill(['status' => 'inactive'])->save();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson("/api/v1/gyms/{$gym->id}/branding")->assertForbidden()->assertJsonPath('code', 'MEMBER_INACTIVE');

        // Deleted members' tokens are revoked.
        $member->forceFill(['status' => 'active'])->save();
        $this->travel(1)->minutes();
        $token = $this->login('9000000008')['token'];
        $this->inGym($gym, fn () => app(MemberService::class)->delete(Member::find($member->id)));
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson("/api/v1/gyms/{$gym->id}/branding")->assertStatus(401);
    }
}
