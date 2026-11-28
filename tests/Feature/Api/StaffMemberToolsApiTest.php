<?php

namespace Tests\Feature\Api;

use App\Enums\FitnessPlanType;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\FitnessProgram;
use App\Models\Gym;
use App\Models\Member;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\BillingService;
use App\Services\MembershipService;
use App\Services\ProgramService;
use App\Services\PushNotificationService;
use App\Services\TrainerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaffMemberToolsApiTest extends TestCase
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
        $this->app['auth']->forgetGuards();
        $token = $this->postJson('/api/v1/admin/auth/login', ['email' => $user->email, 'password' => 'password'])->assertOk()->json('data.token');

        return $this->withToken($token);
    }

    /** @param  list<string>  $permissions */
    protected function trainerWith(array $permissions): User
    {
        $trainer = $this->createTrainer($this->gym);
        $this->inGym($this->gym, fn () => app(TrainerService::class)->setPermissions($trainer, $permissions));

        return $trainer->fresh();
    }

    protected function activeMember(array $attributes = []): Member
    {
        $member = $this->createMember($this->gym, $attributes);
        $plan = $this->createPlan($this->gym);
        $this->inGym($this->gym, fn () => app(MembershipService::class)->assign($member, $plan, now()->subDays(20)->toDateString()));

        return $member;
    }

    protected function visit(Member $member, Carbon $at): Attendance
    {
        Carbon::setTestNow($at);
        $attendance = $this->inGym($this->gym, fn () => app(AttendanceService::class)->checkIn($member));
        Carbon::setTestNow();

        return $attendance;
    }

    public function test_attendance_history_reports_and_streaks(): void
    {
        $asha = $this->activeMember(['name' => 'Asha']);
        $ravi = $this->activeMember(['name' => 'Ravi']);
        $today = Carbon::today();
        $this->visit($asha, $today->copy()->subDay()->setTime(18, 0));
        $this->visit($asha, $today->copy()->setTime(7, 0));
        $this->visit($ravi, $today->copy()->setTime(7, 30));
        $api = $this->asStaff($this->owner);

        $api->getJson('/api/v1/admin/attendance?from='.$today->copy()->subDays(6)->toDateString())->assertOk()
            ->assertJsonPath('data.totals.check_ins', 3)
            ->assertJsonPath('data.totals.unique_members', 2)
            ->assertJsonCount(3, 'data.attendance');
        $api->getJson("/api/v1/admin/attendance?member_id={$ravi->id}")->assertOk()->assertJsonPath('data.totals.check_ins', 1);
        $api->getJson('/api/v1/admin/attendance?from=2024-01-01&to='.$today->toDateString())->assertStatus(422)->assertJsonPath('code', 'RANGE_TOO_LONG');

        $api->getJson('/api/v1/admin/attendance/reports?view=daily')->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.busiest_hour.hour', 7)
            ->assertJsonPath('data.busiest_hour.check_ins', 2)
            ->assertJsonCount(19, 'data.by_hour');

        $monthly = $api->getJson('/api/v1/admin/attendance/reports?view=monthly&month='.$today->format('Y-m'))->assertOk();
        $this->assertSame($today->daysInMonth, count($monthly->json('data.by_day')));
        if ($today->day > 1) {
            $monthly->assertJsonPath('data.total', 3)->assertJsonPath('data.top_members.0.member.id', $asha->id)->assertJsonPath('data.top_members.0.visits', 2);
        }

        $api->getJson('/api/v1/admin/streaks')->assertOk()
            ->assertJsonPath('data.leaders.0.member.id', $asha->id)
            ->assertJsonPath('data.leaders.0.current_streak', 2)
            ->assertJsonPath('data.can_edit_rules', true);
        $api->postJson('/api/v1/admin/streaks/recalculate')->assertOk()->assertJsonPath('data.recalculated', 2);

        // Trainers: history only for their own members; reports need their own permission.
        $trainer = $this->trainerWith(['members.view', 'attendance.manage']);
        $ravi->forceFill(['trainer_id' => $trainer->id])->save();
        $trainerApi = $this->asStaff($trainer);
        $trainerApi->getJson('/api/v1/admin/attendance?from='.$today->copy()->subDays(6)->toDateString())->assertOk()->assertJsonPath('data.totals.check_ins', 1);
        $trainerApi->getJson("/api/v1/admin/attendance?member_id={$asha->id}")->assertNotFound();
        $trainerApi->getJson('/api/v1/admin/attendance/reports?view=daily')->assertForbidden();
    }

    public function test_weight_is_recorded_by_staff_and_shows_on_the_tracking_lists(): void
    {
        $member = $this->createMember($this->gym);
        $api = $this->asStaff($this->owner);

        $api->getJson('/api/v1/admin/progress')->assertOk()->assertJsonPath('data.stats.due', 1)->assertJsonPath('data.rows.0.member.id', $member->id);

        $api->postJson("/api/v1/admin/members/{$member->id}/weight", ['weight_kg' => 10])->assertStatus(422);
        $logId = $api->postJson("/api/v1/admin/members/{$member->id}/weight", ['weight_kg' => 82.5, 'measured_on' => now()->subDays(3)->toDateString()])
            ->assertCreated()->assertJsonPath('data.goal_achieved', false)->json('data.history.0.id');

        $api->putJson("/api/v1/admin/members/{$member->id}/weight-goal", ['target_weight_kg' => 78])->assertOk()->assertJsonPath('data.goal.target_weight_kg', 78);
        $api->getJson('/api/v1/admin/progress?view=near')->assertOk()->assertJsonPath('data.rows.0.member.id', $member->id);

        $api->postJson("/api/v1/admin/members/{$member->id}/weight", ['weight_kg' => 77.8])->assertCreated()->assertJsonPath('data.goal_achieved', true);
        $api->getJson('/api/v1/admin/progress?view=achieved')->assertOk()->assertJsonPath('data.rows.0.target_weight_kg', 78);

        if ($logId) {
            $api->deleteJson("/api/v1/admin/members/{$member->id}/weight/{$logId}")->assertOk();
        }
        $api->deleteJson("/api/v1/admin/members/{$member->id}/weight/999999")->assertNotFound();
    }

    public function test_measurements_progress_photos_and_ai_access_for_staff(): void
    {
        Storage::fake('local');
        $member = $this->createMember($this->gym);
        $api = $this->asStaff($this->owner);

        $api->postJson("/api/v1/admin/members/{$member->id}/measurements", ['waist_cm' => 84])->assertCreated()
            ->assertJsonPath('data.measurements.0.waist_cm', 84)
            ->assertJsonPath('data.measurements.0.source', 'staff');

        $photo = $api->post("/api/v1/admin/members/{$member->id}/progress-photos", ['photo' => UploadedFile::fake()->image('front.jpg', 400, 600), 'pose' => 'front'], ['Accept' => 'application/json'])
            ->assertCreated()->json('data.photos.0');
        $this->assertStringContainsString("/api/v1/admin/members/{$member->id}/progress-photos/{$photo['id']}/image", $photo['image_url']);
        $api->get($photo['image_url'])->assertOk();

        $api->getJson("/api/v1/admin/members/{$member->id}/ai")->assertOk()
            ->assertJsonPath('data.ai.ai_access', false)
            ->assertJsonPath('data.ai.can_toggle_access', true)
            ->assertJsonCount(1, 'data.photos');

        $api->postJson("/api/v1/admin/members/{$member->id}/ai/access")->assertOk()->assertJsonPath('data.ai_access', true);
        $api->deleteJson("/api/v1/admin/members/{$member->id}/progress-photos/{$photo['id']}")->assertOk()->assertJsonCount(0, 'data.photos');

        $trainer = $this->trainerWith(['members.view', 'members.all', 'ai.view']);
        $this->asStaff($trainer)->postJson("/api/v1/admin/members/{$member->id}/ai/access")->assertForbidden();
        $this->asStaff($this->trainerWith(['members.view', 'members.all']))->getJson("/api/v1/admin/members/{$member->id}/ai")->assertForbidden();
    }

    public function test_programs_are_assigned_and_stopped(): void
    {
        $this->inGym($this->gym, fn () => app(ProgramService::class)->importDefaults(FitnessPlanType::Workout));
        $program = $this->inGym($this->gym, fn () => FitnessProgram::ofType('workout')->active()->first());
        $member = $this->createMember($this->gym);
        $api = $this->asStaff($this->owner);

        $api->getJson('/api/v1/admin/programs?type=workout')->assertOk()->assertJsonPath('data.type', 'workout')->assertJsonPath('data.programs.0.type', 'workout');
        $api->getJson('/api/v1/admin/programs')->assertOk()->assertJsonPath('data.type', null);

        $assignmentId = $api->postJson("/api/v1/admin/members/{$member->id}/programs", ['program_id' => $program->id])->assertCreated()
            ->assertJsonPath('data.workout_program.program.id', $program->id)
            ->assertJsonPath('data.can_assign', true)
            ->json('data.workout_program.id');

        $api->postJson("/api/v1/admin/members/{$member->id}/programs", ['program_id' => $program->id, 'start_date' => now()->subDays(10)->toDateString()])
            ->assertStatus(422)->assertJsonValidationErrors(['start_date'], 'errors');

        $api->postJson("/api/v1/admin/members/{$member->id}/programs/{$assignmentId}/stop")->assertOk()
            ->assertJsonPath('data.workout_program', null)
            ->assertJsonPath('data.history.0.state', 'stopped');
        $api->postJson("/api/v1/admin/members/{$member->id}/programs/{$assignmentId}/stop")->assertStatus(409)->assertJsonPath('code', 'ALREADY_STOPPED');

        // A trainer with content rights only for their own members.
        $trainer = $this->trainerWith(['members.view', 'members.all', 'content.custom']);
        $this->asStaff($trainer)->postJson("/api/v1/admin/members/{$member->id}/programs", ['program_id' => $program->id])->assertForbidden();
    }

    public function test_custom_notifications_are_submitted_then_sent_after_approval(): void
    {
        $this->activeMember();
        $api = $this->asStaff($this->owner);

        $api->getJson('/api/v1/admin/announcements/audience-count?audience=active')->assertOk()->assertJsonPath('data.members', 1);
        $api->postJson('/api/v1/admin/announcements', ['title' => 'Hi {nickname}', 'body' => 'Holiday'])->assertStatus(422)->assertJsonValidationErrors(['title'], 'errors');
        $api->postJson('/api/v1/admin/announcements', ['title' => 'Diwali hours', 'body' => 'Open 6-10 for {first_name}', 'audience' => 'trainer'])
            ->assertStatus(422)->assertJsonValidationErrors(['trainer_id'], 'errors');

        $id = $api->postJson('/api/v1/admin/announcements', ['title' => 'Diwali hours', 'body' => 'Open 6-10, {first_name}!'])->assertCreated()
            ->assertJsonPath('data.announcement.status', Announcement::PENDING)
            ->assertJsonPath('data.announcement.can_send', false)
            ->json('data.announcement.id');

        $api->postJson("/api/v1/admin/announcements/{$id}/send")->assertStatus(409)->assertJsonPath('code', 'NOT_APPROVED');

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');
        $this->inGym($this->gym, fn () => app(PushNotificationService::class)->approve(Announcement::find($id), $superAdmin));

        $api->postJson("/api/v1/admin/announcements/{$id}/send", ['when' => 'later', 'scheduled_at' => now()->subDay()->toDateTimeString()])
            ->assertStatus(422)->assertJsonValidationErrors(['scheduled_at'], 'errors');
        $api->postJson("/api/v1/admin/announcements/{$id}/send", ['when' => 'later', 'scheduled_at' => now()->addDays(2)->setTime(12, 0)->toDateTimeString()])
            ->assertOk()->assertJsonPath('data.announcement.status', Announcement::SCHEDULED);
        $api->postJson("/api/v1/admin/announcements/{$id}/cancel")->assertOk()->assertJsonPath('data.announcement.status', Announcement::APPROVED);

        $api->getJson('/api/v1/admin/announcements')->assertOk()->assertJsonPath('data.announcements.0.id', $id)->assertJsonPath('data.sees_all_members', true);

        // A trainer only sees their own notifications.
        $this->asStaff($this->trainerWith(['members.view', 'announcements.send']))->getJson("/api/v1/admin/announcements/{$id}")->assertNotFound();
    }

    public function test_automatic_notifications_are_switched_by_gym_admins(): void
    {
        $api = $this->asStaff($this->owner);
        $types = $api->getJson('/api/v1/admin/automatic-notifications')->assertOk()->json('data.types');
        $key = $types[0]['key'];

        $api->postJson("/api/v1/admin/automatic-notifications/{$key}/toggle", ['enabled' => false])->assertOk()->assertJsonPath('data.types.0.gym_enabled', false);
        $api->putJson("/api/v1/admin/automatic-notifications/{$key}", ['enabled' => true, 'time' => '09:00'])->assertOk()
            ->assertJsonPath('data.types.0.time', '09:00')->assertJsonPath('data.types.0.customised', true);
        $api->postJson("/api/v1/admin/automatic-notifications/{$key}/reset")->assertOk()->assertJsonPath('data.types.0.customised', false);
        $api->postJson('/api/v1/admin/automatic-notifications/pause', ['paused' => true])->assertOk()->assertJsonPath('data.paused', true)->assertJsonPath('data.types.0.enabled', false);
        $api->postJson('/api/v1/admin/automatic-notifications/unknown/toggle')->assertNotFound();

        $this->asStaff($this->trainerWith(['members.view', 'announcements.send']))->getJson('/api/v1/admin/automatic-notifications')->assertForbidden();
    }

    public function test_member_qr_devices_and_import(): void
    {
        $member = $this->createMember($this->gym);
        $api = $this->asStaff($this->owner);

        $qr = $api->getJson("/api/v1/admin/members/{$member->id}/qr")->assertOk();
        $this->assertStringContainsString($member->qr_token, $qr->json('data.payload'));
        $this->assertStringContainsString('<svg', $qr->json('data.svg'));

        $api->postJson("/api/v1/admin/members/{$member->id}/devices/test")->assertStatus(409)->assertJsonPath('code', 'NO_DEVICES');
        $device = $this->inGym($this->gym, fn () => app(PushNotificationService::class)->registerDevice($member, str_repeat('t', 40), 'android', '1.0.0', 'Pixel'));
        $api->getJson("/api/v1/admin/members/{$member->id}/devices")->assertOk()
            ->assertJsonPath('data.devices.0.device_name', 'Pixel')
            ->assertJsonMissingPath('data.devices.0.token');
        $api->postJson("/api/v1/admin/members/{$member->id}/devices/test")->assertOk();
        $api->deleteJson("/api/v1/admin/members/{$member->id}/devices/{$device->id}")->assertOk()->assertJsonCount(0, 'data.devices');

        $api->getJson('/api/v1/admin/members/import')->assertOk()->assertJsonPath('data.required', ['name', 'phone']);
        $csv = "name,phone,email\nRahul,9876543210,rahul@example.test\n,123,\n";
        $api->post('/api/v1/admin/members/import', ['file' => UploadedFile::fake()->createWithContent('members.csv', $csv)], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('data.imported', 1)->assertJsonPath('data.skipped', 1);

        $this->asStaff($this->trainerWith(['members.view', 'members.all']))->getJson("/api/v1/admin/members/{$member->id}/devices")->assertForbidden();
    }

    public function test_ai_coach_settings_and_fitnix_billing(): void
    {
        $api = $this->asStaff($this->owner);
        $plan = $this->createPlan($this->gym);

        $api->getJson('/api/v1/admin/ai-coach')->assertOk()->assertJsonPath('data.settings.eligibility', 'all');
        $api->putJson('/api/v1/admin/ai-coach', ['eligibility' => 'plans'])->assertStatus(422)->assertJsonValidationErrors(['plan_ids'], 'errors');
        $api->putJson('/api/v1/admin/ai-coach', ['enabled' => true, 'eligibility' => 'plans', 'plan_ids' => [$plan->id], 'member_limit' => 2])->assertOk()
            ->assertJsonPath('data.settings.enabled', true)
            ->assertJsonPath('data.settings.plan_ids', [$plan->id])
            ->assertJsonPath('data.settings.member_limit', 2);
        $api->putJson('/api/v1/admin/ai-coach', ['member_limit' => 999])->assertStatus(422);

        $this->activeMember();
        $invoice = app(BillingService::class)->generateInvoice($this->gym, Carbon::today()->startOfMonth()->subMonth());

        $api->getJson('/api/v1/admin/billing')->assertOk()
            ->assertJsonPath('data.invoices.0.id', $invoice->id)
            ->assertJsonPath('data.invoices.0.invoice_number', $invoice->invoice_number);

        $url = $api->getJson("/api/v1/admin/billing/invoices/{$invoice->id}/document")->assertOk()->json('data.url');
        $this->get($url)->assertOk()->assertSee($invoice->invoice_number);

        $other = app(BillingService::class)->generateInvoice($this->gymOf($this->createGymOwner('Other')), Carbon::today()->startOfMonth()->subMonth());
        $api->getJson("/api/v1/admin/billing/invoices/{$other->id}/document")->assertNotFound();

        $trainer = $this->trainerWith(['members.view']);
        $this->asStaff($trainer)->getJson('/api/v1/admin/billing')->assertForbidden();
        $this->asStaff($trainer)->getJson('/api/v1/admin/ai-coach')->assertForbidden();
    }
}
