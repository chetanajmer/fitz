<?php

namespace Tests\Feature;

use App\Livewire\Gym\Members\AiProgress;
use App\Livewire\Portal\Progress;
use App\Models\AiReport;
use App\Models\Gym;
use App\Models\GymAiSetting;
use App\Models\Member;
use App\Models\PlatformSetting;
use App\Models\ProgressPhoto;
use App\Services\AiReportService;
use App\Services\AttendanceService;
use App\Services\MembershipService;
use App\Services\StreakService;
use App\Services\TransformationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PortalProgressTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected Member $member;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['services.openrouter.key' => 'test-key']);
        PlatformSetting::put(['ai_enabled' => '1']);
        Http::fake(['openrouter.ai/*' => Http::response(['model' => 'm', 'choices' => [['message' => ['content' => json_encode(
            ['summary' => 'Steady <b>progress</b>.', 'wins' => ['Regular visits'], 'focus_areas' => [['title' => 'Sleep', 'why' => 'Short nights', 'tip' => 'Eight hours']], 'missing' => ['Weekly weigh-ins'], 'motivation' => 'Go on!', 'confidence' => 'low'])]]], 'usage' => []])]);

        $this->gym = $this->gymOf($this->createGymOwner('Portal Gym'));
        $this->member = $this->portalMember('one@example.test');
        $this->inGym($this->gym, function () {
            app(MembershipService::class)->assign($this->member, $this->createPlan($this->gym), now()->toDateString());
            (new GymAiSetting)->fill(['allowed' => true, 'enabled' => true, 'monthly_credits' => 10, 'member_ceiling' => 4, 'member_limit' => 4])->save();
        });
    }

    protected function portalMember(string $email): Member
    {
        return $this->createMember($this->gym, ['email' => $email, 'phone' => '98'.random_int(10000000, 99999999)], ['portal_password' => 'password'])->refresh();
    }

    public function test_member_consents_records_progress_and_gets_a_report(): void
    {
        $this->actingAs($this->member->user);
        $this->get(route('portal.progress'))->assertOk()->assertSee('I agree');

        $page = Livewire::test(Progress::class)
            ->call('requestReport', 'progress')->assertHasErrors('report'); // consent first
        Http::assertNothingSent();

        $page->call('setConsent', true)->assertSee('credits left this month')
            ->set('measurement.waist_cm', '90')->call('saveMeasurement')->assertHasNoErrors()
            ->set('photo', UploadedFile::fake()->image('me.jpg', 300, 400))->call('uploadPhoto')->assertHasNoErrors()
            ->call('requestReport', 'progress')->assertHasNoErrors()
            ->assertSee('Regular visits')->assertSeeInOrder(['Where to work next', 'Short nights', 'Eight hours', 'Weekly weigh-ins'])->assertDontSee('Start here')
            ->assertSee('Steady <b>progress</b>.'); // shown as text, never as HTML

        $this->assertSame('member', AiReport::withoutGlobalScopes()->sole()->requested_by);
        $photo = ProgressPhoto::withoutGlobalScopes()->sole();
        $this->assertSame(['app', null], [$photo->source, $photo->uploaded_by]);
        $this->get(route('portal.progress-photo', $photo->id))->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        $page->call('setConsent', false)->assertSee('I agree');
        $this->assertNull($this->member->refresh()->ai_consent_at);
    }

    public function test_photos_come_first_and_two_photos_are_enough_to_compare(): void
    {
        Http::swap(new HttpFactory);
        Http::fake(['openrouter.ai/*' => Http::response(['model' => 'm', 'choices' => [['message' => ['content' => json_encode(
            ['summary' => 'Leaner waist.', 'change_level' => 'moderate', 'changes' => [], 'numbers' => 'None logged.', 'photo_quality' => 'good', 'photo_tips' => [], 'focus_areas' => [['title' => 'Legs', 'why' => 'They changed least.', 'tip' => 'Add squats twice a week.'], ['title' => 'Back', 'why' => 'Hard to see.', 'tip' => 'Add rows.']], 'missing' => ['A side photo'], 'motivation' => 'Go on!', 'confidence' => 'medium'])]]], 'usage' => []])]);
        $this->actingAs($this->member->user);

        $page = Livewire::test(Progress::class)->call('setConsent', true)
            ->assertSeeInOrder(['Progress photos', 'Add two photos to compare them.', 'AI reports', 'Add measurements (optional)'])
            ->assertDontSee('Before photo');
        foreach ([now()->subMonths(3), now()] as $day) {
            $page->set('photo', UploadedFile::fake()->image('me.jpg', 300, 400))->set('takenOn', $day->toDateString())->call('uploadPhoto')->assertHasNoErrors();
        }
        [$before, $after] = ProgressPhoto::withoutGlobalScopes()->orderBy('taken_on')->pluck('id')->all();

        $page->assertSeeInOrder(['Progress photos', 'Before photo', 'After photo', 'AI reports'])
            ->set('beforePhotoId', (string) $before)->set('afterPhotoId', (string) $after)
            ->call('requestReport', 'photo_comparison')->assertHasNoErrors()->assertSee('Leaner waist.')
            ->assertSeeInOrder(['Legs', 'Start here', 'They changed least.', 'Add squats', 'Add rows.', 'A side photo'])->assertSee('None logged.')->assertSee('Clear change in 3 months')->assertSee('Share your progress')->assertSee('Show my photos');
        $this->assertSame(0, $this->member->measurements()->withoutGlobalScopes()->count());

        // Only readings within two weeks of each photo are shown, the closest one first.
        foreach ([[now()->subMonths(3)->addDays(10), 95], [now()->subMonths(3)->addDays(2), 90], [now()->subMonth(), 70], [now(), 84]] as [$day, $waist]) {
            $page->set('measurement.measured_on', $day->toDateString())->set('measurement.waist_cm', (string) $waist)->call('saveMeasurement')->assertHasNoErrors();
        }
        $summary = $this->inGym($this->gym, fn () => app(TransformationService::class)->readingsFor(AiReport::sole(), app(TransformationService::class)->summary($this->member)));
        $this->assertSame([['label' => 'Waist', 'unit' => 'cm', 'before' => 90.0, 'after' => 84.0, 'change' => -6.0]], $summary);
        $page->assertSee('−6 cm')->assertSee('90 to 84')->assertDontSee('None logged.');
    }

    public function test_only_the_member_can_share_and_only_a_report_with_something_to_show(): void
    {
        $report = $this->inGym($this->gym, function () {
            foreach ([4, 3, 2, 1] as $daysAgo) {
                $this->travel(-$daysAgo)->days();
                app(AttendanceService::class)->checkIn($this->member, requireValidMembership: false);
                $this->travelBack();
            }
            $report = new AiReport;
            $report->forceFill(['member_id' => $this->member->id, 'type' => 'progress', 'status' => 'completed', 'credits' => 1, 'requested_by' => 'member',
                'result' => ['summary' => 'Good.', 'wins' => [], 'focus_areas' => [], 'motivation' => 'Go on!', 'confidence' => 'low']])->save();

            return $report;
        });

        $this->actingAs($this->member->user);
        Livewire::test(Progress::class)->assertSee('4 day streak')->assertSee('Share your progress');

        $report->forceFill(['type' => 'photo_comparison', 'result' => ['change_level' => 'none', 'changes' => [], 'numbers' => '', 'photo_quality' => 'good', 'photo_tips' => []] + $report->result])->save();
        Livewire::test(Progress::class)->assertSee('No visible change')->assertDontSee('Share your progress');

        $report->forceFill(['result' => ['change_level' => 'strong'] + $report->result])->save();
        Livewire::test(Progress::class)->assertSee('Share your progress');
        $this->actingAs($this->gym->owner);
        $this->inGym($this->gym, fn () => Livewire::test(AiProgress::class, ['member' => $this->member])->assertSee('Strong change')->assertDontSee('Share your progress'));
    }

    public function test_the_ai_gets_full_weeks_with_zeros_and_the_unfinished_week_apart(): void
    {
        $this->travelTo(Carbon::parse('2026-11-25 10:00')); // a Wednesday
        $attendance = $this->inGym($this->gym, function () {
            $this->member->forceFill(['joining_date' => '2026-11-04'])->save();
            foreach (['2026-11-05', '2026-11-18', '2026-11-19', '2026-11-24'] as $day) {
                $this->travelTo(Carbon::parse($day.' 10:00'));
                app(AttendanceService::class)->checkIn($this->member, requireValidMembership: false);
            }
            $this->travelTo(Carbon::parse('2026-11-25 10:00'));

            return app(AiReportService::class)->progressData($this->member->refresh())['attendance'];
        });

        $this->assertSame(['2026-11-02' => 1, '2026-11-09' => 0, '2026-11-16' => 2], $attendance['visits_per_full_week']);
        $this->assertSame(1, $attendance['visits_this_week_so_far']);
    }

    public function test_the_headline_fits_what_the_member_reached_and_stays_the_same(): void
    {
        $this->inGym($this->gym, function () {
            $headline = function (int $id, int $visitDays, string $type = 'progress', array $result = ['summary' => 'x']) {
                $this->mock(StreakService::class)->shouldReceive('calculate')->andReturn(['current' => min($visitDays, 2), 'longest' => 0, 'total_visit_days' => $visitDays]);
                $report = (new AiReport)->forceFill(['id' => $id, 'type' => $type, 'result' => $result, 'created_at' => now()]);

                return app(TransformationService::class)->highlight($report, $this->member, ['weight' => ['history' => []], 'measurements' => []]);
            };

            $this->assertSame(['Day one', 'Double digits', 'Century club', 'Hundred not out'],
                [$headline(2, 0)['headline'], $headline(2, 24)['headline'], $headline(2, 100)['headline'], $headline(3, 100)['headline']]);
            $this->assertSame('24 gym visits', $headline(2, 24)['period']);
            foreach (array_keys(AiReport::CHANGE_LEVELS) as $level) {
                $highlight = $headline(1, 0, 'photo_comparison', ['change_level' => $level]);
                $this->assertNotEmpty($highlight['headline']);
                $this->assertSame(AiReport::CHANGE_LEVELS[$level], $highlight['period']);
            }
        });
    }

    public function test_member_cannot_reach_another_members_photos_or_measurements(): void
    {
        $this->actingAs($this->member->user);
        Livewire::test(Progress::class)->set('photo', UploadedFile::fake()->image('me.jpg'))->call('uploadPhoto')
            ->set('measurement.waist_cm', '90')->call('saveMeasurement');
        $photo = ProgressPhoto::withoutGlobalScopes()->sole();
        $measurement = $this->member->measurements()->withoutGlobalScopes()->sole();

        $this->actingAs($this->portalMember('two@example.test')->user);
        $this->get(route('portal.progress-photo', $photo->id))->assertNotFound();
        Livewire::test(Progress::class)->call('deletePhoto', $photo->id)->assertStatus(404);
        Livewire::test(Progress::class)->call('deleteMeasurement', $measurement->id)->assertStatus(404);
        $this->assertNotNull($photo->fresh());
    }

    public function test_staff_cannot_open_the_member_page(): void
    {
        $this->actingAs($this->gym->owner)->get(route('portal.progress'))->assertForbidden();
    }
}
