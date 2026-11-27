<?php

namespace Tests\Feature\Api;

use App\Models\Attendance;
use App\Models\Gym;
use App\Models\Member;
use App\Services\CheckInCodeService;
use App\Services\GymQrResolver;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class QrCheckInApiTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected Member $member;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://www.fitnix.in', 'app.qr_allowed_hosts' => ['smartai.morethangiftz.com']]);
        URL::forceRootUrl('https://www.fitnix.in');
        $this->travelTo(Carbon::parse('2026-11-23 10:00'));
        $this->gym = $this->gymOf($this->createGymOwner('Iron Den'));
        $this->member = $this->createMember($this->gym, ['phone' => '9000000501']);
        $this->inGym($this->gym, fn () => app(MembershipService::class)->assign($this->member, $this->createPlan($this->gym)));
    }

    protected function api(): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->member->createToken('app', ['member'])->plainTextToken);
    }

    protected function scan(string $qr): TestResponse
    {
        return $this->api()->postJson('/api/v1/me/check-in/qr', ['qr' => $qr]);
    }

    public function test_scanning_the_gym_join_link_checks_the_member_in(): void
    {
        $this->api()->getJson('/api/v1/me/check-in')->assertOk()
            ->assertJsonPath('data.state', 'not_checked_in')
            ->assertJsonPath('data.checked_in_today', false)
            ->assertJsonPath('data.attendance', null)
            ->assertJsonPath('data.has_active_membership', true)
            ->assertJsonPath('data.self_checkin_enabled', true)
            ->assertJsonPath('data.qr_checkin_enabled', true);

        $this->scan($this->gym->joinUrl())->assertOk()
            ->assertJsonPath('message', 'Checked in at Iron Den. Have a great workout!')
            ->assertJsonPath('data.source', 'qr')
            ->assertJsonPath('data.state', 'checked_in')
            ->assertJsonPath('data.checked_in_today', true)
            ->assertJsonPath('data.attendance.source', 'qr')
            ->assertJsonPath('data.attendance.check_out_at', null)
            ->assertJsonPath('data.streak.current_streak', 1);

        $this->assertSame(Attendance::METHOD_GYM_QR, Attendance::withoutGlobalScopes()->sole()->method);
        $this->api()->getJson('/api/v1/me/check-in')->assertJsonPath('data.attendance.source', 'qr');
    }

    public function test_second_scan_on_the_same_day_is_rejected_with_the_current_status(): void
    {
        $this->scan($this->gym->registerUrl())->assertOk();

        $this->scan($this->gym->joinUrl())->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'ALREADY_CHECKED_IN')
            ->assertJsonPath('data.state', 'checked_in')
            ->assertJsonPath('data.checked_in_today', true);

        $this->assertSame(1, Attendance::withoutGlobalScopes()->count());
    }

    public function test_link_variants_all_resolve_to_the_gym(): void
    {
        $resolver = app(GymQrResolver::class);
        $join = $this->gym->joinUrl();
        $path = parse_url($join, PHP_URL_PATH);

        foreach ([
            $join, $join.'/', $join.'?utm_source=poster', $join.'/?a=1#x',
            'http://www.fitnix.in'.$path, 'https://fitnix.in'.$path, 'HTTPS://WWW.FITNIX.IN'.$path,
            'https://smartai.morethangiftz.com'.$path, "  {$join}  ",
            $this->gym->registerUrl(),
            app(CheckInCodeService::class)->url($this->gym->checkin_token),
            $this->gym->checkin_token,
        ] as $qr) {
            $this->assertTrue($resolver->fromUrl($qr)?->is($this->gym) ?? false, "Not resolved: {$qr}");
        }

        foreach ([
            'https://evil.example.com'.$path, 'ftp://www.fitnix.in'.$path, 'https://www.fitnix.in/join/'.str_repeat('a', 40),
            'https://www.fitnix.in/iron-den', 'hello', '',
        ] as $qr) {
            $this->assertNull($resolver->fromUrl($qr), "Should not resolve: {$qr}");
        }
    }

    public function test_another_gyms_link_is_wrong_gym(): void
    {
        Log::spy();
        $other = $this->gymOf($this->createGymOwner('Other Gym'));

        $this->scan($other->joinUrl())->assertForbidden()
            ->assertJsonPath('code', 'WRONG_GYM')->assertJsonPath('message', 'This QR belongs to another gym.');

        $this->assertSame(0, Attendance::withoutGlobalScopes()->count());
        Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context) => $context['reason'] === 'WRONG_GYM' && $context['member_id'] === $this->member->id)->once();
    }

    public function test_random_urls_are_invalid(): void
    {
        $this->scan('https://www.google.com/search?q=gym')->assertStatus(422)->assertJsonPath('code', 'INVALID_QR');
        $this->scan('WIFI:S:IronDen;T:WPA;P:secret;;')->assertStatus(422)->assertJsonPath('code', 'INVALID_QR');
        $this->scan('https://evil.example.com'.parse_url($this->gym->joinUrl(), PHP_URL_PATH))->assertStatus(422)->assertJsonPath('code', 'INVALID_QR');
        $this->api()->postJson('/api/v1/me/check-in/qr', [])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_expired_membership_cannot_check_in(): void
    {
        $this->member->memberships()->withoutGlobalScopes()->update(['status' => 'cancelled']);

        $this->scan($this->gym->joinUrl())->assertForbidden()->assertJsonPath('code', 'NO_ACTIVE_MEMBERSHIP');
    }

    public function test_gym_settings_can_turn_qr_check_in_off_or_limit_it_to_opening_hours(): void
    {
        $this->setAttendance(['qr_checkin' => false]);
        $this->scan($this->gym->joinUrl())->assertForbidden()->assertJsonPath('code', 'QR_CHECKIN_DISABLED');
        $this->api()->getJson('/api/v1/me/check-in')->assertJsonPath('data.qr_checkin_enabled', false);

        $this->setAttendance(['member_self_checkin' => false]);
        $this->scan($this->gym->joinUrl())->assertForbidden()->assertJsonPath('code', 'SELF_CHECKIN_DISABLED');

        // Sunday is closed in the default opening hours.
        $this->setAttendance(['checkin_hours_only' => true]);
        $this->travelTo(Carbon::parse('2026-11-29 10:00'));
        $this->scan($this->gym->joinUrl())->assertForbidden()->assertJsonPath('code', 'GYM_CLOSED');

        $this->travelTo(Carbon::parse('2026-11-30 05:30'));
        $this->scan($this->gym->joinUrl())->assertForbidden()->assertJsonPath('code', 'GYM_CLOSED');

        $this->travelTo(Carbon::parse('2026-11-30 06:15'));
        $this->scan($this->gym->joinUrl())->assertOk();
    }

    public function test_a_gym_using_the_live_screen_qr_only_accepts_the_screen_code(): void
    {
        $this->gym->settings = array_merge($this->gym->settings ?? [], ['checkin' => ['qr' => CheckInCodeService::SCREEN]]);
        $this->gym->save();
        $codes = app(CheckInCodeService::class);

        $this->scan($this->gym->joinUrl())->assertStatus(422)->assertJsonPath('code', 'QR_EXPIRED');
        $this->scan($codes->url($this->gym->checkin_token))->assertStatus(422)->assertJsonPath('code', 'QR_EXPIRED');

        $this->scan($codes->url($codes->screenCode($this->gym->fresh())))->assertOk()->assertJsonPath('data.source', 'qr');
    }

    /** @param  array<string, bool>  $values */
    protected function setAttendance(array $values): void
    {
        $settings = $this->gym->fresh()->settings ?? [];
        $settings['attendance'] = array_merge($settings['attendance'] ?? [], ['member_self_checkin' => true, 'qr_checkin' => true, 'checkin_hours_only' => false], $values);
        $this->gym->forceFill(['settings' => $settings])->save();
    }
}
