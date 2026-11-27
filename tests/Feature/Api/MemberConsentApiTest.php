<?php

namespace Tests\Feature\Api;

use App\Models\AiReport;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberConsent;
use App\Models\ProgressPhoto;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MemberConsentApiTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected Member $member;

    protected Member $other;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->travelTo(Carbon::parse('2026-11-23 10:00'));
        $this->gym = $this->gymOf($this->createGymOwner('Consent Gym'));
        $this->member = $this->createMember($this->gym, ['phone' => '9000000701']);
        $this->other = $this->createMember($this->gym, ['phone' => '9000000702']);
        $this->inGym($this->gym, fn () => app(MembershipService::class)->assign($this->member, $this->createPlan($this->gym)));
    }

    protected function api(?Member $member = null): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken(($member ?? $this->member)->createToken('app', ['member'])->plainTextToken);
    }

    protected function consent(string $type, bool $accepted, ?Member $member = null): TestResponse
    {
        return $this->api($member)->postJson('/api/v1/me/consents', ['type' => $type, 'accepted' => $accepted]);
    }

    protected function upload(?Member $member = null): TestResponse
    {
        return $this->api($member)->post('/api/v1/me/progress-photos', ['photo' => UploadedFile::fake()->image('p.jpg', 300, 400), 'pose' => 'front'], ['Accept' => 'application/json']);
    }

    protected function report(Member $member, string $type = AiReport::PROGRESS, ?int $photoId = null): AiReport
    {
        $report = (new AiReport)->forceFill([
            'gym_id' => $this->gym->id, 'member_id' => $member->id, 'type' => $type, 'status' => AiReport::COMPLETED, 'credits' => 1,
            'requested_by' => 'member', 'content' => 'Private', 'result' => ['summary' => 'x'], 'before_photo_id' => $photoId, 'after_photo_id' => $photoId,
        ]);
        $report->saveQuietly();

        return $report;
    }

    public function test_photo_uploads_and_photo_comparisons_need_the_photo_consent(): void
    {
        $this->api()->getJson('/api/v1/me/consents')->assertOk()
            ->assertJsonPath('data.progress_photos.accepted', false)
            ->assertJsonPath('data.progress_photos.version', null)
            ->assertJsonPath('data.progress_photos.needs_update', false)
            ->assertJsonPath('data.progress_photos.text', 'I agree that Consent Gym may store the progress photos I upload to track my transformation. Only I and my gym\'s staff can see them. I can delete any photo, or all of them, at any time.')
            ->assertJsonPath('data.ai_coach.accepted', false);

        $this->upload()->assertForbidden()->assertJsonPath('code', 'PHOTO_CONSENT_REQUIRED');
        $this->api()->postJson('/api/v1/me/ai/reports', ['type' => 'photo_comparison', 'before_photo_id' => 1, 'after_photo_id' => 2])
            ->assertForbidden()->assertJsonPath('code', 'PHOTO_CONSENT_REQUIRED');
        $this->api()->getJson('/api/v1/me/ai')->assertJsonPath('data.photo_consent', ['accepted' => false, 'needs_update' => false]);

        $this->consent('progress_photos', true)->assertOk()
            ->assertJsonPath('data.progress_photos.accepted', true)
            ->assertJsonPath('data.progress_photos.version', 1)
            ->assertJsonPath('data.progress_photos.accepted_at', '2026-11-23T10:00:00+05:30');
        $this->upload()->assertCreated();
        $this->api()->getJson('/api/v1/me/ai')->assertJsonPath('data.photo_consent.accepted', true);

        // A new version of the text: agree again before uploading more.
        config(['consents.progress_photos.version' => 2]);
        $this->api()->getJson('/api/v1/me/consents')->assertJsonPath('data.progress_photos.accepted', true)->assertJsonPath('data.progress_photos.needs_update', true);
        $this->upload()->assertForbidden()->assertJsonPath('code', 'PHOTO_CONSENT_REQUIRED');
        $this->consent('progress_photos', true)->assertJsonPath('data.progress_photos.needs_update', false);
        $this->upload()->assertCreated();

        $this->api()->postJson('/api/v1/me/consents', ['type' => 'marketing', 'accepted' => true])->assertStatus(422)->assertJsonValidationErrors(['type']);
    }

    public function test_withdrawing_photo_consent_deletes_the_photos_and_their_comparisons_and_keeps_the_history(): void
    {
        $this->consent('progress_photos', true);
        $this->upload()->assertCreated();
        $this->upload()->assertCreated();
        $photos = ProgressPhoto::withoutGlobalScopes()->where('member_id', $this->member->id)->get();
        $comparison = $this->report($this->member, AiReport::PHOTO_COMPARISON, $photos[0]->id);
        $progress = $this->report($this->member);

        $this->consent('progress_photos', true, $this->other);
        $this->upload($this->other)->assertCreated();

        $this->consent('progress_photos', false)->assertOk()
            ->assertJsonPath('message', 'Consent withdrawn.')
            ->assertJsonPath('data.deleted', ['photos' => 2, 'reports' => 1])
            ->assertJsonPath('data.progress_photos.accepted', false)
            ->assertJsonPath('data.progress_photos.withdrawn_at', '2026-11-23T10:00:00+05:30');

        foreach ($photos as $photo) {
            Storage::disk('local')->assertMissing($photo->path);
        }
        $this->assertSame(0, ProgressPhoto::withoutGlobalScopes()->where('member_id', $this->member->id)->count());
        $this->assertSame(1, ProgressPhoto::withoutGlobalScopes()->where('member_id', $this->other->id)->count());
        $this->assertSame([AiReport::ERASED, null], [$comparison->fresh()->status, $comparison->fresh()->content]);
        $this->assertSame('Private', $progress->fresh()->content, 'Progress reports do not use photos.');

        $this->consent('progress_photos', true);
        $history = MemberConsent::withoutGlobalScopes()->where('member_id', $this->member->id)->orderBy('id')->get();
        $this->assertSame([true, false, true], $history->pluck('accepted')->all());
        $this->assertNotNull($history[0]->ip);
    }

    public function test_ai_coach_consent_through_the_consents_endpoint(): void
    {
        $this->consent('ai_coach', true)->assertOk()->assertJsonPath('data.ai_coach.accepted', true)->assertJsonPath('data.ai_coach.version', 2);
        $this->assertNotNull($this->member->fresh()->ai_consent_at);
        $this->api()->getJson('/api/v1/me/ai')->assertJsonPath('data.has_consented', true)->assertJsonPath('data.consent_needs_update', false);

        // The old endpoint still works and is recorded too.
        $this->api()->postJson('/api/v1/me/ai/consent', ['accepted' => false])->assertOk()->assertJsonPath('data.has_consented', false);
        $this->assertNull($this->member->fresh()->ai_consent_at);
        $this->assertSame(2, MemberConsent::withoutGlobalScopes()->where('member_id', $this->member->id)->where('type', 'ai_coach')->count());
    }

    public function test_members_who_agreed_to_the_older_ai_text_are_asked_again(): void
    {
        $this->inGym($this->gym, fn () => (new MemberConsent)->forceFill([
            'member_id' => $this->member->id, 'type' => 'ai_coach', 'version' => 1, 'accepted' => true, 'accepted_at' => now(),
        ])->save());
        $this->member->forceFill(['ai_consent_at' => now()])->save();

        $this->api()->getJson('/api/v1/me/consents')->assertJsonPath('data.ai_coach.accepted', true)->assertJsonPath('data.ai_coach.needs_update', true);
        $this->api()->getJson('/api/v1/me/ai')->assertJsonPath('data.has_consented', true)->assertJsonPath('data.consent_needs_update', true);
    }

    public function test_deleting_ai_data_removes_everything_of_that_member_only(): void
    {
        $this->consent('progress_photos', true);
        $this->consent('ai_coach', true);
        $this->upload()->assertCreated();
        $path = ProgressPhoto::withoutGlobalScopes()->where('member_id', $this->member->id)->sole()->path;
        $this->report($this->member);
        $this->report($this->member, AiReport::PHOTO_COMPARISON);

        $this->consent('progress_photos', true, $this->other);
        $this->upload($this->other)->assertCreated();
        $otherReport = $this->report($this->other);

        $this->api()->deleteJson('/api/v1/me/ai/data')->assertOk()
            ->assertJsonPath('data.deleted', ['photos' => 1, 'reports' => 2])
            ->assertJsonPath('data.progress_photos.accepted', false)
            ->assertJsonPath('data.ai_coach.accepted', false);

        Storage::disk('local')->assertMissing($path);
        $this->assertNull($this->member->fresh()->ai_consent_at);
        $this->assertSame(0, AiReport::withoutGlobalScopes()->where('member_id', $this->member->id)->whereNotNull('content')->count());
        $this->assertSame('Private', $otherReport->fresh()->content);
        $this->assertSame(1, ProgressPhoto::withoutGlobalScopes()->where('member_id', $this->other->id)->count());
        $this->api($this->other)->getJson('/api/v1/me/consents')->assertJsonPath('data.progress_photos.accepted', true);
    }
}
