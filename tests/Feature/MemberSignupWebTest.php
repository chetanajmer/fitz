<?php

namespace Tests\Feature;

use App\Contracts\SmsSender;
use App\Livewire\PublicSite\MemberSignup;
use App\Models\City;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MemberSignupWebTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{phone:string, message:string}> */
    protected array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->sent = [];
        $this->app->instance(SmsSender::class, new class($this->sent) implements SmsSender
        {
            public function __construct(private array &$sent) {}

            public function send(string $phone, string $message): void
            {
                $this->sent[] = compact('phone', 'message');
            }
        });
    }

    protected function lastOtp(): string
    {
        preg_match('/^(\d{6})/', end($this->sent)['message'], $m);

        return $m[1];
    }

    public function test_landing_and_login_pages_link_to_the_member_sign_up(): void
    {
        $this->get(route('home'))->assertOk()->assertSee(route('member.join'), false);
        $this->get(route('login'))->assertOk()->assertSee(route('member.join'), false);
    }

    public function test_steps_go_city_then_gym_then_details_then_otp(): void
    {
        $iron = $this->gymOf($this->createGymOwner('Iron Temple'));
        $this->gymOf($this->createGymOwner('Fit Hub'));
        City::factory()->inactive()->create(['name' => 'Hidden Town']);

        $this->get(route('member.join'))->assertOk()->assertSee('Pune')->assertDontSee('Hidden Town');

        $page = Livewire::test(MemberSignup::class)
            ->assertSet('step', 1)
            ->call('selectCity', $iron->city_id)
            ->assertSet('step', 2)
            ->assertSee('Iron Temple')->assertSee('Fit Hub')
            ->set('gymSearch', 'iron')
            ->assertSee('Iron Temple')->assertDontSee('Fit Hub')
            ->call('selectGym', $iron->id)
            ->assertSet('step', 3)
            ->set('name', 'Web Joiner')
            ->set('phone', '98111 00002')
            ->set('fitness_goal', 'Muscle gain')
            ->call('sendOtp')
            ->assertHasNoErrors()
            ->assertSet('step', 4);

        $this->assertCount(1, $this->sent);
        $this->assertSame(0, Member::forGym($iron)->count());

        $page->set('otp', $this->lastOtp())->call('verify')->assertHasNoErrors()->assertSet('step', 5)->assertSee('M0001');

        $member = Member::forGym($iron)->sole();
        $this->assertSame('Web Joiner', $member->name);
        $this->assertSame('pending', $member->status->value);
        $this->assertSame('9811100002', $member->phone_digits);
    }

    public function test_hidden_city_and_gym_from_another_city_cannot_be_chosen(): void
    {
        $gym = $this->gymOf($this->createGymOwner());
        $hidden = City::factory()->inactive()->create();
        $other = City::factory()->create();

        Livewire::test(MemberSignup::class)->call('selectCity', $hidden->id)->assertStatus(404);
        Livewire::test(MemberSignup::class)->call('selectCity', $other->id)->call('selectGym', $gym->id)->assertStatus(404);
    }

    public function test_wrong_otp_creates_nobody(): void
    {
        $gym = $this->gymOf($this->createGymOwner());

        $page = Livewire::test(MemberSignup::class)
            ->call('selectCity', $gym->city_id)->call('selectGym', $gym->id)
            ->set('name', 'Wrong Code')->set('phone', '9811100003')
            ->call('sendOtp')->assertSet('step', 4);

        $wrong = $this->lastOtp() === '000000' ? '111111' : '000000';
        $page->set('otp', $wrong)->call('verify')->assertHasErrors('otp')->assertSet('step', 4);

        $this->assertSame(0, Member::forGym($gym)->count());
    }

    public function test_number_already_at_the_gym_is_stopped_before_any_otp(): void
    {
        $gym = $this->gymOf($this->createGymOwner());
        $this->createMember($gym, ['phone' => '9811100004']);

        Livewire::test(MemberSignup::class)
            ->call('selectCity', $gym->city_id)->call('selectGym', $gym->id)
            ->set('name', 'Again')->set('phone', '+91 98111 00004')
            ->call('sendOtp')
            ->assertHasErrors('phone')
            ->assertSet('step', 3);

        $this->assertSame([], $this->sent);
    }

    public function test_back_returns_to_the_previous_step(): void
    {
        $gym = $this->gymOf($this->createGymOwner());

        Livewire::test(MemberSignup::class)
            ->call('selectCity', $gym->city_id)->call('selectGym', $gym->id)
            ->call('back')->assertSet('step', 2)
            ->call('back')->assertSet('step', 1);
    }
}
