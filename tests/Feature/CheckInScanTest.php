<?php

namespace Tests\Feature;

use App\Livewire\Gym\CheckInScreen;
use App\Livewire\Portal\Dashboard;
use App\Models\Attendance;
use App\Models\Gym;
use App\Models\Member;
use App\Services\CheckInCodeService;
use App\Services\GymProfileService;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class CheckInScanTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected Member $member;

    protected CheckInCodeService $codes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gym = $this->gymOf($this->createGymOwner());
        $this->member = $this->createMember($this->gym, ['phone' => '9000000501', 'email' => 'scan@example.test'], ['portal_password' => 'password']);
        $this->inGym($this->gym, fn () => app(MembershipService::class)->assign($this->member, $this->createPlan($this->gym)));
        $this->codes = app(CheckInCodeService::class);
    }

    protected function useScreen(): void
    {
        app(GymProfileService::class)->setCheckinQrMode($this->gym, CheckInCodeService::SCREEN);
    }

    protected function attendanceCount(): int
    {
        return Attendance::withoutGlobalScopes()->count();
    }

    public function test_member_checks_in_and_out_by_scanning_the_printed_qr(): void
    {
        $this->actingAs($this->member->user);
        $scanned = $this->codes->url($this->gym->checkin_token);

        Livewire::test(Dashboard::class)
            ->call('checkIn', $scanned)->assertHasNoErrors()->assertSee('Checked in at')
            ->call('checkOut', 'https://example.test/?checkin=wrong')->assertHasErrors('member')
            ->call('checkOut', $scanned)->assertHasNoErrors()->assertSee('Visit done today');
    }

    public function test_wrong_codes_do_not_check_in(): void
    {
        $other = $this->gymOf($this->createGymOwner());
        $this->actingAs($this->member->user);

        foreach (['', 'nonsense', $this->gym->registerUrl(), $this->gym->register_token, $this->codes->url($other->checkin_token), $this->codes->url($this->codes->screenCode($this->gym)), 'x?checkin[]=a'] as $scanned) {
            Livewire::test(Dashboard::class)->call('checkIn', $scanned)->assertHasErrors('member');
        }
        $this->assertSame(0, $this->attendanceCount());
    }

    public function test_screen_code_expires_and_replaces_the_printed_one(): void
    {
        $this->useScreen();
        $this->travelTo(now()->startOfMinute());
        $old = $this->codes->url($this->codes->screenCode($this->gym));
        $this->actingAs($this->member->user);

        Livewire::test(Dashboard::class)->call('checkIn', $this->codes->url($this->gym->checkin_token))->assertHasErrors('member');

        $this->travel(61)->seconds();
        Livewire::test(Dashboard::class)->call('checkIn', $old)->assertHasErrors('member');
        $this->assertSame(0, $this->attendanceCount());

        $previous = $this->codes->url($this->codes->screenCode($this->gym));
        $this->travel(30)->seconds();
        $this->assertNotSame($previous, $this->codes->url($this->codes->screenCode($this->gym)));
        Livewire::test(Dashboard::class)->call('checkIn', $previous)->assertHasNoErrors();
    }

    public function test_replacing_the_qr_stops_the_old_one(): void
    {
        $old = $this->gym->checkin_token;
        $this->actingAs($this->gym->owner);
        app(GymProfileService::class)->regenerateCheckinToken($this->gym);

        $this->assertFalse($this->codes->accepts($this->gym->fresh(), $old));
        $this->assertTrue($this->codes->accepts($this->gym->fresh(), $this->gym->fresh()->checkin_token));
    }

    public function test_gym_chooses_the_qr_and_opens_the_screen(): void
    {
        $this->actingAs($this->gym->owner);
        $this->get(route('gym.check-in-screen'))->assertOk()->assertSee('uses the printed QR');

        $this->useScreen();

        $this->assertSame(CheckInCodeService::SCREEN, $this->codes->mode($this->gym->fresh()));
        Livewire::test(CheckInScreen::class)->assertSee('changes every 30 seconds');
        $this->get(route('gym.attendance.check-in-qr'))->assertOk()->assertHeader('Content-Type', 'image/svg+xml');

        $this->actingAs($this->member->user);
        $this->get(route('gym.check-in-screen'))->assertForbidden();
    }

    public function test_api_accepts_a_code_but_does_not_require_it(): void
    {
        Sanctum::actingAs($this->member, ['*'], 'member');
        $this->postJson('/api/v1/me/check-in', ['code' => 'wrong'])->assertStatus(422)->assertJsonPath('code', 'INVALID_CHECKIN_CODE');
        $this->assertSame(0, $this->attendanceCount());

        $this->postJson('/api/v1/me/check-in', ['code' => $this->codes->url($this->gym->checkin_token)])->assertCreated();
        $this->postJson('/api/v1/me/check-out', ['code' => 'wrong'])->assertStatus(422)->assertJsonPath('code', 'INVALID_CHECKIN_CODE');
        $this->postJson('/api/v1/me/check-out')->assertOk();
    }
}
