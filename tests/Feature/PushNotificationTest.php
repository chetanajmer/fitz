<?php

namespace Tests\Feature;

use App\Enums\FitnessPlanType;
use App\Livewire\Gym\Announcements;
use App\Livewire\Gym\Members\MemberDevices;
use App\Livewire\Platform\Notifications as PlatformNotifications;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\FitnessProgram;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberDevice;
use App\Models\MemberNotification;
use App\Models\Scopes\GymScope;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\NotificationSettings;
use App\Services\PaymentService;
use App\Services\ProgramService;
use App\Services\Push\FcmClient;
use App\Services\Push\PushMessage;
use App\Services\PushNotificationService;
use App\Services\StreakService;
use App\Services\WeightTrackingService;
use App\Support\SystemHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Gym $gym;

    protected FakeFcmClient $fcm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-23 09:00')); // a Monday
        $this->owner = $this->createGymOwner('Push Gym');
        $this->gym = $this->gymOf($this->owner);
        $this->fcm = new FakeFcmClient;
        $this->app->instance(FcmClient::class, $this->fcm);
    }

    protected function withPhone(Member $member, string $token): Member
    {
        $this->inGym(Gym::find($member->gym_id), fn () => app(PushNotificationService::class)->registerDevice($member, $token, 'android'));

        return $member;
    }

    protected function memberToken(Member $member): string
    {
        $this->app['auth']->forgetGuards();

        return $member->createToken('app', ['member'])->plainTextToken;
    }

    protected function superAdmin(): User
    {
        $admin = User::factory()->create(['name' => 'Platform Boss']);
        $admin->assignRole('super_admin');

        return $admin;
    }

    protected function expectValidationError(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected a validation error.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
    }

    /** @return array<string, list<string>> first letter of the token => titles pushed */
    protected function pushedByPhone(): array
    {
        return collect($this->fcm->sent)->groupBy(fn ($s) => $s['token'][0])->map(fn ($rows) => $rows->pluck('title')->all())->all();
    }

    public function test_app_registers_its_phone_on_launch_and_reads_the_notification_list(): void
    {
        $member = $this->createMember($this->gym, ['name' => 'Riya']);
        $token = $this->memberToken($member);
        $fcmToken = str_repeat('a', 300); // FCM tokens can be long

        $this->withToken($token)->postJson('/api/v1/me/devices', ['token' => $fcmToken, 'platform' => 'android', 'app_version' => '1.0.0', 'device_name' => 'Pixel 7 (android)'])
            ->assertOk()->assertExactJson(['success' => true, 'message' => 'Device registered.', 'data' => ['registered' => true]]);
        $this->withToken($token)->postJson('/api/v1/me/devices', ['token' => $fcmToken, 'platform' => 'android'])->assertOk();
        $this->assertSame(1, MemberDevice::withoutGlobalScope(GymScope::class)->count());

        $this->withToken($token)->postJson('/api/v1/me/devices', ['token' => 'x', 'platform' => 'windows'])
            ->assertUnprocessable()->assertJsonPath('success', false);

        // A payment is pushed (channel "membership", screen "payments") and listed in the app.
        $this->actingAs($this->owner);
        $this->inGym($this->gym, function () use ($member) {
            $membership = app(MembershipService::class)->assign($member, $this->createPlan($this->gym));
            app(PaymentService::class)->record($membership, 400, 'cash');
        });
        $this->assertCount(1, $this->fcm->sent);
        $push = $this->fcm->sent[0];
        $this->assertSame([$fcmToken, 'Payment received', 'membership'], [$push['token'], $push['title'], $push['channel']]);
        $this->assertSame('payment_received', $push['data']['type']);
        $this->assertSame('payments', $push['data']['screen']);
        $this->assertSame($this->gym->id, $push['data']['gym_id']);
        $this->assertStringContainsString('Push Gym', $push['body']);

        $this->app['auth']->forgetGuards();
        $list = $this->withToken($token)->getJson('/api/v1/me/notifications')->assertOk()
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonPath('data.notifications.0.type', 'payment_received')
            ->assertJsonPath('data.notifications.0.data.screen', 'payments')
            ->assertJsonPath('data.notifications.0.screen', 'payments')
            ->assertJsonPath('data.notifications.0.image_url', null)
            ->assertJsonPath('data.notifications.0.read_at', null)
            ->assertJsonPath('data.notifications.0.created_at', '2026-11-23T09:00:00+05:30')
            ->assertJsonPath('data.notifications.0.is_read', false);
        $id = $list->json('data.notifications.0.id');

        $this->withToken($token)->postJson("/api/v1/me/notifications/{$id}/read")->assertOk()->assertJsonPath('data.unread_count', 0);
        $this->withToken($token)->postJson('/api/v1/me/notifications/999999/read')->assertNotFound();
        $this->withToken($token)->postJson('/api/v1/me/notifications/read-all')->assertOk();

        // Settings: reminders can be switched off; account updates cannot.
        $this->withToken($token)->putJson('/api/v1/me/notification-settings', ['reminders' => false])->assertOk()
            ->assertJsonPath('data.settings.reminders', false)->assertJsonPath('data.settings.announcements', true);
        $this->assertFalse(app(PushNotificationService::class)->wantsPush($member->fresh(), 'streak_at_risk'));
        $this->assertTrue(app(PushNotificationService::class)->wantsPush($member->fresh(), 'payment_received'));

        // Logout removes the phone.
        $this->withToken($token)->deleteJson('/api/v1/me/devices', ['token' => $fcmToken])->assertOk()->assertJsonPath('message', 'Device removed.');
        $this->assertSame(0, MemberDevice::withoutGlobalScope(GymScope::class)->count());
    }

    public function test_a_phone_moves_to_the_member_who_signed_in_last_and_delete_is_scoped_to_the_owner(): void
    {
        $otherGym = $this->gymOf($this->createGymOwner('Other Gym'));
        $first = $this->createMember($this->gym);
        $second = $this->createMember($otherGym);
        $shared = str_repeat('b', 40);

        $this->withToken($this->memberToken($first))->postJson('/api/v1/me/devices', ['token' => $shared, 'platform' => 'ios'])->assertOk();
        $this->withToken($this->memberToken($second))->postJson('/api/v1/me/devices', ['token' => $shared, 'platform' => 'ios'])->assertOk();

        $device = MemberDevice::withoutGlobalScope(GymScope::class)->sole();
        $this->assertSame([$second->id, $otherGym->id], [$device->member_id, $device->gym_id]);

        // The previous owner cannot remove it (still answers 200).
        $this->withToken($this->memberToken($first))->deleteJson('/api/v1/me/devices', ['token' => $shared])->assertOk();
        $this->assertSame(1, MemberDevice::withoutGlobalScope(GymScope::class)->count());

        // Logout with device_token removes it for the current owner.
        $this->withToken($this->memberToken($second))->postJson('/api/v1/auth/logout', ['device_token' => $shared])->assertOk();
        $this->assertSame(0, MemberDevice::withoutGlobalScope(GymScope::class)->count());
    }

    public function test_custom_notification_needs_super_admin_approval_and_reaches_only_own_members(): void
    {
        Storage::fake('public');
        $otherGym = $this->gymOf($this->createGymOwner('Other Gym'));
        $active = $this->withPhone($this->createMember($this->gym, ['name' => 'Asha Verma']), str_repeat('c', 40));
        $noPlan = $this->withPhone($this->createMember($this->gym, ['name' => 'No Plan']), str_repeat('d', 40));
        $noApp = $this->createMember($this->gym, ['name' => 'No App']);
        $outsider = $this->withPhone($this->createMember($otherGym), str_repeat('e', 40));
        $this->actingAs($this->owner);
        $this->inGym($this->gym, function () use ($active, $noApp) {
            app(MembershipService::class)->assign($active, $this->createPlan($this->gym));
            app(MembershipService::class)->assign($noApp, $this->createPlan($this->gym));
        });
        $this->fcm->sent = [];

        // Only gym name and member name can be personalised.
        Livewire::test(Announcements::class)
            ->set('title', 'Pay {amount} now')->set('body', 'x')->call('submit')->assertHasErrors('title');

        Livewire::test(Announcements::class)
            ->assertSet('audience', 'active')
            ->set('title', 'Closed on Sunday')->set('body', 'Hi {first_name}, {gym_name} is closed this Sunday.')
            ->set('image', UploadedFile::fake()->image('banner.webp', 600, 300))
            ->assertSeeHtml('<strong>2</strong> members')
            ->call('submit')->assertHasNoErrors()
            ->assertSee('Waiting for approval');

        $announcement = $this->inGym($this->gym, fn () => Announcement::sole());
        $this->assertSame(Announcement::PENDING, $announcement->status);
        $this->assertSame([], $this->fcm->sent);

        // Cannot be sent before approval.
        $this->expectValidationError(fn () => $this->inGym($this->gym, fn () => app(PushNotificationService::class)->send($announcement)));

        // Super Admin approves.
        $admin = $this->superAdmin();
        $this->actingAs($admin);
        Livewire::test(PlatformNotifications::class)->assertSee('Closed on Sunday')->assertSee('Push Gym')->call('approve', $announcement->id);
        $this->assertSame(Announcement::APPROVED, $announcement->fresh()->status);
        $this->assertSame($admin->id, $announcement->fresh()->reviewed_by);

        // The gym sends it.
        $this->actingAs($this->owner);
        Livewire::test(Announcements::class)->call('openSend', $announcement->id)->call('closeSend')->assertSet('sendingId', null)
            ->call('openSend', $announcement->id)->set('when', 'now')->call('send')->assertHasNoErrors();

        $announcement->refresh();
        $this->assertSame([Announcement::SENT, 2, 1], [$announcement->status, $announcement->recipients_count, $announcement->pushed_count]);
        Storage::disk('public')->assertExists($announcement->image_path);
        $this->assertStringStartsWith('gyms/'.$this->gym->id.'/notifications/', $announcement->image_path);
        $this->assertSame($announcement->imageUrl(), $this->inGym($this->gym, fn () => MemberNotification::where('member_id', $active->id)->value('image_url')));

        $this->assertSame([str_repeat('c', 40)], array_column($this->fcm->sent, 'token'));
        $push = $this->fcm->sent[0];
        $this->assertSame(['Closed on Sunday', 'Hi Asha, Push Gym is closed this Sunday.'], [$push['title'], $push['body']]);
        $this->assertSame(['announcements', 'gym', 'announcement'], [$push['channel'], $push['data']['screen'], $push['data']['type']]);
        $this->assertSame($announcement->imageUrl(), $push['image']);

        $this->assertSame(1, $this->inGym($this->gym, fn () => MemberNotification::where('member_id', $noApp->id)->count()));
        $this->assertSame(0, $this->inGym($this->gym, fn () => MemberNotification::where('member_id', $noPlan->id)->count()));
        $this->assertSame(0, MemberNotification::withoutGlobalScope(GymScope::class)->where('member_id', $outsider->id)->count());

        $this->get(route('gym.announcements'))->assertOk()->assertSee('Closed on Sunday')->assertSee('1 on phone');
    }

    public function test_rejected_notifications_show_the_reason_and_only_super_admins_review(): void
    {
        $this->createMember($this->gym);
        $this->actingAs($this->owner);
        $announcement = $this->inGym($this->gym, fn () => app(PushNotificationService::class)->submit($this->owner, ['title' => 'BIG SALE!!!', 'body' => 'Click here', 'audience' => 'all']));

        $this->get(route('platform.notifications'))->assertForbidden();

        $this->actingAs($this->superAdmin());
        Livewire::test(PlatformNotifications::class)
            ->call('startReject', $announcement->id)->call('cancelReject')->assertSet('rejectingId', null)
            ->call('startReject', $announcement->id)->set('reason', '')->call('reject')->assertHasErrors('reason')
            ->set('reason', 'Please avoid capitals and links.')->call('reject')->assertHasNoErrors();
        $this->assertSame(Announcement::REJECTED, $announcement->fresh()->status);

        $this->actingAs($this->owner);
        $this->get(route('gym.announcements'))->assertSee('Not approved')->assertSee('Please avoid capitals and links.');
        $this->expectValidationError(fn () => $this->inGym($this->gym, fn () => app(PushNotificationService::class)->send($announcement->fresh())));
    }

    public function test_trainers_need_the_permission_and_only_reach_their_members(): void
    {
        $trainer = $this->createTrainer($this->gym);
        $mine = $this->withPhone($this->createMember($this->gym, [], ['trainer_id' => $trainer->id]), str_repeat('f', 40));
        $this->withPhone($this->createMember($this->gym), str_repeat('g', 40));

        $this->actingAs($trainer);
        $this->get(route('gym.announcements'))->assertForbidden();

        $trainer->givePermissionTo('announcements.send');
        $this->get(route('gym.announcements'))->assertOk()->assertSee('members assigned to you');

        Livewire::test(Announcements::class)
            ->set('title', 'Leg day')->set('body', 'Bring your shoes.')
            ->set('audience', 'all') // ignored: trainers without "see all members" reach only their own
            ->call('submit')->assertHasNoErrors();

        $announcement = $this->inGym($this->gym, fn () => Announcement::sole());
        $this->assertSame('trainer', $announcement->audience);
        $this->inGym($this->gym, function () use ($announcement) {
            app(PushNotificationService::class)->approve($announcement, $this->superAdmin());
            app(PushNotificationService::class)->send($announcement->fresh());
        });

        $this->assertSame([str_repeat('f', 40)], array_column($this->fcm->sent, 'token'));
        $this->assertSame(1, $this->inGym($this->gym, fn () => MemberNotification::where('member_id', $mine->id)->count()));

        Livewire::test(Announcements::class)->call('saveAutomatic', 'weigh_in_due')->assertForbidden();
    }

    public function test_sending_respects_quiet_hours_the_daily_limit_and_can_be_scheduled_or_cancelled(): void
    {
        $this->withPhone($this->createMember($this->gym), str_repeat('h', 40));
        $this->actingAs($this->owner);
        $service = app(PushNotificationService::class);
        $admin = $this->superAdmin();

        $approved = fn (string $title) => $this->inGym($this->gym, function () use ($service, $admin, $title) {
            $a = $service->submit($this->owner, ['title' => $title, 'body' => 'x', 'audience' => 'all']);
            $service->approve($a, $admin);

            return $a->fresh();
        });

        // Scheduled during quiet hours -> refused; at 18:00 -> fine; cancelling keeps it approved.
        $first = $approved('New batch');

        // A time that already passed gets a clear message (the current minute counts as now).
        try {
            $this->inGym($this->gym, fn () => $service->send($first, '2026-11-23 08:30'));
            $this->fail('Expected a validation error.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('already passed', $e->errors()['scheduledAt'][0]);
        }
        $this->travelTo(Carbon::parse('2026-11-23 09:00:40'));
        $this->assertSame(Announcement::APPROVED, $first->fresh()->status);
        $this->expectValidationError(fn () => $this->inGym($this->gym, fn () => $service->send($first, '2026-11-23 22:30')));
        $this->inGym($this->gym, fn () => $service->send($first, '2026-11-23 18:00'));
        $this->assertSame(Announcement::SCHEDULED, $first->fresh()->status);
        $this->inGym($this->gym, fn () => $service->cancel($first->fresh()));
        $this->assertSame(Announcement::APPROVED, $first->fresh()->status);
        $this->inGym($this->gym, fn () => $service->send($first->fresh(), '2026-11-23 18:00'));

        // 3 a day.
        $this->inGym($this->gym, fn () => $service->send($approved('Two')));
        $this->inGym($this->gym, fn () => $service->send($approved('Three')));
        $fourth = $approved('Four');
        $this->expectValidationError(fn () => $this->inGym($this->gym, fn () => $service->send($fourth)));

        $this->fcm->sent = [];
        $this->travelTo(Carbon::parse('2026-11-23 18:01'));
        $this->artisan('gymai:send-announcements')->assertSuccessful();
        $this->artisan('gymai:send-announcements')->assertSuccessful(); // never twice
        $this->assertSame(['New batch'], array_column($this->fcm->sent, 'title'));

        // "Send now" at night: in the list right away, pushed when quiet hours end (07:00).
        $this->travelTo(Carbon::parse('2026-11-24 22:00'));
        $this->fcm->sent = [];
        $this->inGym($this->gym, fn () => $service->send($fourth->fresh()));
        $this->assertSame('2026-11-25 07:00', $fourth->fresh()->scheduled_at->format('Y-m-d H:i'));
        $this->artisan('gymai:send-announcements')->assertSuccessful();
        $this->assertSame([], $this->fcm->sent);
        $this->travelTo(Carbon::parse('2026-11-25 07:00'));
        $this->artisan('gymai:send-announcements')->assertSuccessful();
        $this->assertSame(['Four'], array_column($this->fcm->sent, 'title'));
    }

    public function test_membership_pushes_are_sent_once_and_follow_the_gym_settings(): void
    {
        $expiring = $this->withPhone($this->createMember($this->gym, ['name' => 'Ends Soon']), str_repeat('i', 40));
        $renewed = $this->withPhone($this->createMember($this->gym, ['name' => 'Renewed']), str_repeat('j', 40));
        $owing = $this->withPhone($this->createMember($this->gym, ['name' => 'Owes Fees']), str_repeat('k', 40));
        $this->actingAs($this->owner);

        $this->inGym($this->gym, function () use ($expiring, $renewed, $owing) {
            $plan = $this->createPlan($this->gym, ['duration_days' => 30]);
            $memberships = app(MembershipService::class);
            $memberships->assign($expiring, $plan, '2026-10-27');  // ends 26 Nov
            $memberships->assign($renewed, $plan, '2026-10-27');   // also ends 26 Nov...
            $memberships->assign($renewed, $plan, '2026-11-27');   // ...but is already renewed
            $memberships->assign($owing, $plan, '2026-11-20');     // 1000 to pay
        });

        // 23 Nov: 3 days left.
        $this->artisan('push:membership-expiring')->assertSuccessful();
        $this->artisan('push:membership-expiring')->assertSuccessful();
        $this->assertSame(['i' => ['Membership ends in 3 days']], $this->pushedByPhone());
        $this->assertSame('profile', $this->fcm->sent[0]['data']['screen']);
        $this->assertSame('membership', $this->fcm->sent[0]['channel']);

        // Monday: pending fees, once a week.
        $this->fcm->sent = [];
        $this->artisan('push:balance-due')->assertSuccessful();
        $this->artisan('push:balance-due')->assertSuccessful();
        $titles = $this->pushedByPhone();
        $this->assertSame(['Pending fees: '.money(1000)], $titles['k']);
        $this->assertSame('payments', $this->fcm->sent[0]['data']['screen']);

        // 27 Nov: ended yesterday, not renewed.
        $this->fcm->sent = [];
        $this->travelTo(Carbon::parse('2026-11-27 10:05'));
        $this->artisan('push:membership-expired')->assertSuccessful();
        $this->assertSame(['i' => ['Your membership has ended']], $this->pushedByPhone());

        // Switched off by the gym admin -> nothing.
        Livewire::test(Announcements::class)->set('automatic.membership_expiring.enabled', false)->call('saveAutomatic', 'membership_expiring');
        $this->inGym($this->gym, fn () => app(MembershipService::class)->assign($expiring, $this->createPlan($this->gym, ['duration_days' => 4]), '2026-11-27'));
        $this->fcm->sent = [];
        $this->travelTo(Carbon::parse('2026-11-29 10:00'));
        $this->artisan('push:membership-expiring')->assertSuccessful();
        $this->assertSame([], $this->fcm->sent);
    }

    public function test_program_workout_weigh_in_goal_and_streak_pushes(): void
    {
        $training = $this->withPhone($this->createMember($this->gym, ['name' => 'In Program', 'height_cm' => 170]), str_repeat('m', 40));
        $regular = $this->withPhone($this->createMember($this->gym, ['name' => 'Regular']), str_repeat('n', 40));
        $this->actingAs($this->owner);

        $this->inGym($this->gym, function () use ($training, $regular) {
            app(MembershipService::class)->assign($regular, $this->createPlan($this->gym), '2026-11-01');
            app(ProgramService::class)->importDefaults(FitnessPlanType::Workout);
            app(ProgramService::class)->assign($training, FitnessProgram::where('library_key', 'prog-wo-wl-beginner')->sole(), '2026-11-23');
        });
        $this->assertSame(['m' => ['Your new workout plan is ready']], $this->pushedByPhone());
        $this->assertSame(['workout', 'general'], [$this->fcm->sent[0]['data']['screen'], $this->fcm->sent[0]['channel']]);

        // Morning: today's workout (once), and a first weigh-in reminder.
        $this->fcm->sent = [];
        $this->artisan('push:workout-reminder')->assertSuccessful();
        $this->artisan('push:workout-reminder')->assertSuccessful();
        $this->artisan('push:weigh-in-due')->assertSuccessful();
        $this->artisan('push:weigh-in-due')->assertSuccessful();
        $titles = $this->pushedByPhone();
        $this->assertSame(['Today: Full body strength A', 'Time for your weigh-in'], $titles['m']);
        $this->assertSame(['Time for your weigh-in'], $titles['n']);

        // Reaching the target weight.
        $this->fcm->sent = [];
        $this->inGym($this->gym, function () use ($training) {
            $weights = app(WeightTrackingService::class);
            $weights->setGoal($training, 70, null, 75);
            $weights->record($training, 69.8);
        });
        $this->assertSame(['m' => ['You reached your target weight!']], $this->pushedByPhone());
        $this->assertSame('progress', $this->fcm->sent[0]['data']['screen']);

        // Streak at risk: 3+ day streak, no visit today, gym open (Thursday).
        $this->inGym($this->gym, function () use ($regular) {
            foreach (['2026-11-23', '2026-11-24', '2026-11-25'] as $day) {
                $this->travelTo(Carbon::parse($day.' 07:00'));
                Attendance::forceCreate(['member_id' => $regular->id, 'date' => $day, 'check_in_at' => now(), 'method' => 'manual']);
            }
            app(StreakService::class)->refresh($regular);
        });
        $this->fcm->sent = [];
        $this->travelTo(Carbon::parse('2026-11-26 19:00'));
        $this->artisan('push:streak-at-risk')->assertSuccessful();
        $this->artisan('push:streak-at-risk')->assertSuccessful();
        $this->assertSame(['n' => ['Keep your 3-day streak alive']], $this->pushedByPhone());
        $this->assertSame(['streak', 'reminders'], [$this->fcm->sent[0]['data']['screen'], $this->fcm->sent[0]['channel']]);

        // Member switched reminders off: still in the list, not pushed.
        $this->inGym($this->gym, fn () => app(PushNotificationService::class)->updateSettings($regular, ['reminders' => false]));
        $this->fcm->sent = [];
        $this->travelTo(Carbon::parse('2026-11-27 19:00'));
        $this->artisan('push:streak-at-risk')->assertSuccessful();
        $this->assertSame([], $this->fcm->sent);
    }

    public function test_push_test_command_and_invalid_tokens_are_removed(): void
    {
        $member = $this->withPhone($this->createMember($this->gym), str_repeat('p', 40));
        $this->withPhone($member, str_repeat('q', 40));
        $this->fcm->results[str_repeat('q', 40)] = FcmClient::INVALID_TOKEN;

        $this->artisan('push:test', ['member_id' => $member->id])->assertSuccessful();

        $this->assertSame(['p' => ['Test from Push Gym']], $this->pushedByPhone());
        $this->assertSame(['home', 'general'], [$this->fcm->sent[0]['data']['screen'], $this->fcm->sent[0]['channel']]);
        $this->assertSame([str_repeat('p', 40)], MemberDevice::withoutGlobalScope(GymScope::class)->pluck('token')->all());
    }

    public function test_every_notification_type_uses_the_android_channel_and_sound_the_app_ships(): void
    {
        $expected = [
            'announcement' => 'fitz_announcements',
            'membership_expiring' => 'fitz_membership', 'membership_expired' => 'fitz_membership',
            'payment_received' => 'fitz_membership', 'balance_due' => 'fitz_membership',
            'plan_assigned' => 'fitz_general', 'goal_achieved' => 'fitz_general', 'ai_report_ready' => 'fitz_general', 'test' => 'fitz_general',
            'weigh_in_due' => 'fitz_reminders', 'workout_reminder' => 'fitz_reminders', 'streak_at_risk' => 'fitz_reminders',
        ];

        foreach (PushMessage::TYPES as $type => [, , $channel]) {
            $this->assertSame($expected[$type] ?? null, FcmClient::androidChannel($channel)['id'], $type);
            $this->assertSame('fitz_chime', FcmClient::androidChannel($channel)['sound'], $type);
        }

        // Personal reminders (ReminderService): meals and workouts on the reminders channel, water on its own.
        $this->assertSame(['id' => 'fitz_reminders', 'sound' => 'fitz_chime'], FcmClient::androidChannel('reminders'));
        $this->assertSame(['id' => 'fitz_water', 'sound' => 'water_drop'], FcmClient::androidChannel('water'));
        $this->assertSame(['id' => 'fitz_general', 'sound' => 'fitz_chime'], FcmClient::androidChannel('unknown'));
    }

    public function test_every_push_carries_its_saved_notification_id_and_picture(): void
    {
        $this->app->forgetInstance(FcmClient::class);
        $member = $this->withPhone($this->createMember($this->gym, ['name' => 'Detail Page']), str_repeat('w', 40));
        $sent = [];
        $client = new class($sent) extends FcmClient
        {
            public function __construct(public array &$sent) {}

            public function isConfigured(): bool
            {
                return true;
            }

            public function send(string $token, string $title, string $body, array $data = [], string $channel = 'general', ?string $imageUrl = null, array $android = []): string
            {
                $this->sent[] = compact('title', 'body', 'data', 'channel', 'imageUrl');

                return self::SENT;
            }
        };
        $this->app->instance(FcmClient::class, $client);

        $this->artisan('push:test', ['member_id' => $member->id, '--image' => 'https://cdn.example.com/offer.jpg'])->assertSuccessful();

        $row = $this->inGym($this->gym, fn () => MemberNotification::sole());
        $this->assertSame('https://cdn.example.com/offer.jpg', $row->image_url);
        $this->assertGreaterThanOrEqual(3, substr_count($row->body, "\n"));

        $push = $client->sent[0];
        $this->assertSame($row->id, $push['data']['notification_id']);
        $this->assertSame([$row->title, $row->body, 'general'], [$push['title'], $push['body'], $push['channel']]);
        $this->assertSame('https://cdn.example.com/offer.jpg', $push['imageUrl']);
        $this->assertSame('https://cdn.example.com/offer.jpg', $push['data']['image_url']);
        $this->assertSame(['test', 'home', $this->gym->id], [$push['data']['type'], $push['data']['screen'], $push['data']['gym_id']]);

        // The app reads the same row: full text, picture, screen.
        $this->withToken($this->memberToken($member))->getJson('/api/v1/me/notifications')->assertOk()
            ->assertJsonPath('data.notifications.0.id', $row->id)
            ->assertJsonPath('data.notifications.0.body', $row->body)
            ->assertJsonPath('data.notifications.0.image_url', 'https://cdn.example.com/offer.jpg')
            ->assertJsonPath('data.notifications.0.screen', 'home');

        // Reading it twice keeps the first read time; other members' ids are 404.
        $token = $this->memberToken($member);
        $this->withToken($token)->postJson("/api/v1/me/notifications/{$row->id}/read")->assertOk();
        $firstRead = $row->fresh()->read_at;
        $this->travel(5)->minutes();
        $this->withToken($token)->postJson("/api/v1/me/notifications/{$row->id}/read")->assertOk();
        $this->assertEquals($firstRead, $row->fresh()->read_at);

        $other = $this->createMember($this->gym);
        $this->withToken($this->memberToken($other))->postJson("/api/v1/me/notifications/{$row->id}/read")->assertNotFound();
    }

    public function test_push_message_uses_app_screens_and_channels(): void
    {
        foreach (PushMessage::TYPES as $type => [, $screen, $channel]) {
            $this->assertContains($screen, PushMessage::SCREENS, $type);
            $this->assertContains($channel, PushMessage::CHANNELS, $type);
        }
        $this->assertSame('diet', PushMessage::make('plan_assigned', 'x', 'y', [], 'diet')->screen);
        $this->assertSame('workout', PushMessage::make('plan_assigned', 'x', 'y', [], 'nonsense')->screen);
    }

    public function test_super_admin_templates_word_every_automatic_notification(): void
    {
        $member = $this->withPhone($this->createMember($this->gym, ['name' => 'Kabir Mehta']), str_repeat('r', 40));
        $this->actingAs($this->superAdmin());

        // Opening and cancelling the editor, and activating / deactivating from the list.
        Livewire::test(PlatformNotifications::class)->set('tab', 'templates')
            ->call('edit', 'weigh_in_due')->assertSet('editing', 'weigh_in_due')->call('cancelEdit')->assertSet('editing', null)
            ->call('toggleTemplate', 'weigh_in_due')->assertSee('Inactive for all gyms');
        $this->assertFalse(app(NotificationSettings::class)->template('weigh_in_due')['is_enabled']);
        Livewire::test(PlatformNotifications::class)->set('tab', 'templates')->call('toggleTemplate', 'weigh_in_due');
        $this->assertTrue(app(NotificationSettings::class)->template('weigh_in_due')['is_enabled']);

        Livewire::test(PlatformNotifications::class)
            ->set('tab', 'templates')
            ->call('edit', 'payment_received')
            ->set('form.title', 'Thanks {first_name}! {secret}')->call('saveTemplate')->assertHasErrors('form.title')
            ->set('form.title', 'Thanks {first_name}!')->set('form.body', '{gym_name} got {amount}. Receipt {receipt}.')
            ->call('saveTemplate')->assertHasNoErrors();

        $this->actingAs($this->owner);
        $this->inGym($this->gym, function () use ($member) {
            $membership = app(MembershipService::class)->assign($member, $this->createPlan($this->gym));
            app(PaymentService::class)->record($membership, 500, 'cash');
        });

        $this->assertSame('Thanks Kabir!', $this->fcm->sent[0]['title']);
        $this->assertStringStartsWith('Push Gym got '.money(500).'. Receipt RCPT-', $this->fcm->sent[0]['body']);

        // Switched off for the whole platform: gyms cannot turn it back on.
        $this->actingAs($this->superAdmin());
        Livewire::test(PlatformNotifications::class)->call('edit', 'payment_received')->set('form.is_enabled', false)->call('saveTemplate');
        $this->assertFalse(app(NotificationSettings::class)->forGym($this->gym->fresh(), 'payment_received')['enabled']);
    }

    public function test_gyms_choose_the_time_and_rules_and_push_run_follows_them(): void
    {
        $member = $this->withPhone($this->createMember($this->gym, ['name' => 'Early Bird']), str_repeat('s', 40));
        $this->actingAs($this->owner);
        $this->inGym($this->gym, fn () => app(MembershipService::class)->assign($member, $this->createPlan($this->gym, ['duration_days' => 30]), '2026-10-29')); // ends 28 Nov: 5 days

        // Default rules are 7/3/1 days: nothing on 23 Nov. The gym adds 5 days and 08:00, outside quiet hours only.
        Livewire::test(Announcements::class)->set('tab', 'automatic')
            ->set('automatic.membership_expiring.time', '22:00')->call('saveAutomatic', 'membership_expiring')->assertHasErrors('time')
            ->set('automatic.membership_expiring.time', '08:00')->set('automatic.membership_expiring.params.days', ['5', '1'])
            ->call('saveAutomatic', 'membership_expiring')->assertHasNoErrors();

        $setting = app(NotificationSettings::class)->forGym($this->gym->fresh(), 'membership_expiring');
        $this->assertSame(['08:00', [5, 1]], [$setting['time'], $setting['params']['days']]);

        $this->travelTo(Carbon::parse('2026-11-23 07:55'));
        $this->artisan('push:run')->assertSuccessful();
        $this->assertSame([], $this->fcm->sent);

        $this->travelTo(Carbon::parse('2026-11-23 08:00'));
        $this->artisan('push:run')->assertSuccessful();
        $this->artisan('push:run')->assertSuccessful();
        $this->assertSame(['s' => ['Membership ends in 5 days', 'Time for your weigh-in']], $this->pushedByPhone()); // weigh-in is also at 08:00

        // Reset puts the Fitnix defaults back.
        Livewire::test(Announcements::class)->call('resetAutomatic', 'membership_expiring');
        $this->assertSame('10:00', app(NotificationSettings::class)->forGym($this->gym->fresh(), 'membership_expiring')['time']);
    }

    public function test_gym_admin_turns_automatic_notifications_off_one_by_one_or_all_at_once_and_sees_counts(): void
    {
        $member = $this->withPhone($this->createMember($this->gym, ['name' => 'Count Me']), str_repeat('t', 40));
        $this->actingAs($this->owner);
        $membership = $this->inGym($this->gym, fn () => app(MembershipService::class)->assign($member, $this->createPlan($this->gym)));

        $this->inGym($this->gym, fn () => app(PaymentService::class)->record($membership, 100, 'cash'));
        $this->assertCount(1, $this->fcm->sent);

        Livewire::test(Announcements::class)->set('tab', 'automatic')
            ->assertSee('9 of 9')
            ->assertSeeHtml('<strong class="text-gray-800">1</strong> today')
            // One notification off: no more payment pushes; its time and rules are kept.
            ->call('toggleAutomatic', 'payment_received')
            ->assertSet('automatic.payment_received.enabled', false)
            ->assertSee('8 of 9');

        $this->inGym($this->gym, fn () => app(PaymentService::class)->record($membership->fresh(), 100, 'cash'));
        $this->assertCount(1, $this->fcm->sent);

        // Everything off at once: nothing goes out, the individual choices are kept.
        Livewire::test(Announcements::class)->set('tab', 'automatic')->call('togglePause')->assertSee('Automatic notifications are off');
        $config = app(NotificationSettings::class);
        $this->assertFalse($config->forGym($this->gym->fresh(), 'weigh_in_due')['enabled']);
        $this->assertTrue($config->forGym($this->gym->fresh(), 'weigh_in_due')['gym_enabled']);

        $this->travelTo(Carbon::parse('2026-11-23 10:00'));
        $this->artisan('push:run')->assertSuccessful();
        $this->artisan('push:weigh-in-due')->assertSuccessful();
        $this->assertCount(1, $this->fcm->sent);

        // Back on: weigh-in reminders go out again, payment stays off.
        Livewire::test(Announcements::class)->set('tab', 'automatic')->call('togglePause')->assertSee('Automatic notifications are on');
        $this->artisan('push:weigh-in-due')->assertSuccessful();
        $this->assertSame(['Payment received', 'Time for your weigh-in'], array_column($this->fcm->sent, 'title'));
        $this->assertFalse($config->forGym($this->gym->fresh(), 'payment_received')['enabled']);

        // Trainers cannot change these switches.
        $trainer = $this->createTrainer($this->gym);
        $trainer->givePermissionTo('announcements.send');
        $this->actingAs($trainer);
        Livewire::test(Announcements::class)->call('toggleAutomatic', 'weigh_in_due')->assertForbidden();
        Livewire::test(Announcements::class)->call('togglePause')->assertForbidden();
    }

    public function test_gym_admins_see_a_members_phones_and_fcm_tokens_and_can_send_a_test(): void
    {
        $token = 'fcm-'.str_repeat('u', 60);
        $member = $this->withPhone($this->createMember($this->gym, ['name' => 'Token Owner']), $token);
        $noPhone = $this->createMember($this->gym, ['name' => 'No Phone']);

        $this->actingAs($this->owner);
        $this->get(route('gym.members.show', $member))->assertOk()->assertSee('Phones & notifications', false)->assertSee('FCM token');

        Livewire::test(MemberDevices::class, ['member' => $member])
            ->assertSee('Android')->assertSee($token, false)
            ->call('sendTest');
        $this->assertSame([[$token, 'Test from Push Gym']], array_map(fn ($s) => [$s['token'], $s['title']], $this->fcm->sent));

        Livewire::test(MemberDevices::class, ['member' => $noPhone])->assertSee('No phone registered yet');

        $device = MemberDevice::withoutGlobalScope(GymScope::class)->sole();
        Livewire::test(MemberDevices::class, ['member' => $member])->call('removeDevice', $device->id);
        $this->assertSame(0, MemberDevice::withoutGlobalScope(GymScope::class)->count());

        // Trainers never see tokens.
        $trainer = $this->createTrainer($this->gym);
        $assigned = $this->withPhone($this->createMember($this->gym, [], ['trainer_id' => $trainer->id]), str_repeat('v', 40));
        $this->actingAs($trainer);
        $this->get(route('gym.members.show', $assigned))->assertOk()->assertDontSee('Phones & notifications', false)->assertDontSee(str_repeat('v', 40));
        Livewire::test(MemberDevices::class, ['member' => $assigned])->assertForbidden();
    }

    public function test_health_shows_whether_the_scheduler_and_queue_run_on_their_own(): void
    {
        $health = app(SystemHealth::class);
        $this->assertFalse($health->report()['scheduler']['ok']);
        $this->assertFalse($health->report()['queue']['ok']);

        // One scheduler run: it writes its heartbeat and queues the queue heartbeat (run right away in tests).
        $this->artisan('schedule:run')->assertSuccessful();
        $report = $health->report();
        $this->assertTrue($report['scheduler']['ok']);
        $this->assertTrue($report['queue']['ok']);

        $this->travel(5)->minutes();
        $this->assertFalse($health->report()['scheduler']['ok'], 'stale after 3 minutes');

        $this->artisan('gymai:health')->assertSuccessful()->expectsOutputToContain('Scheduler (cron)');

        $this->actingAs($this->superAdmin());
        Livewire::test(PlatformNotifications::class)->set('tab', 'rules')->assertSee('System status')->assertSee('Queue (sends notifications)');
    }

    public function test_super_admin_sets_quiet_hours_and_daily_limit(): void
    {
        $this->actingAs($this->superAdmin());
        Livewire::test(PlatformNotifications::class)->set('tab', 'rules')
            ->set('quietStart', '22:00')->set('quietEnd', '06:30')->set('dailyLimit', 5)->call('saveRules')->assertHasNoErrors();

        $config = app(NotificationSettings::class);
        $this->assertSame(['start' => '22:00', 'end' => '06:30'], $config->quietHours());
        $this->assertSame(5, $config->dailyAnnouncementLimit());
        $this->assertTrue($config->isQuietAt(Carbon::parse('2026-11-23 23:00')));
        $this->assertFalse($config->isQuietAt(Carbon::parse('2026-11-23 21:30')));
        $this->assertSame('2026-11-24 06:30', $config->quietUntil(Carbon::parse('2026-11-23 23:00'))->format('Y-m-d H:i'));
    }

    public function test_fcm_client_signs_in_with_the_service_account_and_sends_http_v1_messages(): void
    {
        $this->app->forgetInstance(FcmClient::class);
        $config = is_file($cnf = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf') ? ['config' => $cnf] : [];
        $key = @openssl_pkey_new($config + ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        if (! $key || ! openssl_pkey_export($key, $pem, null, $config)) {
            $this->markTestSkipped('OpenSSL key generation is not available.');
        }

        // Relative to the project folder, like FIREBASE_CREDENTIALS=storage/app/firebase/service-account.json
        $relative = 'storage/framework/testing/fcm-'.uniqid().'.json';
        @mkdir(dirname(base_path($relative)), 0777, true);
        file_put_contents(base_path($relative), json_encode(['project_id' => 'gymai-test', 'client_email' => 'push@gymai-test.iam.gserviceaccount.com', 'private_key' => $pem]));
        config(['services.fcm.credentials' => $relative]);

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
            'fcm.googleapis.com/v1/projects/gymai-test/messages:send' => Http::sequence()
                ->push(['name' => 'projects/gymai-test/messages/1'])
                ->push(['error' => ['code' => 404, 'status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404)
                // A problem with the message itself must not be mistaken for a dead phone.
                ->push(['error' => ['code' => 400, 'status' => 'INVALID_ARGUMENT', 'message' => 'Value for APS key [mutable-content] is either 0 or 1.',
                    'details' => [['@type' => 'type.googleapis.com/google.rpc.BadRequest', 'fieldViolations' => [['field' => 'message.apns.payload.aps.mutable-content']]]]]], 400)
                ->push(['error' => ['code' => 400, 'status' => 'INVALID_ARGUMENT', 'message' => 'The registration token is not a valid FCM registration token',
                    'details' => [['errorCode' => 'INVALID_ARGUMENT']]]], 400),
        ]);

        $fcm = new FcmClient;
        $this->assertTrue($fcm->isConfigured());
        $this->assertSame(FcmClient::SENT, $fcm->send('device-token', 'Hello', 'World', ['screen' => 'progress', 'gym_id' => 5], 'reminders', 'https://cdn.example.com/a.jpg'));
        $this->assertSame(FcmClient::INVALID_TOKEN, $fcm->send('old-token', 'Hello', 'World'));
        $this->assertSame(FcmClient::FAILED, $fcm->send('good-token', 'Hello', 'World'));
        $this->assertSame(FcmClient::INVALID_TOKEN, $fcm->send('garbage', 'Hello', 'World'));

        // Without a picture the iOS block carries no empty values (APNs rejects them, and FCM then refuses the whole message).
        Http::assertSent(fn ($request) => str_contains($request->url(), 'messages:send')
            && $request['message']['token'] === 'old-token'
            && $request['message']['apns'] === ['payload' => ['aps' => ['sound' => 'default']]]
            && $request['message']['android']['notification'] === ['channel_id' => 'fitz_general', 'sound' => 'fitz_chime']
            && ! array_key_exists('image', $request['message']['notification']));

        Http::assertSent(function ($request) use ($pem) {
            if (! str_contains($request->url(), 'oauth2')) {
                return false;
            }
            [$header, $claims, $signature] = explode('.', $request['assertion']);
            $decode = fn ($v) => base64_decode(strtr($v, '-_', '+/'));
            $public = openssl_pkey_get_details(openssl_pkey_get_private($pem))['key'];

            return json_decode($decode($claims), true)['scope'] === 'https://www.googleapis.com/auth/firebase.messaging'
                && openssl_verify($header.'.'.$claims, $decode($signature), $public, OPENSSL_ALGO_SHA256) === 1;
        });
        Http::assertSent(fn ($request) => str_contains($request->url(), 'messages:send')
            && $request->hasHeader('Authorization', 'Bearer ya29.test')
            && $request['message']['token'] === 'device-token'
            && $request['message']['data'] === ['screen' => 'progress', 'gym_id' => '5', 'image_url' => 'https://cdn.example.com/a.jpg']
            && $request['message']['notification']['image'] === 'https://cdn.example.com/a.jpg'
            && $request['message']['apns'] === ['payload' => ['aps' => ['sound' => 'default', 'mutable-content' => 1]], 'fcm_options' => ['image' => 'https://cdn.example.com/a.jpg']]
            && $request['message']['android'] === ['priority' => 'high', 'notification' => ['channel_id' => 'fitz_reminders', 'sound' => 'fitz_chime', 'image' => 'https://cdn.example.com/a.jpg']]);

        // Phones cannot load pictures from a developer machine.
        foreach (['http://localhost:8000/storage/a.jpg', 'http://127.0.0.1/a.jpg', 'http://192.168.1.5/a.jpg', '/storage/a.jpg'] as $local) {
            $this->assertNull(FcmClient::publicImageUrl($local), $local);
        }
        $this->assertSame('https://gym.example.com/storage/a.jpg', FcmClient::publicImageUrl('https://gym.example.com/storage/a.jpg'));

        @unlink(base_path($relative));
    }
}

/** Records pushes instead of calling Firebase. */
class FakeFcmClient extends FcmClient
{
    /** @var list<array{token: string, title: string, body: string, data: array<string, mixed>, channel: string, image: ?string}> */
    public array $sent = [];

    /** @var array<string, string> token => result */
    public array $results = [];

    public function isConfigured(): bool
    {
        return true;
    }

    public function send(string $token, string $title, string $body, array $data = [], string $channel = 'general', ?string $imageUrl = null, array $android = []): string
    {
        $result = $this->results[$token] ?? self::SENT;

        if ($result === self::SENT) {
            $this->sent[] = ['token' => $token, 'title' => $title, 'body' => $body, 'data' => $data, 'channel' => $channel, 'image' => $imageUrl, 'android' => $android];
        }

        return $result;
    }
}
