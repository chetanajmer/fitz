<?php

namespace Tests\Feature;

use App\Livewire\Portal\Progress;
use App\Models\AiReport;
use App\Models\Gym;
use App\Models\GymAiSetting;
use App\Models\Member;
use App\Models\PlatformSetting;
use App\Models\ProgressPhoto;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\UploadedFile;
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
            ['summary' => 'Steady <b>progress</b>.', 'wins' => ['Regular visits'], 'focus_areas' => [['title' => 'Sleep', 'tip' => 'Eight hours']], 'motivation' => 'Go on!', 'confidence' => 'low'])]]], 'usage' => []])]);

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
            ->assertSee('Regular visits')->assertSee('Eight hours')
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
            ['summary' => 'Leaner waist.', 'change_level' => 'moderate', 'changes' => [], 'numbers' => 'None logged.', 'photo_quality' => 'good', 'photo_tips' => [], 'motivation' => 'Go on!', 'confidence' => 'medium'])]]], 'usage' => []])]);
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
            ->call('requestReport', 'photo_comparison')->assertHasNoErrors()->assertSee('Leaner waist.');
        $this->assertSame(0, $this->member->measurements()->withoutGlobalScopes()->count());
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
