<?php

namespace Tests\Feature;

use App\Enums\EnquiryStatus;
use App\Livewire\PublicSite\EnquiryForm;
use App\Models\Enquiry;
use App\Models\Member;
use App\Services\EnquiryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class EnquiryFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_qr_join_page_creates_an_enquiry_not_a_member(): void
    {
        $gym = $this->gymOf($this->createGymOwner());

        $this->get(route('gym.join', $gym->join_token))->assertOk()->assertSee($gym->name);

        Livewire::test(EnquiryForm::class, ['gym' => $gym, 'source' => 'qr'])
            ->set('name', 'Walk In')
            ->set('phone', '9876543210')
            ->set('fitness_goal', 'Weight loss')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $enquiry = Enquiry::forGym($gym)->sole();
        $this->assertSame(EnquiryStatus::New, $enquiry->status);
        $this->assertSame('qr', $enquiry->source->value);
        $this->assertSame(0, Member::forGym($gym)->count());
    }

    public function test_invalid_join_token_and_inactive_gym_return_404(): void
    {
        $gym = $this->gymOf($this->createGymOwner());

        $this->get('/join/'.str_repeat('x', 40))->assertNotFound();

        $gym->forceFill(['status' => 'suspended', 'is_active' => false])->save();
        $this->get(route('gym.join', $gym->join_token))->assertNotFound();
        $this->get(route('gym.public', $gym->slug))->assertNotFound();
    }

    public function test_enquiry_converts_once_and_keeps_history(): void
    {
        $owner = $this->createGymOwner();
        $gym = $this->gymOf($owner);
        $plan = $this->createPlan($gym);
        $this->actingAs($owner);

        $service = app(EnquiryService::class);
        $enquiry = $service->create(['name' => 'Lead One', 'phone' => '9123456789', 'interested_plan_id' => $plan->id]);
        $service->changeStatus($enquiry, EnquiryStatus::Interested, 'Visited the gym');

        $member = $service->convert($enquiry->fresh(), ['joining_date' => now()->toDateString()], [
            'plan_id' => $plan->id, 'payment' => ['amount' => 400, 'method' => 'upi'],
        ]);

        $enquiry->refresh();
        $this->assertSame(EnquiryStatus::Converted, $enquiry->status);
        $this->assertSame($member->id, $enquiry->converted_member_id);
        $this->assertSame($enquiry->id, $member->enquiry_id);
        $this->assertSame('Lead One', $member->name);
        $this->assertSame(1, $member->memberships()->count());
        $this->assertEquals(600.0, $member->pendingAmount());
        $this->assertSame(['created', 'status_changed', 'converted'], $enquiry->activities()->oldest('id')->pluck('type')->all());

        $this->expectException(ValidationException::class);
        $service->convert($enquiry, ['joining_date' => now()->toDateString()]);
    }

    public function test_converted_enquiry_status_cannot_be_changed(): void
    {
        $owner = $this->createGymOwner();
        $this->actingAs($owner);
        $service = app(EnquiryService::class);
        $enquiry = $service->create(['name' => 'Lead', 'phone' => '9123456780']);
        $service->convert($enquiry, ['joining_date' => now()->toDateString()]);

        $this->expectException(ValidationException::class);
        $service->changeStatus($enquiry->fresh(), EnquiryStatus::Contacted);
    }
}
