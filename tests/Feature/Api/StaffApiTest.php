<?php

namespace Tests\Feature\Api;

use App\Contracts\SmsSender;
use App\Enums\Role;
use App\Mail\StaffDeletionRequestMail;
use App\Models\Attendance;
use App\Models\Gym;
use App\Models\Member;
use App\Models\StaffDeletionRequest;
use App\Models\StaffDevice;
use App\Models\User;
use App\Services\CheckInCodeService;
use App\Services\MembershipService;
use App\Services\StaffAuthService;
use App\Services\TrainerService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class StaffApiTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{phone:string, message:string}> */
    protected array $sent = [];

    protected User $owner;

    protected Gym $gym;

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

    /** @param  list<string>  $permissions */
    protected function trainerWith(array $permissions): User
    {
        $trainer = $this->createTrainer($this->gym);
        $this->inGym($this->gym, fn () => app(TrainerService::class)->setPermissions($trainer, $permissions));

        return $trainer->fresh();
    }

    protected function withPlan(Member $member, array $plan = []): void
    {
        $this->inGym($this->gym, fn () => app(MembershipService::class)->assign($member, $this->createPlan($this->gym, $plan), now()->toDateString()));
    }

    // ---- Auth ---------------------------------------------------------------------------------------

    public function test_password_login_returns_a_staff_token_with_profile_gym_and_permissions(): void
    {
        $this->postJson('/api/v1/admin/auth/login', ['email' => strtoupper($this->owner->email), 'password' => 'password', 'device_name' => 'Pixel'])
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.role', 'gym_admin')
            ->assertJsonPath('data.gym.id', $this->gym->id)
            ->assertJsonPath('data.gym.timezone', config('app.timezone'))
            ->assertJson(fn ($json) => $json->where('data.permissions', fn ($p) => collect($p)->contains('members.manage'))->etc());

        $this->postJson('/api/v1/admin/auth/login', ['email' => $this->owner->email, 'password' => 'wrong'])
            ->assertStatus(422)->assertJsonPath('code', 'INVALID_CREDENTIALS');
    }

    public function test_staff_and_member_tokens_only_work_on_their_own_api(): void
    {
        $token = $this->tokenFor($this->owner);
        $member = $this->createMember($this->gym);
        $memberToken = $member->createToken('member-app', ['member'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/me')->assertStatus(401)->assertJsonPath('code', 'UNAUTHENTICATED');
        $this->app['auth']->forgetGuards();
        $this->withToken($memberToken)->getJson('/api/v1/admin/me')->assertStatus(401)->assertJsonPath('code', 'UNAUTHENTICATED');
        $this->app['auth']->forgetGuards();
        $this->withToken($memberToken)->getJson('/api/v1/me')->assertOk();
    }

    public function test_otp_sign_in_sends_only_to_staff_and_answers_strangers_the_same_way(): void
    {
        $this->owner->forceFill(['login_phone' => '9811122233'])->save();

        $this->postJson('/api/v1/admin/auth/send-otp', ['identifier' => '+91 98111 22233'])->assertOk()->assertJsonPath('data.otp_length', 6);
        $this->assertCount(1, $this->sent);
        preg_match('/^(\d{6})/', $this->sent[0]['message'], $m);

        // A member's number gets the same answer, but no SMS.
        $this->createMember($this->gym, ['phone' => '9822233344']);
        $this->postJson('/api/v1/admin/auth/send-otp', ['identifier' => '9822233344'])->assertOk();
        $this->assertCount(1, $this->sent);

        $this->postJson('/api/v1/admin/auth/verify-otp', ['identifier' => '9811122233', 'otp' => '000000'])
            ->assertStatus(422)->assertJsonPath('code', 'OTP_INVALID');
        $this->postJson('/api/v1/admin/auth/verify-otp', ['identifier' => '9811122233', 'otp' => $m[1]])
            ->assertOk()->assertJsonPath('data.user.id', $this->owner->id);

        // A resend within the cooldown uses the shared code.
        $this->postJson('/api/v1/admin/auth/send-otp', ['identifier' => '9811122233'])->assertOk();
        $this->postJson('/api/v1/admin/auth/send-otp', ['identifier' => '9811122233'])->assertStatus(429)->assertJsonPath('code', 'OTP_TOO_MANY');
    }

    public function test_choosing_an_account_uses_a_single_use_selection_token(): void
    {
        $other = $this->createGymOwner('Second Gym');
        $service = app(StaffAuthService::class);
        $selection = $service->issueSelectionToken(new Collection([$this->owner, $other]));

        $this->postJson('/api/v1/admin/auth/select-account', ['selection_token' => $selection, 'account' => 'staff:999999'])
            ->assertStatus(422)->assertJsonPath('code', 'ACCOUNT_NOT_FOUND');
        $this->postJson('/api/v1/admin/auth/select-account', ['selection_token' => $selection, 'account' => 'staff:'.$other->id])
            ->assertOk()->assertJsonPath('data.gym.name', 'Second Gym');
        $this->postJson('/api/v1/admin/auth/select-account', ['selection_token' => $selection, 'account' => 'staff:'.$other->id])
            ->assertStatus(422)->assertJsonPath('code', 'SELECTION_EXPIRED');

        $this->assertSame(['Iron Den · Gym admin', 'Second Gym · Gym admin'], array_column($service->choices(new Collection([$this->owner, $other])), 'label'));
    }

    public function test_switched_off_staff_and_suspended_gyms_get_account_disabled(): void
    {
        $trainer = $this->trainerWith(['members.view']);
        $token = $this->tokenFor($trainer);

        $trainer->forceFill(['status' => 'inactive'])->save();
        $this->withToken($token)->getJson('/api/v1/admin/me')->assertStatus(403)->assertJsonPath('code', 'ACCOUNT_DISABLED');

        $this->app['auth']->forgetGuards();
        $ownerToken = $this->tokenFor($this->owner);
        $this->gym->forceFill(['status' => 'suspended'])->save();
        $this->app['auth']->forgetGuards();
        $this->withToken($ownerToken)->getJson('/api/v1/admin/dashboard')->assertStatus(403)->assertJsonPath('code', 'ACCOUNT_DISABLED');
    }

    public function test_logout_revokes_the_token_and_forgets_the_phone(): void
    {
        $token = $this->tokenFor($this->owner);
        $this->withToken($token)->postJson('/api/v1/admin/devices', ['token' => 'fcm-abc', 'platform' => 'android'])->assertOk();
        $this->assertSame(1, StaffDevice::withoutGlobalScopes()->count());

        $this->withToken($token)->postJson('/api/v1/admin/auth/logout')->assertOk();
        $this->assertSame(0, StaffDevice::withoutGlobalScopes()->count());
        $this->assertSame(0, $this->owner->tokens()->count());
    }

    public function test_devices_are_removed_by_token_in_the_body(): void
    {
        $this->asStaff($this->owner)->postJson('/api/v1/admin/devices', ['token' => 'fcm-1', 'platform' => 'ios', 'app_version' => '1.0.0'])->assertOk();
        $this->deleteJson('/api/v1/admin/devices', ['token' => 'fcm-1'])->assertOk();
        $this->assertSame(0, StaffDevice::withoutGlobalScopes()->count());
    }

    // ---- Account --------------------------------------------------------------------------------------

    public function test_profile_and_password_can_be_changed(): void
    {
        $this->asStaff($this->owner)->putJson('/api/v1/admin/me', ['name' => 'Ravi', 'email' => 'ravi@irongym.test', 'phone' => '9000011111'])
            ->assertOk()->assertJsonPath('data.user.email', 'ravi@irongym.test');

        $this->putJson('/api/v1/admin/me/password', ['current_password' => 'nope', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertStatus(422)->assertJsonValidationErrors('current_password');
        $this->putJson('/api/v1/admin/me/password', ['current_password' => 'password', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])->assertOk();

        $this->postJson('/api/v1/admin/auth/login', ['email' => 'ravi@irongym.test', 'password' => 'newpass123'])->assertOk();
    }

    public function test_login_phone_is_verified_with_an_otp(): void
    {
        $this->asStaff($this->owner)->postJson('/api/v1/admin/me/login-phone/send-otp', ['phone' => '98765 00011'])->assertOk();
        preg_match('/^(\d{6})/', end($this->sent)['message'], $m);

        $this->postJson('/api/v1/admin/me/login-phone/verify-otp', ['phone' => '9876500011', 'otp' => $m[1]])
            ->assertOk()->assertJsonPath('data.user.login_phone', '9876500011')->assertJsonPath('data.user.login_phone_verified', true);

        $this->deleteJson('/api/v1/admin/me/login-phone')->assertOk()->assertJsonPath('data.user.login_phone', null);
    }

    public function test_a_trainer_deletion_request_is_recorded_and_emailed_to_the_gym_and_super_admin(): void
    {
        Mail::fake();
        $super = User::factory()->create(['email' => 'boss@fitnix.test']);
        $super->assignRole(Role::SuperAdmin->value);
        $trainer = $this->trainerWith([]);

        $this->asStaff($trainer)->postJson('/api/v1/admin/me/delete-request', ['reason' => 'Leaving the gym'])
            ->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.handled_by', 'gym_admin');
        $this->postJson('/api/v1/admin/me/delete-request')->assertOk();

        $this->assertSame(1, StaffDeletionRequest::withoutGlobalScopes()->count());
        Mail::assertQueued(StaffDeletionRequestMail::class, fn ($mail) => $mail->hasTo($this->owner->email));
        Mail::assertQueued(StaffDeletionRequestMail::class, fn ($mail) => $mail->hasTo('boss@fitnix.test'));
        $this->assertTrue($trainer->fresh()->isActive(), 'Nothing is deleted automatically.');
    }

    // ---- Permissions and options ------------------------------------------------------------------------

    public function test_missing_permissions_return_forbidden(): void
    {
        $trainer = $this->trainerWith([]);

        $this->asStaff($trainer)->getJson('/api/v1/admin/members')->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
        $this->getJson('/api/v1/admin/options/plans')->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
        $this->getJson('/api/v1/admin/dashboard')->assertOk()->assertJsonPath('data.role', 'trainer');
    }

    public function test_option_lists_hold_active_plans_and_trainers_only(): void
    {
        $this->createPlan($this->gym, ['name' => 'Gold']);
        $this->createPlan($this->gym, ['name' => 'Old', 'is_active' => false]);
        $active = $this->createTrainer($this->gym);
        $off = $this->createTrainer($this->gym);
        $off->forceFill(['status' => 'inactive'])->save();

        $this->asStaff($this->owner)->getJson('/api/v1/admin/options/plans')
            ->assertOk()->assertJsonCount(1, 'data.plans')->assertJsonPath('data.plans.0.name', 'Gold')
            ->assertJsonPath('data.plans.0.duration_type', 'monthly')->assertJsonPath('data.plans.0.duration_days', 30);

        $this->getJson('/api/v1/admin/options/trainers')
            ->assertOk()->assertJsonCount(1, 'data.trainers')->assertJsonPath('data.trainers.0.id', $active->id)
            ->assertJson(fn ($json) => $json->where('data.trainers.0.photo_url', fn ($url) => str_starts_with($url, 'http'))->etc());
    }

    // ---- Dashboard ------------------------------------------------------------------------------------

    public function test_admin_and_trainer_dashboards(): void
    {
        $trainer = $this->trainerWith(['members.view']);
        $mine = $this->createMember($this->gym, [], ['trainer_id' => $trainer->id]);
        $this->withPlan($mine);
        $this->createMember($this->gym);

        $this->asStaff($this->owner)->getJson('/api/v1/admin/dashboard')
            ->assertOk()->assertJsonPath('data.role', 'gym_admin')->assertJsonPath('data.stats.total_members', 2)
            ->assertJsonCount(6, 'data.member_growth')->assertJsonCount(6, 'data.revenue');

        $this->app['auth']->forgetGuards();
        $this->asStaff($trainer)->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.stats', ['my_members' => 1, 'active_members' => 1, 'checked_in_today' => 0, 'inside_now' => 0, 'expiring_soon' => 0])
            ->assertJsonCount(1, 'data.members');
    }

    // ---- Members --------------------------------------------------------------------------------------

    public function test_member_list_filters_sorts_and_marks_todays_check_ins(): void
    {
        $zara = $this->createMember($this->gym, ['name' => 'Zara']);
        $amit = $this->createMember($this->gym, ['name' => 'Amit']);
        $none = $this->createMember($this->gym, ['name' => 'Neha']);
        $this->withPlan($zara, ['name' => 'Short', 'duration_days' => 10, 'duration_type' => 'custom']);
        $this->withPlan($amit, ['name' => 'Long', 'duration_days' => 300, 'duration_type' => 'custom']);
        $this->inGym($this->gym, fn () => Attendance::forceCreate(['gym_id' => $this->gym->id, 'member_id' => $amit->id, 'date' => today(), 'check_in_at' => now(), 'method' => 'manual']));

        $this->asStaff($this->owner)->getJson('/api/v1/admin/members?sort=name')
            ->assertOk()->assertJsonPath('data.members.*.name', ['Amit', 'Neha', 'Zara'])
            ->assertJsonPath('data.members.0.checked_in_today', true)
            ->assertJsonPath('data.members.0.membership.status', 'active')
            ->assertJsonPath('data.members.1.membership', null)
            ->assertJsonPath('data.pagination.total', 3);

        $this->getJson('/api/v1/admin/members?sort=expiring')->assertJsonPath('data.members.*.name', ['Zara', 'Amit', 'Neha']);
        $this->getJson('/api/v1/admin/members')->assertJsonPath('data.members.*.name', ['Neha', 'Amit', 'Zara']);
        $this->getJson('/api/v1/admin/members?membership=none')->assertJsonPath('data.members.*.id', [$none->id]);
        $this->getJson('/api/v1/admin/members?search=zar')->assertJsonPath('data.members.*.id', [$zara->id]);
        $this->assertNotNull($this->getJson('/api/v1/admin/members?search=Amit')->json('data.members.0.last_check_in_at'));
    }

    public function test_trainers_without_see_all_only_reach_their_own_members(): void
    {
        $trainer = $this->trainerWith(['members.view', 'members.manage']);
        $mine = $this->createMember($this->gym, [], ['trainer_id' => $trainer->id]);
        $other = $this->createMember($this->gym);

        $this->asStaff($trainer)->getJson('/api/v1/admin/members')->assertJsonPath('data.members.*.id', [$mine->id]);
        $this->getJson("/api/v1/admin/members/{$other->id}")->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $this->getJson("/api/v1/admin/members/{$mine->id}")->assertOk()->assertJsonPath('data.can.assign_trainer', false)->assertJsonPath('data.can.delete', false);

        // What they register stays theirs, and they cannot delete.
        $created = $this->postJson('/api/v1/admin/members', ['name' => 'New One', 'phone' => '9123400001', 'joining_date' => today()->toDateString(), 'status' => 'active', 'trainer_id' => null])
            ->assertCreated()->json('data');
        $this->assertSame($trainer->id, $created['trainer']['id']);
        $this->deleteJson("/api/v1/admin/members/{$mine->id}")->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_member_create_with_a_duplicate_phone_returns_phone_taken(): void
    {
        $existing = $this->createMember($this->gym, ['phone' => '98989 89898']);

        $this->asStaff($this->owner)->postJson('/api/v1/admin/members', ['name' => 'Copy', 'phone' => '+91 9898989898', 'joining_date' => today()->toDateString(), 'status' => 'active'])
            ->assertStatus(422)->assertJsonPath('code', 'PHONE_TAKEN')->assertJsonPath('data.member_id', $existing->id);
    }

    public function test_member_create_with_an_idempotency_key_runs_once(): void
    {
        Storage::fake('public');
        $key = (string) Str::uuid();
        $body = ['name' => 'Once', 'phone' => '9555500001', 'joining_date' => today()->toDateString(), 'status' => 'active'];

        $first = $this->asStaff($this->owner)->withHeader('Idempotency-Key', $key)->postJson('/api/v1/admin/members', $body)->assertCreated();
        $again = $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/admin/members', $body)->assertCreated()->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame($first->json('data.member.id'), $again->json('data.member.id'));
        $this->assertSame(1, Member::withoutGlobalScopes()->where('name', 'Once')->count());

        $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/admin/members', ['name' => 'Different'] + $body)
            ->assertStatus(422)->assertJsonPath('code', 'IDEMPOTENCY_KEY_REUSED');
        $this->withHeader('Idempotency-Key', 'not-a-uuid')->postJson('/api/v1/admin/members', $body)->assertStatus(422);
    }

    public function test_member_detail_update_photo_status_and_delete(): void
    {
        Storage::fake('public');
        $trainer = $this->createTrainer($this->gym);
        $member = $this->createMember($this->gym, ['name' => 'Kiran', 'phone' => '9444400001']);
        $this->withPlan($member);

        $this->asStaff($this->owner)->getJson("/api/v1/admin/members/{$member->id}")
            ->assertOk()->assertJsonPath('data.member.name', 'Kiran')->assertJsonPath('data.current_membership.status', 'active')
            ->assertJsonPath('data.can.edit', true)->assertJsonPath('data.can.delete', true)
            ->assertJsonPath('data.attendance_summary.checked_in_today', false);

        $this->putJson("/api/v1/admin/members/{$member->id}", ['name' => 'Kiran K', 'phone' => '9444400001', 'joining_date' => today()->toDateString(), 'status' => 'active', 'trainer_id' => $trainer->id, 'medical_notes' => 'Knee'])
            ->assertOk()->assertJsonPath('data.member.name', 'Kiran K')->assertJsonPath('data.trainer.id', $trainer->id)->assertJsonPath('data.member.medical_notes', 'Knee');

        $url = $this->post("/api/v1/admin/members/{$member->id}/photo", ['photo' => UploadedFile::fake()->image('me.jpg')], ['Accept' => 'application/json'])->assertOk()->json('data.photo_url');
        $this->assertStringContainsString('/storage/gyms/'.$this->gym->id.'/members/', $url);
        $this->deleteJson("/api/v1/admin/members/{$member->id}/photo")->assertOk();

        $this->postJson("/api/v1/admin/members/{$member->id}/status", ['status' => 'inactive'])->assertOk()->assertJsonPath('data.status', 'inactive');
        $this->postJson("/api/v1/admin/members/{$member->id}/trainer", ['trainer_id' => null])->assertOk()->assertJsonPath('data.trainer', null);
        $this->deleteJson("/api/v1/admin/members/{$member->id}")->assertOk();
        $this->assertSoftDeleted('members', ['id' => $member->id]);
    }

    public function test_other_gyms_members_are_not_found(): void
    {
        $otherGym = $this->gymOf($this->createGymOwner('Elsewhere'));
        $stranger = $this->createMember($otherGym);

        $this->asStaff($this->owner)->getJson("/api/v1/admin/members/{$stranger->id}")->assertStatus(404);
        $this->postJson('/api/v1/admin/attendance/check-in', ['member_id' => $stranger->id])->assertStatus(404);
    }

    // ---- Attendance -----------------------------------------------------------------------------------

    public function test_manual_check_in_and_out_with_error_codes(): void
    {
        $member = $this->createMember($this->gym, ['name' => 'Asha']);
        $noPlan = $this->createMember($this->gym);

        $this->asStaff($this->owner)->postJson('/api/v1/admin/attendance/check-in', ['member_id' => $noPlan->id])
            ->assertStatus(422)->assertJsonPath('code', 'NO_ACTIVE_MEMBERSHIP');

        $this->withPlan($member);
        $key = (string) Str::uuid();
        $id = $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/admin/attendance/check-in', ['member_id' => $member->id])
            ->assertCreated()->assertJsonPath('data.member.name', 'Asha')->assertJsonPath('data.method', 'manual')->json('data.id');
        $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/admin/attendance/check-in', ['member_id' => $member->id])->assertCreated()->assertJsonPath('data.id', $id);

        $this->withHeaders(['Idempotency-Key' => ''])->postJson('/api/v1/admin/attendance/check-in', ['member_id' => $member->id])
            ->assertStatus(409)->assertJsonPath('code', 'ALREADY_CHECKED_IN');
        $this->assertSame($this->owner->id, Attendance::withoutGlobalScopes()->find($id)->recorded_by);

        $this->postJson("/api/v1/admin/attendance/{$id}/check-out")->assertOk()->assertJson(fn ($json) => $json->whereType('data.check_out_at', 'string')->etc());
        $this->postJson("/api/v1/admin/attendance/{$id}/check-out")->assertStatus(409)->assertJsonPath('code', 'ALREADY_CHECKED_OUT');
    }

    public function test_todays_attendance_has_counts_search_and_pagination(): void
    {
        foreach (['Ravi', 'Rohit', 'Sonal', 'Tara', 'Uma', 'Vik'] as $i => $name) {
            $member = $this->createMember($this->gym, ['name' => $name]);
            $this->inGym($this->gym, fn () => Attendance::forceCreate(['gym_id' => $this->gym->id, 'member_id' => $member->id, 'date' => today(), 'check_in_at' => now()->subMinutes($i), 'check_out_at' => $i === 0 ? now() : null, 'method' => 'manual']));
        }

        $this->asStaff($this->owner)->getJson('/api/v1/admin/attendance/today?per_page=5')
            ->assertOk()->assertJsonPath('data.checked_in', 6)->assertJsonPath('data.inside_now', 5)
            ->assertJsonCount(5, 'data.list')->assertJsonPath('data.pagination.last_page', 2);

        $this->getJson('/api/v1/admin/attendance/today?search=ro')
            ->assertJsonPath('data.checked_in', 6)->assertJsonPath('data.list.*.member.name', ['Rohit']);
    }

    public function test_check_in_qr_has_an_expiry_in_screen_mode(): void
    {
        $this->asStaff($this->owner)->getJson('/api/v1/admin/check-in-qr')
            ->assertOk()->assertJsonPath('data.mode', 'printed')->assertJsonPath('data.expires_at', null);

        $this->gym->forceFill(['settings' => ['checkin' => ['qr' => CheckInCodeService::SCREEN]]])->save();

        $data = $this->getJson('/api/v1/admin/check-in-qr')->assertOk()->assertJsonPath('data.mode', 'screen')->assertJsonPath('data.refresh_seconds', 30)->json('data');
        $this->assertStringStartsWith('http', $data['payload']);
        $this->assertTrue(now()->lt($data['expires_at']) && now()->addSeconds(31)->gt($data['expires_at']));
    }
}
