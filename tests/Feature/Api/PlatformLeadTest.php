<?php

namespace Tests\Feature\Api;

use App\Contracts\SmsSender;
use App\Livewire\Platform\Leads;
use App\Models\Member;
use App\Models\PlatformLead;
use App\Models\User;
use App\Services\MemberOtpService;
use App\Services\MemberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlatformLeadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(SmsSender::class, new class implements SmsSender
        {
            public function send(string $phone, string $message): void {}
        });
    }

    public function test_unknown_number_becomes_a_lead_with_callback_message(): void
    {
        $this->postJson('/api/v1/auth/send-otp', [
            'phone' => '+91 98111 22233', 'name' => 'Rahul', 'city' => 'Pune',
            'latitude' => 18.5204, 'longitude' => 73.8567, 'app_platform' => 'android', 'app_version' => '1.0.0',
        ])
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'NO_GYM_CONNECTED')
            ->assertJsonPath('message', MemberOtpService::NOT_CONNECTED_MESSAGE)
            ->assertJsonPath('data.callback_requested', true);

        $lead = PlatformLead::sole();
        $this->assertSame('9811122233', $lead->phone_digits);
        $this->assertSame('not_registered', $lead->reason->value);
        $this->assertSame('new', $lead->status->value);
        $this->assertSame('Rahul', $lead->name);
        $this->assertSame('Pune', $lead->city);
        $this->assertEqualsWithDelta(18.5204, $lead->latitude, 0.0001);
        $this->assertSame('android', $lead->app_platform);
    }

    public function test_repeat_attempts_update_the_same_open_lead(): void
    {
        $this->postJson('/api/v1/auth/send-otp', ['phone' => '9811122233'])->assertNotFound();
        $this->postJson('/api/v1/auth/send-otp', ['phone' => '09811122233', 'name' => 'Rahul'])->assertNotFound();

        $lead = PlatformLead::sole();
        $this->assertSame(2, $lead->request_count);
        $this->assertSame('Rahul', $lead->name);

        // Once closed, a new attempt opens a fresh lead.
        $lead->forceFill(['status' => 'closed'])->save();
        $this->postJson('/api/v1/auth/send-otp', ['phone' => '9811122233'])->assertNotFound();
        $this->assertSame(2, PlatformLead::count());
    }

    public function test_lead_reason_explains_why_the_number_is_not_connected(): void
    {
        $gym = $this->gymOf($this->createGymOwner('Old Gym'));
        $this->createMember($gym, ['phone' => '9000000101', 'status' => 'inactive']);
        $removed = $this->createMember($gym, ['phone' => '9000000102']);
        $this->inGym($gym, fn () => app(MemberService::class)->delete(Member::find($removed->id)));

        $closedGym = $this->gymOf($this->createGymOwner('Closed Gym'));
        $this->createMember($closedGym, ['phone' => '9000000103']);
        $closedGym->forceFill(['status' => 'deactivated', 'is_active' => false])->save();

        foreach (['9000000101' => 'member_inactive', '9000000102' => 'member_removed', '9000000103' => 'gym_inactive'] as $phone => $reason) {
            $this->postJson('/api/v1/auth/send-otp', ['phone' => $phone])->assertNotFound()->assertJsonPath('code', 'NO_GYM_CONNECTED');

            $lead = PlatformLead::where('phone_digits', $phone)->sole();
            $this->assertSame($reason, $lead->reason->value, $phone);
            $this->assertNotNull($lead->previous_gym_id);
        }

        // The API response never reveals the internal reason.
        $this->postJson('/api/v1/auth/send-otp', ['phone' => '9000000101'])->assertJsonMissingPath('data.reason');
    }

    public function test_active_members_and_wrong_gym_choice_do_not_create_leads(): void
    {
        $gymA = $this->gymOf($this->createGymOwner('A'));
        $gymB = $this->gymOf($this->createGymOwner('B'));
        $this->createMember($gymA, ['phone' => '9000000201']);

        $this->postJson('/api/v1/auth/send-otp', ['phone' => '9000000201'])->assertOk();

        // Picked the wrong gym: plain not-found, no lead (they are a valid member elsewhere).
        $this->travel(1)->minutes();
        $this->postJson('/api/v1/auth/send-otp', ['phone' => '9000000201', 'gym_id' => $gymB->id])
            ->assertNotFound()->assertJsonPath('code', 'MEMBER_NOT_FOUND');

        $this->assertSame(0, PlatformLead::count());
    }

    public function test_leads_are_visible_to_super_admin_only(): void
    {
        $owner = $this->createGymOwner();
        $this->postJson('/api/v1/auth/send-otp', ['phone' => '9811122233', 'city' => 'Pune'])->assertNotFound();

        $this->actingAs($owner)->get(route('platform.leads'))->assertForbidden();

        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin)->get(route('platform.leads'))->assertOk()->assertSee('9811122233');
    }

    public function test_super_admin_works_a_lead_and_connects_it_to_a_gym(): void
    {
        $gym = $this->gymOf($this->createGymOwner('Pune Power Gym'));
        $this->postJson('/api/v1/auth/send-otp', ['phone' => '9811122233'])->assertNotFound();
        $lead = PlatformLead::sole();

        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        Livewire::test(Leads::class)
            ->call('open', $lead->id)
            ->set('form.status', 'converted')
            ->call('save')
            ->assertHasErrors('form.assigned_gym_id')
            ->set('form.assigned_gym_id', (string) $gym->id)
            ->set('form.notes', 'Suggested Pune Power Gym, will visit Monday.')
            ->call('save')
            ->assertHasNoErrors();

        $lead->refresh();
        $this->assertSame('converted', $lead->status->value);
        $this->assertSame($gym->id, $lead->assigned_gym_id);
        $this->assertSame($admin->id, $lead->handled_by);
        $this->assertNotNull($lead->contacted_at);
    }
}
