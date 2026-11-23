<?php

namespace Tests\Feature;

use App\Livewire\PublicSite\MemberRegistrationForm;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MemberSelfRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_qr_form_creates_a_member_without_membership(): void
    {
        $gym = $this->gymOf($this->createGymOwner());

        $this->get(route('gym.register', $gym->join_token))->assertOk()->assertSee($gym->name)->assertSee('Member registration');

        Livewire::test(MemberRegistrationForm::class, ['gym' => $gym])
            ->set('name', 'Self Joiner')
            ->set('phone', '9876543210')
            ->set('gender', 'female')
            ->set('fitness_goal', 'Weight loss')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('memberCode', 'M0001');

        $member = Member::forGym($gym)->sole();
        $this->assertSame('Self Joiner', $member->name);
        $this->assertSame('active', $member->status->value);
        $this->assertSame(0, $member->memberships()->count());
        $this->assertSame('none', $member->membershipState());
    }

    public function test_duplicate_phone_is_rejected(): void
    {
        $gym = $this->gymOf($this->createGymOwner());
        $this->createMember($gym, ['phone' => '9876543210']);

        Livewire::test(MemberRegistrationForm::class, ['gym' => $gym])
            ->set('name', 'Again')
            ->set('phone', '+91 98765 43210')
            ->call('submit')
            ->assertHasErrors('phone')
            ->assertSet('memberCode', null);

        $this->assertSame(1, Member::forGym($gym)->count());
    }

    public function test_invalid_token_and_inactive_gym_return_404(): void
    {
        $gym = $this->gymOf($this->createGymOwner());

        $this->get('/register/'.str_repeat('x', 40))->assertNotFound();

        $gym->forceFill(['status' => 'suspended', 'is_active' => false])->save();
        $this->get(route('gym.register', $gym->join_token))->assertNotFound();
    }

    public function test_attendance_page_shows_the_registration_qr(): void
    {
        $owner = $this->createGymOwner();
        $gym = $this->gymOf($owner);
        $this->actingAs($owner);

        $this->get(route('gym.attendance.index'))->assertOk()->assertSee($gym->registerUrl(), false);
        $this->get(route('gym.attendance.register-qr'))->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    }

    public function test_qr_codes_page_is_linked_in_sidebar_and_shows_only_registration_code(): void
    {
        $owner = $this->createGymOwner();
        $gym = $this->gymOf($owner);
        $this->actingAs($owner);

        $this->get(route('gym.dashboard'))->assertOk()->assertSee(route('gym.qr-codes'), false);
        $this->get(route('gym.qr-codes'))->assertOk()
            ->assertSee('Member registration')
            ->assertSee($gym->registerUrl(), false)
            ->assertDontSee($gym->joinUrl(), false);
    }
}
