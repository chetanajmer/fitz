<?php

namespace Tests\Feature;

use App\Livewire\Gym\AiCoach as GymAiCoach;
use App\Livewire\Gym\Members\AiProgress;
use App\Livewire\Platform\AiCoach as PlatformAiCoach;
use App\Models\AiReport;
use App\Models\Gym;
use App\Models\GymAiSetting;
use App\Models\Member;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Ai\OpenRouterClient;
use App\Services\BodyProgressService;
use App\Services\MembershipService;
use App\Services\TrainerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AiCoachPanelTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Gym $gym;

    protected Member $member;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['services.openrouter.key' => 'test-key']);
        Http::fake(['openrouter.ai/*' => Http::response(['model' => 'anthropic/claude-haiku-5.5', 'choices' => [['message' => ['content' => json_encode(['summary' => 'Great consistency.', 'direction' => 'improved', 'change_level' => 'slight', 'changes' => [['area' => 'Waist', 'observation' => 'Looks leaner']], 'numbers' => 'Waist down 3 cm.', 'photo_quality' => 'good', 'photo_tips' => ['Same light'], 'wins' => ['Four visits a week'], 'focus_areas' => [['title' => 'Weigh in', 'why' => 'Last one was in May.', 'tip' => 'Every Monday']], 'missing' => ['A weight goal'], 'motivation' => 'Keep going!', 'confidence' => 'medium'])]]], 'usage' => ['cost' => 0.001]])]);
        PlatformSetting::put(['ai_enabled' => '1']);

        $this->owner = $this->createGymOwner('Panel Gym');
        $this->gym = $this->gymOf($this->owner);
        $this->member = $this->createMember($this->gym);
        $this->inGym($this->gym, fn () => app(MembershipService::class)->assign($this->member, $this->createPlan($this->gym), now()->toDateString()));
        $this->member->forceFill(['ai_consent_at' => now()])->save();
    }

    protected function superAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        return $admin;
    }

    public function test_super_admin_allows_a_gym_and_a_lower_ceiling_caps_the_gym_limit(): void
    {
        $this->actingAs($this->superAdmin());
        Livewire::test(PlatformAiCoach::class)
            ->assertSee('Panel Gym')->assertSee('Not allowed')
            ->call('editGym', $this->gym->id)
            ->set('gymForm.allowed', true)
            ->set('gymForm.monthly_credits', '50')
            ->set('gymForm.member_ceiling', '3')
            ->call('saveGym')
            ->assertHasNoErrors()
            ->set('settings.ai_model', 'not a model')
            ->call('save')
            ->assertHasErrors('settings.ai_model');

        $settings = GymAiSetting::forGym($this->gym)->sole();
        $this->assertTrue($settings->allowed);
        $this->assertSame(50, $settings->monthly_credits);
        $this->assertSame(3, $settings->memberMonthlyLimit());
    }

    /** OpenRouter as seen by the AI Coach page: "good-key" is valid, one suitable model and one that cannot read images. */
    protected function fakeOpenRouterCatalog(): void
    {
        Http::swap(new HttpFactory);
        Http::preventStrayRequests();
        Http::fake([
            'openrouter.ai/api/v1/key' => fn (Request $request) => $request->hasHeader('Authorization', 'Bearer good-key-0000000000000000')
                ? Http::response(['data' => ['label' => 'x']]) : Http::response(['error' => 'No auth'], 401),
            'openrouter.ai/api/v1/models' => Http::response(['data' => [
                ['id' => 'openai/gpt-6-luna', 'architecture' => ['input_modalities' => ['image', 'text']]],
                ['id' => 'text/only', 'architecture' => ['input_modalities' => ['text']]],
                ['id' => 'no/reasoning', 'architecture' => ['input_modalities' => ['image', 'text']]],
            ]]),
            'openrouter.ai/api/v1/endpoints/zdr' => Http::response(['data' => [
                ['model_id' => 'openai/gpt-6-luna', 'supported_parameters' => ['structured_outputs', 'reasoning']],
                ['model_id' => 'no/reasoning', 'supported_parameters' => ['structured_outputs']],
                ['model_id' => 'text/only', 'supported_parameters' => ['structured_outputs', 'reasoning']],
            ]]),
        ]);
    }

    public function test_super_admin_saves_a_checked_api_key_that_is_stored_encrypted_and_used(): void
    {
        $this->fakeOpenRouterCatalog();
        config(['services.openrouter.key' => 'env-key']);
        $client = app(OpenRouterClient::class);
        $this->actingAs($this->superAdmin());

        Livewire::test(PlatformAiCoach::class)
            ->assertSee('OPENROUTER_API_KEY from .env')
            ->set('apiKey', 'wrong-key-000000000000000')->call('save')->assertHasErrors('apiKey')
            ->set('settings.ai_model', 'text/only')->set('apiKey', '')->call('save')->assertHasErrors('settings.ai_model')
            ->set('settings.ai_model', 'no/reasoning')->call('save')->assertHasErrors('settings.ai_model')
            ->set('settings.ai_model', 'openai/gpt-6-luna')->set('apiKey', 'good-key-0000000000000000')->call('save')
            ->assertHasNoErrors()->assertSet('apiKey', '')->assertSee('ending in')->assertDontSee('good-key-0000000000000000');

        $stored = PlatformSetting::query()->where('key', OpenRouterClient::KEY_SETTING)->value('value');
        $this->assertStringNotContainsString('good-key', $stored);
        $this->assertSame('good-key-0000000000000000', $client->apiKey());

        // Saving other settings keeps the key; removing it goes back to .env.
        Livewire::test(PlatformAiCoach::class)->set('settings.ai_credits_progress', '2')->call('save')->assertHasNoErrors();
        $this->assertSame('good-key-0000000000000000', $client->apiKey());
        Livewire::test(PlatformAiCoach::class)->call('removeKey');
        $this->assertSame('env-key', $client->apiKey());
    }

    public function test_nothing_is_saved_when_openrouter_cannot_be_reached(): void
    {
        Http::swap(new HttpFactory);
        Http::fake(['openrouter.ai/*' => Http::response('down', 503)]);
        $this->actingAs($this->superAdmin());

        Livewire::test(PlatformAiCoach::class)->set('settings.ai_model', 'openai/gpt-6-luna')->set('settings.ai_credits_progress', '3')
            ->call('save')->assertHasErrors('settings.ai_model');
        $this->assertSame('1', PlatformSetting::get('ai_credits_progress'));
    }

    public function test_only_the_super_admin_can_open_the_ai_settings(): void
    {
        $this->actingAs($this->owner)->get(route('platform.ai-coach'))->assertForbidden();
    }

    public function test_gym_admin_switches_ai_on_within_the_super_admin_ceiling(): void
    {
        $this->inGym($this->gym, fn () => GymAiSetting::create(['allowed' => true, 'monthly_credits' => 100, 'member_ceiling' => 5]));

        $this->actingAs($this->owner)->get(route('gym.ai-coach'))->assertOk()->assertSee('AI Coach');
        Livewire::test(GymAiCoach::class)
            ->set('enabled', true)
            ->set('memberLimit', 6)
            ->call('save')
            ->assertHasErrors('memberLimit')
            ->set('memberLimit', 5)
            ->set('eligibility', 'plans')
            ->call('save')
            ->assertHasErrors('planIds')
            ->set('eligibility', 'all')
            ->call('save')
            ->assertHasNoErrors();

        $settings = GymAiSetting::forGym($this->gym)->sole();
        $this->assertTrue($settings->enabled);
        $this->assertSame(5, $settings->member_limit);
        $this->assertTrue($settings->allowed); // the gym admin cannot change the Super Admin's fields
    }

    public function test_staff_record_progress_and_request_a_report_for_a_member(): void
    {
        $this->inGym($this->gym, fn () => GymAiSetting::create(['allowed' => true, 'enabled' => true, 'monthly_credits' => 100, 'member_ceiling' => 4, 'member_limit' => 4]));
        $this->actingAs($this->owner);

        Livewire::test(AiProgress::class, ['member' => $this->member])
            ->set('measurement.waist_cm', '91')
            ->call('saveMeasurement')
            ->assertHasNoErrors()
            ->set('photo', UploadedFile::fake()->image('front.jpg'))
            ->call('uploadPhoto')
            ->assertHasNoErrors()
            ->call('requestReport', 'progress')
            ->assertHasNoErrors()
            ->assertSee('Great consistency.');

        $report = AiReport::forGym($this->gym)->sole();
        $this->assertSame('staff', $report->requested_by);
        $this->assertSame($this->owner->id, $report->requested_by_user);
        $this->assertSame(91.0, $this->member->measurements()->sole()->waist_cm);

        $photo = $this->member->progressPhotos()->sole();
        $this->get(route('gym.members.progress-photo', [$this->member, $photo->id]))->assertOk();
    }

    public function test_limit_errors_show_in_the_panel(): void
    {
        $this->inGym($this->gym, fn () => GymAiSetting::create(['allowed' => true, 'enabled' => true, 'monthly_credits' => 0]));
        $this->actingAs($this->owner);

        Livewire::test(AiProgress::class, ['member' => $this->member])
            ->call('requestReport', 'progress')
            ->assertHasErrors('report');

        $this->assertSame(0, AiReport::forGym($this->gym)->count());
    }

    public function test_trainers_need_ai_access_and_only_see_their_members(): void
    {
        $trainer = $this->createTrainer($this->gym);
        $photo = $this->inGym($this->gym, fn () => app(BodyProgressService::class)->addPhoto($this->member, UploadedFile::fake()->image('a.jpg'), 'front'));
        $photoUrl = route('gym.members.progress-photo', [$this->member, $photo->id]);

        $this->actingAs($trainer)->get(route('gym.ai-coach'))->assertForbidden();
        $this->get($photoUrl)->assertForbidden();

        $this->inGym($this->gym, fn () => app(TrainerService::class)->setPermissions($trainer, ['members.view', 'ai.view']));
        $trainer->refresh()->unsetRelation('permissions');
        $this->actingAs($trainer)->get($photoUrl)->assertForbidden(); // not their member

        $this->member->forceFill(['trainer_id' => $trainer->id])->save();
        $this->actingAs($trainer)->get($photoUrl)->assertOk();
        $this->get(route('gym.members.show', ['member' => $this->member, 'tab' => 'ai']))->assertOk()->assertSee('AI Progress');
        $this->get(route('gym.ai-coach'))->assertForbidden(); // settings stay with the gym admin
    }

    public function test_other_gyms_cannot_reach_a_members_photos(): void
    {
        $photo = $this->inGym($this->gym, fn () => app(BodyProgressService::class)->addPhoto($this->member, UploadedFile::fake()->image('a.jpg'), 'front'));
        $otherOwner = $this->createGymOwner('Other Gym');

        $this->actingAs($otherOwner)->get(route('gym.members.progress-photo', [$this->member->id, $photo->id]))->assertNotFound();
    }

    public function test_monthly_command_requests_reports_only_for_eligible_consenting_members(): void
    {
        $this->inGym($this->gym, fn () => GymAiSetting::create(['allowed' => true, 'enabled' => true, 'auto_monthly_report' => true, 'monthly_credits' => 100, 'member_ceiling' => 4, 'member_limit' => 4]));
        $this->createMember($this->gym)->forceFill(['ai_consent_at' => now()])->save(); // consented, no membership
        $this->inGym($this->gym, fn () => app(MembershipService::class)->assign($this->createMember($this->gym), $this->createPlan($this->gym), now()->toDateString())); // no consent

        $this->artisan('gymai:ai-monthly-reports')->expectsOutput('AI progress reports requested: 1.')->assertSuccessful();

        $this->assertSame([$this->member->id], AiReport::forGym($this->gym)->pluck('member_id')->all());
        $this->assertSame('auto', AiReport::forGym($this->gym)->value('requested_by'));
    }
}
