<?php

namespace Tests\Feature;

use App\Livewire\Portal\CheckIn;
use App\Models\Attendance;
use App\Models\Gym;
use App\Models\Member;
use App\Services\AttendanceService;
use App\Services\CheckInCodeService;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DeskQrCheckInTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected Member $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gym = $this->gymOf($this->createGymOwner());
        $this->member = $this->createMember($this->gym, ['email' => 'desk@example.test'], ['portal_password' => 'password']);
        $this->inGym($this->gym, fn () => app(MembershipService::class)->assign($this->member, $this->createPlan($this->gym)));
    }

    public function test_scanning_the_desk_qr_checks_in_then_checks_out(): void
    {
        $this->actingAs($this->member->user);

        Livewire::test(CheckIn::class)
            ->call('scanDeskQr', $this->gym->registerUrl())
            ->assertHasNoErrors()
            ->assertDispatched('notify')
            ->assertSee('Scan to check out');

        $attendance = Attendance::withoutGlobalScopes()->sole();
        $this->assertSame('desk_qr', $attendance->method);
        $this->assertNull($attendance->check_out_at);

        $this->travel(AttendanceService::MIN_MINUTES_BEFORE_QR_CHECKOUT)->minutes();

        Livewire::test(CheckIn::class)
            ->call('scanDeskQr', $this->gym->registerUrl())
            ->assertHasNoErrors()
            ->assertSee('Visit done today');

        $this->assertNotNull($attendance->fresh()->check_out_at);

        Livewire::test(CheckIn::class)
            ->call('scanDeskQr', $this->gym->registerUrl())
            ->assertHasErrors(['member']);

        $this->assertSame(1, Attendance::withoutGlobalScopes()->count());
    }

    public function test_a_second_scan_right_after_checking_in_does_not_check_out(): void
    {
        $this->actingAs($this->member->user);

        Livewire::test(CheckIn::class)->call('scanDeskQr', $this->gym->registerUrl())->assertHasNoErrors();

        $this->travel(1)->minutes();

        Livewire::test(CheckIn::class)
            ->call('scanDeskQr', $this->gym->registerUrl())
            ->assertHasErrors(['member']);

        $this->assertNull(Attendance::withoutGlobalScopes()->sole()->check_out_at);
    }

    public function test_gym_on_the_live_screen_qr_only_accepts_the_desk_qr_for_checking_out(): void
    {
        $this->gym->forceFill(['settings' => array_merge($this->gym->settings ?? [], ['checkin' => ['qr' => CheckInCodeService::SCREEN]])])->save();
        $this->actingAs($this->member->user);

        Livewire::test(CheckIn::class)
            ->call('scanDeskQr', $this->gym->registerUrl())
            ->assertHasErrors(['member']);

        $this->assertSame(0, Attendance::withoutGlobalScopes()->count());

        $this->inGym($this->gym, fn () => app(AttendanceService::class)->checkIn($this->member, 'qr'));
        $this->travel(30)->minutes();

        Livewire::test(CheckIn::class)
            ->call('scanDeskQr', $this->gym->registerUrl())
            ->assertHasNoErrors();

        $this->assertNotNull(Attendance::withoutGlobalScopes()->sole()->check_out_at);
    }

    public function test_desk_qr_works_even_when_self_check_in_is_switched_off(): void
    {
        $this->gym->forceFill(['settings' => ['attendance' => ['member_self_checkin' => false]]])->save();
        $this->actingAs($this->member->user);

        Livewire::test(CheckIn::class)
            ->call('scanDeskQr', $this->gym->fresh()->register_token)
            ->assertHasNoErrors();

        $this->assertSame(1, Attendance::withoutGlobalScopes()->count());
    }

    public function test_desk_qr_of_another_gym_or_random_qr_is_rejected(): void
    {
        $otherGym = $this->gymOf($this->createGymOwner('Other Gym'));
        $this->actingAs($this->member->user);

        Livewire::test(CheckIn::class)
            ->call('scanDeskQr', $otherGym->registerUrl())
            ->assertHasErrors(['member'])
            ->call('scanDeskQr', 'https://example.com/some-menu')
            ->assertHasErrors(['member']);

        $this->assertSame(0, Attendance::withoutGlobalScopes()->count());
    }

    public function test_member_without_valid_membership_cannot_check_in_with_desk_qr(): void
    {
        $other = $this->createMember($this->gym, ['email' => 'noplan@example.test'], ['portal_password' => 'password']);
        $this->actingAs($other->user);

        Livewire::test(CheckIn::class)
            ->call('scanDeskQr', $this->gym->registerUrl())
            ->assertHasErrors(['member']);

        $this->assertSame(0, Attendance::withoutGlobalScopes()->count());
    }

    public function test_signed_in_member_opening_the_desk_qr_link_is_sent_to_check_in(): void
    {
        $this->actingAs($this->member->user);

        $this->get($this->gym->registerUrl())
            ->assertRedirect(route('portal.check-in', ['desk' => $this->gym->register_token]));

        Livewire::withQueryParams(['desk' => $this->gym->register_token])
            ->test(CheckIn::class)
            ->assertSet('pendingDeskToken', $this->gym->register_token)
            ->assertSee('Recording your visit');
    }

    public function test_guest_opening_the_desk_qr_link_sees_registration_and_member_login(): void
    {
        $this->get($this->gym->registerUrl())
            ->assertOk()
            ->assertSee('Member registration')
            ->assertSee('Already a member? Log in to check in');

        $this->assertSame($this->gym->registerUrl(), session('url.intended'));
    }

    public function test_gym_staff_opening_the_desk_qr_link_still_see_the_form(): void
    {
        $this->actingAs($this->gym->owner);

        $this->get($this->gym->registerUrl())
            ->assertOk()
            ->assertSee('Member registration')
            ->assertDontSee('Already a member? Log in to check in');
    }
}
