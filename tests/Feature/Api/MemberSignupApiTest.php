<?php

namespace Tests\Feature\Api;

use App\Contracts\SmsSender;
use App\Enums\GymStatus;
use App\Livewire\Gym\Members\Show;
use App\Models\City;
use App\Models\Gym;
use App\Models\Member;
use App\Models\PlatformLead;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class MemberSignupApiTest extends TestCase
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

    /** Verify a phone through the sign-up OTP endpoints and return the registration token. */
    protected function verifiedToken(string $phone = '9811100001'): string
    {
        $this->postJson('/api/v1/auth/register/send-otp', ['phone' => $phone])->assertOk();

        return $this->postJson('/api/v1/auth/register/verify-otp', ['phone' => $phone, 'otp' => $this->lastOtp()])
            ->assertOk()->json('data.registration_token');
    }

    public function test_city_list_shows_only_live_cities_with_operational_gym_counts(): void
    {
        $pune = $this->gymOf($this->createGymOwner('Pune Gym'))->cityRecord;
        $suspended = $this->gymOf($this->createGymOwner('Closed Gym'));
        $suspended->forceFill(['status' => GymStatus::Suspended])->save();
        City::factory()->inactive()->create(['name' => 'Hidden Town']);

        $this->getJson('/api/v1/cities')->assertOk()
            ->assertJsonCount(1, 'data.cities')
            ->assertJsonPath('data.cities.0.id', $pune->id)
            ->assertJsonPath('data.cities.0.gyms_count', 1);
    }

    public function test_gym_search_is_limited_to_the_chosen_city(): void
    {
        $iron = $this->gymOf($this->createGymOwner('Iron Temple'));
        $this->gymOf($this->createGymOwner('Fit Hub'));
        $jaipur = City::factory()->create(['name' => 'Jaipur']);
        $elsewhere = $this->gymOf($this->createGymOwner('Iron Jaipur'));
        $elsewhere->forceFill(['city_id' => $jaipur->id, 'city' => 'Jaipur'])->save();

        $this->getJson("/api/v1/cities/{$iron->city_id}/gyms")->assertOk()->assertJsonCount(2, 'data.gyms');
        $this->getJson("/api/v1/cities/{$iron->city_id}/gyms?search=iron")->assertOk()
            ->assertJsonCount(1, 'data.gyms')
            ->assertJsonPath('data.gyms.0.gym_id', $iron->id)
            ->assertJsonPath('data.gyms.0.name', 'Iron Temple');
    }

    public function test_hidden_city_returns_not_found(): void
    {
        $city = $this->gymOf($this->createGymOwner())->cityRecord;
        $city->update(['is_active' => false]);

        $this->getJson("/api/v1/cities/{$city->id}/gyms")->assertNotFound()->assertJsonPath('code', 'CITY_NOT_FOUND');
    }

    public function test_full_sign_up_creates_a_pending_member_who_logs_in_after_approval(): void
    {
        $owner = $this->createGymOwner('Iron Temple');
        $gym = $this->gymOf($owner);
        $token = $this->verifiedToken('+91 98111 00001');

        $this->postJson('/api/v1/auth/register', [
            'registration_token' => $token,
            'city_id' => $gym->city_id,
            'gym_id' => $gym->id,
            'name' => 'App Joiner',
            'gender' => 'female',
            'fitness_goal' => 'Weight loss',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.gym.gym_id', $gym->id);

        $member = Member::forGym($gym)->sole();
        $this->assertSame('App Joiner', $member->name);
        $this->assertSame('pending', $member->status->value);
        $this->assertSame('9811100001', $member->phone_digits);
        $this->assertSame('Pune', $member->city);

        // Pending: no login, no lead, not billed.
        $this->postJson('/api/v1/auth/send-otp', ['phone' => '9811100001'])
            ->assertForbidden()->assertJsonPath('code', 'MEMBER_PENDING_APPROVAL');
        $this->assertSame(0, PlatformLead::count());
        $this->assertSame(0, app(BillingService::class)->billableMemberCount($gym, Carbon::today()->startOfMonth(), Carbon::today()->endOfMonth()));

        $this->actingAs($owner)->get(route('gym.dashboard'))->assertOk()->assertSee('waiting for your approval');

        // Staff approve, then the normal OTP login works.
        Livewire::actingAs($owner)->test(Show::class, ['member' => $member])->call('toggleStatus');
        $this->assertSame('active', $member->fresh()->status->value);

        $this->postJson('/api/v1/auth/send-otp', ['phone' => '9811100001'])->assertOk();
        $this->postJson('/api/v1/auth/verify-otp', ['phone' => '9811100001', 'otp' => $this->lastOtp()])
            ->assertOk()->assertJsonStructure(['data' => ['token']]);
    }

    public function test_gym_must_be_in_the_chosen_city(): void
    {
        $gym = $this->gymOf($this->createGymOwner());
        $otherCity = City::factory()->create();

        $this->postJson('/api/v1/auth/register', [
            'registration_token' => $this->verifiedToken(),
            'city_id' => $otherCity->id,
            'gym_id' => $gym->id,
            'name' => 'Wrong City',
        ])->assertNotFound()->assertJsonPath('code', 'GYM_NOT_FOUND');

        $this->assertSame(0, Member::forGym($gym)->count());
    }

    public function test_registration_token_is_single_use(): void
    {
        $gym = $this->gymOf($this->createGymOwner());
        $other = $this->gymOf($this->createGymOwner('Second Gym'));
        $token = $this->verifiedToken();

        $payload = ['registration_token' => $token, 'city_id' => $gym->city_id, 'name' => 'Once'];
        $this->postJson('/api/v1/auth/register', $payload + ['gym_id' => $gym->id])->assertCreated();
        $this->postJson('/api/v1/auth/register', $payload + ['gym_id' => $other->id])
            ->assertStatus(422)->assertJsonPath('code', 'REGISTRATION_TOKEN_INVALID');
    }

    public function test_expired_registration_token_is_rejected(): void
    {
        $gym = $this->gymOf($this->createGymOwner());
        $token = $this->verifiedToken();

        $this->travel(31)->minutes();

        $this->postJson('/api/v1/auth/register', ['registration_token' => $token, 'city_id' => $gym->city_id, 'gym_id' => $gym->id, 'name' => 'Late'])
            ->assertStatus(422)->assertJsonPath('code', 'REGISTRATION_TOKEN_INVALID');
    }

    public function test_phone_already_at_the_gym_is_rejected(): void
    {
        $gym = $this->gymOf($this->createGymOwner());
        $this->createMember($gym, ['phone' => '9811100001']);

        $this->postJson('/api/v1/auth/register', [
            'registration_token' => $this->verifiedToken('9811100001'),
            'city_id' => $gym->city_id,
            'gym_id' => $gym->id,
            'name' => 'Again',
        ])->assertStatus(409)->assertJsonPath('code', 'ALREADY_REGISTERED');
    }

    public function test_wrong_otp_counts_attempts_and_gives_no_token(): void
    {
        $this->postJson('/api/v1/auth/register/send-otp', ['phone' => '9811100001'])->assertOk();
        $wrong = $this->lastOtp() === '000000' ? '111111' : '000000';

        $this->postJson('/api/v1/auth/register/verify-otp', ['phone' => '9811100001', 'otp' => $wrong])
            ->assertStatus(422)
            ->assertJsonPath('code', 'OTP_INVALID')
            ->assertJsonPath('data.attempts_left', 4)
            ->assertJsonMissingPath('data.registration_token');
    }

    public function test_suspended_gym_cannot_be_chosen(): void
    {
        $gym = $this->gymOf($this->createGymOwner());
        $gym->forceFill(['status' => GymStatus::Suspended])->save();

        $this->postJson('/api/v1/auth/register', [
            'registration_token' => $this->verifiedToken(),
            'city_id' => $gym->city_id,
            'gym_id' => $gym->id,
            'name' => 'Closed',
        ])->assertNotFound()->assertJsonPath('code', 'GYM_NOT_FOUND');

        $this->assertFalse(Gym::find($gym->id)->isOperational());
    }
}
