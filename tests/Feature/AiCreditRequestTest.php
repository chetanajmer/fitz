<?php

namespace Tests\Feature;

use App\Livewire\Gym\AiCoach as GymAiCoach;
use App\Livewire\Platform\AiCoach as PlatformAiCoach;
use App\Livewire\Portal\Progress;
use App\Models\AiCreditRequest;
use App\Models\AiReport;
use App\Models\Gym;
use App\Models\GymAiSetting;
use App\Models\Member;
use App\Models\MemberNotification;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\AiAccessService;
use App\Services\AiCreditRequestService;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class AiCreditRequestTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Gym $gym;

    protected Member $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-20 10:00'));
        PlatformSetting::put(['ai_enabled' => '1']);

        $this->owner = $this->createGymOwner('Credit Gym');
        $this->gym = $this->gymOf($this->owner);
        $this->member = $this->createMember($this->gym, ['phone' => '9000000777', 'email' => 'credits@example.test'], ['portal_password' => 'password']);
        $this->inGym($this->gym, fn () => app(MembershipService::class)->assign($this->member, $this->createPlan($this->gym), now()->toDateString()));
        $this->member->forceFill(['ai_consent_at' => now()])->save();
        $this->inGym($this->gym, fn () => GymAiSetting::create(['allowed' => true, 'enabled' => true, 'monthly_credits' => 100, 'member_ceiling' => 4, 'member_limit' => 4]));
    }

    protected function useCredits(int $credits): void
    {
        $this->inGym($this->gym, fn () => AiReport::create(['member_id' => $this->member->id, 'type' => AiReport::PROGRESS, 'status' => AiReport::COMPLETED, 'credits' => $credits, 'requested_by' => 'member']));
    }

    protected function memberApi(): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->member->createToken('app', ['member'])->plainTextToken);
    }

    protected function superAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        return $admin;
    }

    public function test_new_gyms_get_100_credits_and_members_4(): void
    {
        $other = $this->gymOf($this->createGymOwner('Fresh Gym'));
        $defaults = app(AiAccessService::class)->defaultsFor($other);

        $this->assertSame(100, $defaults->monthly_credits);
        $this->assertSame(4, $defaults->memberMonthlyLimit());
    }

    public function test_member_out_of_credits_asks_the_gym_and_the_admin_approves(): void
    {
        $this->memberApi()->getJson('/api/v1/me/ai')->assertJsonPath('data.can_request_credits', true)->assertJsonPath('data.out_of_credits', false);

        $this->useCredits(4);
        $this->memberApi()->getJson('/api/v1/me/ai')->assertJsonPath('data.can_request_credits', true)
            ->assertJsonPath('data.out_of_credits', true)->assertJsonPath('data.credits.remaining', 0);

        $this->memberApi()->postJson('/api/v1/me/ai/credit-requests', ['credits' => 6, 'note' => 'Photo comparison please'])->assertCreated()
            ->assertJsonPath('data.can_request_credits', false)
            ->assertJsonPath('data.credits.pending_request.credits', 6);
        $this->memberApi()->postJson('/api/v1/me/ai/credit-requests', ['credits' => 6])->assertStatus(409)->assertJsonPath('code', 'REQUEST_PENDING');

        $request = AiCreditRequest::withoutGlobalScopes()->sole();
        $this->assertSame($this->gym->id, $request->gym_id);
        $this->assertSame('Photo comparison please', $request->note);

        $this->actingAs($this->owner);
        $this->get(route('gym.ai-coach'))->assertOk()->assertSee('Member credit requests')->assertSee('Photo comparison please');
        Livewire::test(GymAiCoach::class)->set("grant.{$request->id}", 5)->call('approveMemberRequest', $request->id)->assertHasNoErrors();

        $this->assertSame(AiCreditRequest::APPROVED, $request->fresh()->status);
        $this->assertSame(5, $request->fresh()->granted_credits);
        $this->assertSame(1, MemberNotification::withoutGlobalScopes()->where('type', 'ai_credits_decided')->count());

        $this->memberApi()->getJson('/api/v1/me/ai')
            ->assertJsonPath('data.credits.limit', 9)
            ->assertJsonPath('data.credits.remaining', 5)
            ->assertJsonPath('data.credits.extra', 5)
            ->assertJsonPath('data.credits.pending_request', null);
    }

    public function test_extra_member_credits_last_only_for_the_month_they_were_given(): void
    {
        $this->useCredits(4);
        $this->memberApi()->postJson('/api/v1/me/ai/credit-requests', ['credits' => 3])->assertCreated();
        $this->actingAs($this->owner);
        Livewire::test(GymAiCoach::class)->call('approveMemberRequest', AiCreditRequest::withoutGlobalScopes()->sole()->id)->assertHasNoErrors();

        $this->memberApi()->getJson('/api/v1/me/ai')->assertJsonPath('data.credits.limit', 7);

        $this->travelTo(Carbon::parse('2026-12-02 10:00'));
        $this->memberApi()->getJson('/api/v1/me/ai')->assertJsonPath('data.credits.limit', 4)->assertJsonPath('data.credits.remaining', 4);
    }

    public function test_admin_can_decline_and_cannot_answer_twice(): void
    {
        $this->useCredits(4);
        $this->memberApi()->postJson('/api/v1/me/ai/credit-requests', ['credits' => 2])->assertCreated();
        $id = AiCreditRequest::withoutGlobalScopes()->sole()->id;

        $this->actingAs($this->owner);
        Livewire::test(GymAiCoach::class)->call('rejectMemberRequest', $id)->assertHasNoErrors()
            ->call('approveMemberRequest', $id)->assertHasErrors("grant.{$id}");

        $this->assertSame(AiCreditRequest::REJECTED, AiCreditRequest::withoutGlobalScopes()->find($id)->status);
        $this->memberApi()->getJson('/api/v1/me/ai')->assertJsonPath('data.credits.limit', 4)->assertJsonPath('data.can_request_credits', true);
    }

    public function test_another_gym_cannot_see_or_answer_the_request(): void
    {
        $this->useCredits(4);
        $id = $this->inGym($this->gym, fn () => app(AiCreditRequestService::class)->requestForMember($this->member, 2, 'Photo note'))->id;

        $otherOwner = $this->createGymOwner('Other Gym');
        $this->actingAs($otherOwner);
        $this->get(route('gym.ai-coach'))->assertOk()->assertDontSee('Photo note');
        Livewire::test(GymAiCoach::class)->call('approveMemberRequest', $id)->assertNotFound();

        $this->assertTrue(AiCreditRequest::withoutGlobalScopes()->find($id)->isPending());
    }

    public function test_member_requests_from_the_portal_page(): void
    {
        $this->useCredits(4);
        $this->actingAs($this->member->user);

        Livewire::test(Progress::class)
            ->assertSee('Out of AI credits?')
            ->set('creditsWanted', 50)->call('requestCredits')->assertHasErrors('creditsWanted')
            ->set('creditsWanted', 4)->set('creditNote', 'Need one more report')->call('requestCredits')->assertHasNoErrors()
            ->assertSee('Waiting for their answer');

        $this->assertSame(4, AiCreditRequest::withoutGlobalScopes()->sole()->credits);
    }

    public function test_gym_admin_asks_fitnix_for_more_credits_and_super_admin_approves(): void
    {
        $this->actingAs($this->owner);
        Livewire::test(GymAiCoach::class)
            ->set('poolCredits', 5)->call('requestPoolCredits')->assertHasErrors('poolCredits')
            ->set('poolCredits', 200)->set('poolNote', 'Transformation challenge')->call('requestPoolCredits')->assertHasNoErrors()
            ->assertSee('Waiting for their answer')
            ->call('requestPoolCredits')->assertHasErrors('poolCredits');

        $request = AiCreditRequest::withoutGlobalScopes()->sole();
        $this->assertNull($request->member_id);

        $this->actingAs($this->superAdmin());
        $this->get(route('platform.ai-coach'))->assertOk()->assertSee('Gym credit requests')->assertSee('Transformation challenge');
        Livewire::test(PlatformAiCoach::class)->call('approveGymRequest', $request->id)->assertHasNoErrors();

        $access = app(AiAccessService::class);
        $this->assertSame(300, $access->gymPool($access->settingsFor($this->gym)));

        $this->actingAs($this->owner);
        Livewire::test(GymAiCoach::class)->assertSee('200 added')->assertSee('0 / 300');
    }

    public function test_super_admin_can_decline_a_gym_request(): void
    {
        $this->actingAs($this->owner);
        Livewire::test(GymAiCoach::class)->set('poolCredits', 50)->call('requestPoolCredits')->assertHasNoErrors();
        $id = AiCreditRequest::withoutGlobalScopes()->sole()->id;

        $this->actingAs($this->superAdmin());
        Livewire::test(PlatformAiCoach::class)->call('rejectGymRequest', $id)->assertHasNoErrors();

        $access = app(AiAccessService::class);
        $this->assertSame(100, $access->gymPool($access->settingsFor($this->gym)));
        $this->actingAs($this->owner);
        Livewire::test(GymAiCoach::class)->assertSee('Declined')->assertSee('Send request');
    }

    public function test_gym_admin_can_ask_fitnix_even_before_ai_is_allowed(): void
    {
        $this->inGym($this->gym, fn () => GymAiSetting::query()->update(['allowed' => false]));
        $this->actingAs($this->owner);

        Livewire::test(GymAiCoach::class)->assertSee('Ask Fitnix to switch on AI coach')->call('requestPoolCredits')->assertHasNoErrors();
        $this->assertSame(100, AiCreditRequest::withoutGlobalScopes()->sole()->credits);
    }

    public function test_member_with_credits_left_can_still_ask_for_more(): void
    {
        $this->actingAs($this->member->user);

        Livewire::test(Progress::class)->assertSee('Need more AI credits?')
            ->set('creditsWanted', 2)->call('requestCredits')->assertHasNoErrors()->assertSee('Waiting for their answer');
    }
}
