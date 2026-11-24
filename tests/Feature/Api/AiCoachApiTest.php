<?php

namespace Tests\Feature\Api;

use App\Models\AiReport;
use App\Models\Gym;
use App\Models\GymAiSetting;
use App\Models\Member;
use App\Models\MemberNotification;
use App\Models\PlatformSetting;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiCoachApiTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected Member $member;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['services.openrouter.key' => 'test-key']);
        PlatformSetting::put(['ai_enabled' => '1']);
        $this->fakeOpenRouter();

        $this->gym = $this->gymOf($this->createGymOwner('AI Gym'));
        $this->member = $this->memberWithMembership($this->gym, ['name' => 'Ravi Kumar', 'phone' => '9876543210', 'medical_notes' => 'Asthma']);
        $this->token = $this->member->createToken('app', ['member'])->plainTextToken;
        $this->aiSettings(['allowed' => true, 'enabled' => true, 'monthly_credits' => 100, 'member_ceiling' => 4, 'member_limit' => 4]);
    }

    protected function api(?string $token = null): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->token);
    }

    /** OpenRouter answers with this HTTP status (tests switch it to simulate an outage). */
    protected int $openRouterStatus = 200;

    protected function fakeOpenRouter(): void
    {
        Http::fake(['openrouter.ai/*' => fn () => $this->openRouterStatus === 200
            ? Http::response(['model' => 'openai/gpt-4o-mini', 'choices' => [['message' => ['content' => '## Summary
Great consistency.']]],
                'usage' => ['prompt_tokens' => 900, 'completion_tokens' => 300, 'cost' => 0.0004]])
            : Http::response(['error' => ['message' => 'Provider down']], $this->openRouterStatus)]);
    }

    /** @param  array<string, mixed>  $values */
    protected function aiSettings(array $values): GymAiSetting
    {
        return $this->inGym($this->gym, function () use ($values) {
            $settings = GymAiSetting::firstOrNew();
            $settings->fill($values)->save();

            return $settings;
        });
    }

    /** @param  array<string, mixed>  $attributes */
    protected function memberWithMembership(Gym $gym, array $attributes = [], ?int $planPrice = null): Member
    {
        $member = $this->createMember($gym, $attributes);
        $plan = $this->createPlan($gym, $planPrice ? ['name' => 'Premium', 'price' => $planPrice] : []);
        $this->inGym($gym, fn () => app(MembershipService::class)->assign($member, $plan, now()->toDateString()));

        return $member;
    }

    protected function consent(): void
    {
        $this->api()->postJson('/api/v1/me/ai/consent', ['accepted' => true])->assertOk()->assertJsonPath('data.has_consented', true);
    }

    public function test_member_must_consent_before_a_progress_report_is_generated(): void
    {
        $this->api()->getJson('/api/v1/me/ai')->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.has_consented', false)
            ->assertJsonPath('data.credits.remaining', 4);

        $this->api()->postJson('/api/v1/me/ai/reports', ['type' => 'progress'])
            ->assertForbidden()->assertJsonPath('code', 'AI_CONSENT_REQUIRED');

        $this->consent();
        $this->api()->postJson('/api/v1/me/ai/reports', ['type' => 'progress'])->assertStatus(202)
            ->assertJsonPath('data.report.status', 'completed')
            ->assertJsonPath('data.report.content', "## Summary\nGreat consistency.")
            ->assertJsonPath('data.credits.used', 1)
            ->assertJsonPath('data.credits.remaining', 3);

        $report = AiReport::forGym($this->gym)->sole();
        $this->assertSame(900, $report->input_tokens);
        $this->assertSame(0.0004, $report->cost_usd);
        $this->assertTrue(MemberNotification::withoutGlobalScopes()->where('member_id', $this->member->id)->where('type', 'ai_report_ready')->exists());

        // Private data never leaves the platform, and only non-logging providers are used.
        Http::assertSent(function (Request $request) {
            $body = json_encode($request->data());

            return $request['provider'] === ['data_collection' => 'deny']
                && ! str_contains($body, 'Ravi') && ! str_contains($body, '9876543210') && ! str_contains($body, 'Asthma');
        });

        $this->api()->getJson('/api/v1/me/ai/reports')->assertOk()->assertJsonCount(1, 'data.reports');
        $this->api()->getJson("/api/v1/me/ai/reports/{$report->id}")->assertOk()->assertJsonPath('data.type', 'progress');
    }

    public function test_member_limit_and_gym_pool_stop_requests_and_failed_reports_cost_nothing(): void
    {
        $this->consent();
        $this->aiSettings(['member_limit' => 2]);

        $this->api()->postJson('/api/v1/me/ai/reports', ['type' => 'progress'])->assertStatus(202);
        $this->api()->postJson('/api/v1/me/ai/reports', ['type' => 'progress'])->assertStatus(202);
        $this->api()->postJson('/api/v1/me/ai/reports', ['type' => 'progress'])
            ->assertStatus(429)->assertJsonPath('code', 'AI_LIMIT_REACHED')->assertJsonPath('data.credits.remaining', 0);

        // The Super Admin's ceiling caps the gym's own per-member limit.
        $this->aiSettings(['member_limit' => 10, 'member_ceiling' => 2]);
        $this->api()->postJson('/api/v1/me/ai/reports', ['type' => 'progress'])->assertStatus(429)->assertJsonPath('code', 'AI_LIMIT_REACHED');

        // Gym pool used up (2 of 2 credits) blocks another member too.
        $this->aiSettings(['member_ceiling' => 4, 'monthly_credits' => 2]);
        $other = $this->memberWithMembership($this->gym);
        $other->forceFill(['ai_consent_at' => now()])->save();
        $this->api($other->createToken('app', ['member'])->plainTextToken)->postJson('/api/v1/me/ai/reports', ['type' => 'progress'])
            ->assertStatus(429)->assertJsonPath('code', 'AI_GYM_LIMIT_REACHED');

        // A failed generation releases its credits.
        $this->aiSettings(['monthly_credits' => 100, 'member_limit' => 3]);
        $this->openRouterStatus = 500;
        $this->api()->postJson('/api/v1/me/ai/reports', ['type' => 'progress'])->assertStatus(202)
            ->assertJsonPath('data.report.status', 'failed')
            ->assertJsonPath('data.credits.used', 2);
    }

    public function test_super_admin_and_gym_admin_switches_and_eligibility(): void
    {
        $this->consent();

        PlatformSetting::put(['ai_enabled' => '0']);
        $this->api()->postJson('/api/v1/me/ai/reports', ['type' => 'progress'])->assertForbidden()->assertJsonPath('code', 'AI_DISABLED');
        PlatformSetting::put(['ai_enabled' => '1']);

        $this->aiSettings(['allowed' => false]);
        $this->api()->postJson('/api/v1/me/ai/reports', ['type' => 'progress'])->assertForbidden()->assertJsonPath('code', 'AI_NOT_AVAILABLE');
        $this->aiSettings(['allowed' => true, 'enabled' => false]);
        $this->api()->getJson('/api/v1/me/ai')->assertJsonPath('data.available', false);
        $this->api()->postJson('/api/v1/me/ai/reports', ['type' => 'progress'])->assertForbidden()->assertJsonPath('code', 'AI_NOT_AVAILABLE');

        // Only members on the Premium plan.
        $premium = $this->memberWithMembership($this->gym, [], 3000);
        $premiumPlanId = $this->inGym($this->gym, fn () => $premium->currentMembership()->plan_id);
        $this->aiSettings(['enabled' => true, 'eligibility' => 'plans', 'eligible_plan_ids' => [$premiumPlanId]]);
        $this->api()->postJson('/api/v1/me/ai/reports', ['type' => 'progress'])->assertForbidden()->assertJsonPath('code', 'AI_NOT_ELIGIBLE');

        // Hand-picked members.
        $this->aiSettings(['eligibility' => 'selected']);
        $this->api()->postJson('/api/v1/me/ai/reports', ['type' => 'progress'])->assertForbidden()->assertJsonPath('code', 'AI_NOT_ELIGIBLE');
        $this->member->forceFill(['ai_access' => true])->save();
        $this->api()->postJson('/api/v1/me/ai/reports', ['type' => 'progress'])->assertStatus(202);

        // No valid membership = not eligible.
        $noMembership = $this->createMember($this->gym);
        $noMembership->forceFill(['ai_consent_at' => now(), 'ai_access' => true])->save();
        $this->api($noMembership->createToken('app', ['member'])->plainTextToken)->postJson('/api/v1/me/ai/reports', ['type' => 'progress'])
            ->assertForbidden()->assertJsonPath('code', 'AI_NOT_ELIGIBLE');
    }

    public function test_photo_comparison_sends_both_photos_and_costs_two_credits(): void
    {
        $this->consent();
        $before = $this->uploadPhoto('2026-01-01');
        $after = $this->uploadPhoto('2026-03-01');

        $this->api()->postJson('/api/v1/me/ai/reports', ['type' => 'photo_comparison', 'before_photo_id' => $after, 'after_photo_id' => $before])
            ->assertStatus(202)
            ->assertJsonPath('data.report.status', 'completed')
            ->assertJsonPath('data.report.before_photo_id', $before) // always older → newer
            ->assertJsonPath('data.credits.used', 2);

        Http::assertSent(fn (Request $request) => collect($request['messages'][1]['content'])->where('type', 'image_url')->count() === 2);

        $this->aiSettings(['photos_allowed' => false]);
        $this->api()->getJson('/api/v1/me/ai')->assertJsonPath('data.features.photo_comparison.available', false);
        $this->api()->postJson('/api/v1/me/ai/reports', ['type' => 'photo_comparison', 'before_photo_id' => $before, 'after_photo_id' => $after])
            ->assertForbidden()->assertJsonPath('code', 'AI_FEATURE_DISABLED');
    }

    public function test_photos_of_other_members_cannot_be_compared_or_viewed(): void
    {
        $this->consent();
        $mine = $this->uploadPhoto();
        $other = $this->memberWithMembership($this->gym);
        $otherToken = $other->createToken('app', ['member'])->plainTextToken;
        $theirs = $this->uploadPhoto(null, $otherToken);

        $this->api()->postJson('/api/v1/me/ai/reports', ['type' => 'photo_comparison', 'before_photo_id' => $mine, 'after_photo_id' => $theirs])
            ->assertStatus(422);
        $this->api()->get("/api/v1/me/progress-photos/{$theirs}/image")->assertNotFound();
        $this->api()->get("/api/v1/me/progress-photos/{$mine}/image")->assertOk();
        $this->assertSame(0, AiReport::forGym($this->gym)->count());
    }

    public function test_measurements_are_saved_per_day_and_can_be_deleted(): void
    {
        $this->api()->postJson('/api/v1/me/measurements', [])->assertStatus(422);
        $this->api()->postJson('/api/v1/me/measurements', ['waist_cm' => 92, 'body_fat_percent' => 24.5])->assertCreated()
            ->assertJsonCount(1, 'data.measurements')
            ->assertJsonPath('data.measurements.0.waist_cm', 92);
        $id = $this->api()->postJson('/api/v1/me/measurements', ['waist_cm' => 90.5, 'chest_cm' => 101])->assertCreated()
            ->assertJsonCount(1, 'data.measurements')
            ->assertJsonPath('data.measurements.0.waist_cm', 90.5)
            ->json('data.measurements.0.id');

        $this->api()->deleteJson("/api/v1/me/measurements/{$id}")->assertOk()->assertJsonCount(0, 'data.measurements');
    }

    protected function uploadPhoto(?string $takenOn = null, ?string $token = null): int
    {
        return $this->api($token)->post('/api/v1/me/progress-photos', array_filter([
            'photo' => UploadedFile::fake()->image('p.jpg', 300, 400),
            'pose' => 'front',
            'taken_on' => $takenOn,
        ]), ['Accept' => 'application/json'])->assertCreated()->json('data.photos.'.($takenOn === '2026-03-01' ? '1' : '0').'.id');
    }
}
