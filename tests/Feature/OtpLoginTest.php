<?php

namespace Tests\Feature;

use App\Contracts\SmsSender;
use App\Livewire\Account;
use App\Livewire\Auth\Login;
use App\Mail\LoginCodeMail;
use App\Models\Gym;
use App\Models\User;
use App\Services\MemberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class OtpLoginTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{phone: string, message: string}> */
    public array $sms = [];

    protected User $owner;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $test = $this;
        $this->app->instance(SmsSender::class, new class($test) implements SmsSender
        {
            public function __construct(private OtpLoginTest $test) {}

            public function send(string $phone, string $message): void
            {
                $this->test->sms[] = ['phone' => $phone, 'message' => $message];
            }
        });

        $this->owner = $this->createGymOwner('Mobile Gym');
        $this->gym = $this->gymOf($this->owner);
    }

    protected function lastSmsCode(): string
    {
        preg_match('/^(\d{6})/', end($this->sms)['message'], $match);

        return $match[1];
    }

    protected function lastEmailCode(string $to): string
    {
        $code = null;
        Mail::assertSent(LoginCodeMail::class, function (LoginCodeMail $mail) use ($to, &$code) {
            $code = $mail->code;

            return $mail->hasTo($to);
        });

        return $code;
    }

    public function test_member_signs_in_with_a_code_by_sms_and_gets_a_portal_account(): void
    {
        $member = $this->createMember($this->gym, ['phone' => '+91 98765 43210', 'email' => null]);

        Livewire::test(Login::class)
            ->set('mode', 'otp')
            ->set('identifier', '98765 43210')
            ->call('sendCode')
            ->assertHasNoErrors()
            ->assertSet('codeSent', true)
            ->assertSee('XXXXXX3210')
            ->set('code', '000000')
            ->call('verifyCode')
            ->assertHasErrors('code')
            ->set('code', $this->lastSmsCode())
            ->call('verifyCode')
            ->assertRedirect(route('portal.dashboard'));

        $user = $member->refresh()->user;
        $this->assertTrue($user->isMember());
        $this->assertNull($user->email);
        $this->assertSame($user->id, Auth::id());

        // The member account page works without an email.
        Livewire::test(Account::class)->set('name', 'New Name')->call('saveProfile')->assertHasNoErrors();

        // Next time the same account is used.
        Auth::logout();
        Livewire::test(Login::class)->set('mode', 'otp')->set('identifier', '9876543210')->call('sendCode')
            ->set('code', $this->lastSmsCode())->call('verifyCode')->assertRedirect(route('portal.dashboard'));
        $this->assertSame(1, User::role('member')->count());
    }

    public function test_member_and_staff_sign_in_with_a_code_by_email(): void
    {
        $member = $this->createMember($this->gym, ['email' => 'Ravi@Example.test']);

        Livewire::test(Login::class)->set('mode', 'otp')->set('identifier', 'ravi@example.test')->call('sendCode')
            ->assertHasNoErrors()->assertSee('ra•••@example.test')
            ->set('code', $this->lastEmailCode('ravi@example.test'))->call('verifyCode')
            ->assertRedirect(route('portal.dashboard'));
        $this->assertSame($member->refresh()->user_id, Auth::id());
        $this->assertSame([], $this->sms);

        Auth::logout();
        Livewire::test(Login::class)->set('mode', 'otp')->set('identifier', $this->owner->email)->call('sendCode')
            ->set('code', $this->lastEmailCode($this->owner->email))->call('verifyCode')
            ->assertRedirect(route('gym.dashboard'));
        $this->assertSame($this->owner->id, Auth::id());
    }

    public function test_a_pending_registration_sees_its_status_only_after_the_code(): void
    {
        $this->createMember($this->gym, ['phone' => '9123456781', 'status' => 'pending']);

        $this->get(route('login', ['mode' => 'otp']))->assertOk()->assertSee('Send code');

        Livewire::test(Login::class)->set('mode', 'otp')->set('identifier', '9123456781')->call('sendCode')
            ->assertHasNoErrors()->assertSet('codeSent', true)
            ->assertDontSee('Waiting for approval') // nothing is revealed before the code
            ->set('code', $this->lastSmsCode())->call('verifyCode')
            ->assertHasNoErrors()
            ->assertSee('Waiting for approval')->assertSee('Mobile Gym');
        $this->assertNull(Auth::id());
    }

    public function test_a_declined_registration_gets_a_kind_message_and_other_options(): void
    {
        $member = $this->createMember($this->gym, ['phone' => '9123456782', 'status' => 'pending']);
        $this->inGym($this->gym, fn () => app(MemberService::class)->declineRegistration($member));

        Livewire::test(Login::class)->set('mode', 'otp')->set('identifier', '9123456782')->call('sendCode')
            ->set('code', $this->lastSmsCode())->call('verifyCode')
            ->assertSee("Sorry, Mobile Gym couldn't approve your registration", false)
            ->assertSeeHtml(route('gyms.browse'))->assertSeeHtml(route('member.join'));
        $this->assertNull(Auth::id());
    }

    public function test_unknown_and_super_admin_identifiers_look_the_same_but_get_no_code(): void
    {
        $admin = User::factory()->create(['email' => 'boss@gymai.test']);
        $admin->assignRole('super_admin');

        foreach (['9123456780', 'boss@gymai.test'] as $identifier) {
            $page = Livewire::test(Login::class)->set('mode', 'otp')->set('identifier', $identifier)->call('sendCode')
                ->assertHasNoErrors()->assertSet('codeSent', true);

            // Wrong codes, the attempt limit and the resend cooldown answer as for a real account.
            foreach (range(1, 5) as $try) {
                $page->set('code', '000000')->call('verifyCode')->assertSee('Incorrect code.');
            }
            $page->call('verifyCode')->assertSee('Too many wrong attempts');
            $page->call('sendCode')->assertSee('before requesting another code');
        }

        $this->assertSame([], $this->sms);
        Mail::assertNothingSent();
        $this->assertNull(Auth::id());
    }

    public function test_code_requests_are_limited_per_ip_across_different_numbers(): void
    {
        foreach (range(0, 9) as $n) {
            Livewire::test(Login::class)->set('mode', 'otp')->set('identifier', '912345670'.$n)->call('sendCode')->assertHasNoErrors();
        }

        Livewire::test(Login::class)->set('mode', 'otp')->set('identifier', '9123456799')->call('sendCode')
            ->assertHasErrors('identifier')->assertSet('codeSent', false);
    }

    public function test_staff_verify_their_mobile_then_sign_in_with_a_password_or_a_code(): void
    {
        // Not verified yet: the number cannot be used.
        Livewire::test(Login::class)->set('email', '9811111111')->set('password', 'password')->call('login')->assertHasErrors('email');

        $this->actingAs($this->owner);
        Livewire::test(Account::class)
            ->set('loginPhone', '98111 11111')
            ->call('sendPhoneCode')
            ->assertHasNoErrors()
            ->set('phoneCode', $this->lastSmsCode())
            ->call('verifyPhoneCode')
            ->assertHasNoErrors();
        $this->assertSame('9811111111', $this->owner->refresh()->login_phone);

        // Another staff account cannot claim the same number.
        $trainer = $this->createTrainer($this->gym);
        $this->actingAs($trainer);
        Livewire::test(Account::class)->set('loginPhone', '9811111111')->call('sendPhoneCode')->assertHasErrors('loginPhone');

        Auth::logout();
        Livewire::test(Login::class)->set('email', '+91 98111 11111')->set('password', 'wrong')->call('login')->assertHasErrors('email');
        Livewire::test(Login::class)->set('email', '+91 98111 11111')->set('password', 'password')->call('login')
            ->assertRedirect(route('gym.dashboard'));
        $this->assertSame($this->owner->id, Auth::id());

        Auth::logout();
        Livewire::test(Login::class)->set('mode', 'otp')->set('identifier', '9811111111')->call('sendCode')
            ->set('code', $this->lastSmsCode())->call('verifyCode')
            ->assertRedirect(route('gym.dashboard'));
    }

    public function test_an_identifier_with_several_accounts_chooses_where_to_sign_in(): void
    {
        $this->owner->forceFill(['login_phone' => '9822222222'])->save();
        $this->createMember($this->gym, ['phone' => '9822222222']);
        $otherGym = $this->gymOf($this->createGymOwner('Second Gym'));
        $this->createMember($otherGym, ['phone' => '9822222222']);

        $login = Livewire::test(Login::class)->set('mode', 'otp')->set('identifier', '9822222222')->call('sendCode')
            ->assertHasNoErrors()
            ->assertDontSee('Mobile Gym')->assertDontSee('Second Gym') // nothing is revealed before the code
            ->set('code', $this->lastSmsCode())->call('verifyCode')
            ->assertSee('Mobile Gym · Gym admin')->assertSee('Mobile Gym · Member')->assertSee('Second Gym · Member');
        $this->assertNull(Auth::id());
        $this->assertCount(1, $this->sms);

        $login->set('account', 'staff:'.$this->owner->id)->call('chooseAccount')->assertRedirect(route('gym.dashboard'));
        $this->assertSame($this->owner->id, Auth::id());
    }

    public function test_choosing_an_account_needs_a_recent_code_and_one_of_its_own_accounts(): void
    {
        $this->owner->forceFill(['login_phone' => '9844444444'])->save();
        $this->createMember($this->gym, ['phone' => '9844444444']);
        $stranger = $this->createMember($this->gym, ['phone' => '9855555555']);
        $key = 'staff:'.$this->owner->id;

        // No code at all.
        Livewire::test(Login::class)->set('identifier', '9844444444')->set('account', $key)->call('chooseAccount')->assertHasErrors('account');

        // A code for this number does not open somebody else's account, even if the number is edited afterwards.
        $page = Livewire::test(Login::class)->set('mode', 'otp')->set('identifier', '9844444444')->call('sendCode')
            ->set('code', $this->lastSmsCode())->call('verifyCode')
            ->set('identifier', '9855555555')->set('account', 'member:'.$stranger->id)->call('chooseAccount')->assertHasErrors('account');
        $this->assertNull(Auth::id());

        // The check expires.
        $this->travel(11)->minutes();
        $page->set('account', $key)->call('chooseAccount')->assertHasErrors('account');
        $this->assertNull(Auth::id());
    }

    public function test_a_verified_code_signs_in_only_once(): void
    {
        $this->owner->forceFill(['login_phone' => '9844444444'])->save();
        $this->createMember($this->gym, ['phone' => '9844444444']);

        $page = Livewire::test(Login::class)->set('mode', 'otp')->set('identifier', '9844444444')->call('sendCode')
            ->set('code', $this->lastSmsCode())->call('verifyCode')
            ->set('account', 'staff:'.$this->owner->id)->call('chooseAccount')->assertRedirect(route('gym.dashboard'));

        Auth::logout();
        $page->call('chooseAccount')->assertHasErrors('account');
        $this->assertNull(Auth::id());
    }

    public function test_super_admin_cannot_set_a_staff_sign_in_mobile(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        Livewire::actingAs($admin)->test(Account::class)->set('loginPhone', '9833333333')->call('sendPhoneCode')->assertForbidden();
    }
}
