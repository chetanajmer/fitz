<?php

namespace Tests\Feature;

use App\Contracts\SmsSender;
use App\Livewire\Account;
use App\Livewire\Auth\Login;
use App\Mail\LoginCodeMail;
use App\Models\Gym;
use App\Models\User;
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

    public function test_unknown_pending_and_super_admin_identifiers_get_no_code(): void
    {
        Livewire::test(Login::class)->set('mode', 'otp')->set('identifier', '9123456780')->call('sendCode')
            ->assertHasErrors('identifier')->assertSee('No active account uses this mobile number');

        $this->createMember($this->gym, ['phone' => '9123456781', 'status' => 'pending']);
        Livewire::test(Login::class)->set('mode', 'otp')->set('identifier', '9123456781')->call('sendCode')
            ->assertHasErrors('identifier')->assertSee('waiting for the gym');

        $admin = User::factory()->create(['email' => 'boss@gymai.test']);
        $admin->assignRole('super_admin');
        Livewire::test(Login::class)->set('mode', 'otp')->set('identifier', 'boss@gymai.test')->call('sendCode')->assertHasErrors('identifier');

        $this->assertSame([], $this->sms);
        Mail::assertNothingSent();
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
            ->assertHasErrors('account')
            ->assertSee('Mobile Gym · Gym admin')->assertSee('Mobile Gym · Member')->assertSee('Second Gym · Member');
        $this->assertSame([], $this->sms);

        $login->set('account', 'staff:'.$this->owner->id)->call('sendCode')->assertSet('codeSent', true)
            ->set('code', $this->lastSmsCode())->call('verifyCode')
            ->assertRedirect(route('gym.dashboard'));
        $this->assertSame($this->owner->id, Auth::id());
    }

    public function test_a_code_only_works_for_the_account_it_was_sent_for(): void
    {
        $this->owner->forceFill(['login_phone' => '9844444444'])->save();
        $member = $this->createMember($this->gym, ['phone' => '9844444444']);

        Livewire::test(Login::class)->set('mode', 'otp')->set('identifier', '9844444444')
            ->set('account', 'member:'.$member->id)->call('sendCode')->assertSet('codeSent', true)
            ->set('account', 'staff:'.$this->owner->id)->set('code', $this->lastSmsCode())->call('verifyCode')
            ->assertHasErrors('code');
        $this->assertNull(Auth::id());
    }

    public function test_super_admin_cannot_set_a_staff_sign_in_mobile(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        Livewire::actingAs($admin)->test(Account::class)->set('loginPhone', '9833333333')->call('sendPhoneCode')->assertForbidden();
    }
}
