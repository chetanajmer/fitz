<?php

namespace Tests\Feature;

use App\Livewire\Auth\RegisterGym;
use App\Models\City;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GymRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_register_a_gym_and_lands_on_dashboard(): void
    {
        $mumbai = City::factory()->create(['name' => 'Mumbai']);

        Livewire::test(RegisterGym::class)
            ->set('gym_name', 'Iron Paradise')
            ->set('city_id', (string) $mumbai->id)
            ->set('owner_name', 'Ravi Kumar')
            ->set('email', 'ravi@example.com')
            ->set('phone', '9876543210')
            ->set('password', 'secret-pass')
            ->set('password_confirmation', 'secret-pass')
            ->set('terms', true)
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('gym.dashboard'));

        $owner = User::where('email', 'ravi@example.com')->firstOrFail();
        $gym = Gym::firstOrFail();

        $this->assertAuthenticatedAs($owner);
        $this->assertTrue($owner->isGymAdmin());
        $this->assertSame($gym->id, $owner->gym_id);
        $this->assertSame($owner->id, $gym->owner_id);
        $this->assertSame('iron-paradise', $gym->slug);
        $this->assertSame(40, strlen($gym->join_token));
        $this->assertTrue($gym->is_active);
        $this->assertSame($mumbai->id, $gym->city_id);
        $this->assertSame('Mumbai', $gym->city);
        $this->assertSame('9876543210', $gym->phone);
    }

    public function test_registration_only_offers_and_accepts_live_cities(): void
    {
        City::factory()->create(['name' => 'Pune']);
        $hidden = City::factory()->inactive()->create(['name' => 'Nagpur']);

        Livewire::test(RegisterGym::class)
            ->assertSee('Pune')
            ->assertDontSee('Nagpur')
            ->set('city_id', (string) $hidden->id)
            ->call('register')
            ->assertHasErrors('city_id');
    }

    public function test_registration_walks_through_city_gym_and_owner_steps(): void
    {
        $jaipur = City::factory()->create(['name' => 'Jaipur']);

        Livewire::test(RegisterGym::class)
            ->assertSet('step', 1)
            ->call('next')
            ->assertHasErrors('city_id')
            ->assertSet('step', 1)
            ->call('selectCity', $jaipur->id)
            ->call('next')
            ->assertHasNoErrors()
            ->assertSet('step', 2)
            ->set('map_url', 'not a link')
            ->call('next')
            ->assertHasErrors(['gym_name', 'map_url'])
            ->assertSet('step', 2)
            ->set('gym_name', 'FitLife Jagatpura')
            ->set('map_url', 'https://maps.app.goo.gl/abc123')
            ->call('next')
            ->assertSet('step', 3)
            ->call('back')
            ->assertSet('step', 2)
            ->call('next')
            ->set('owner_name', 'Naresh')
            ->set('email', 'owner@example.com')
            ->set('phone', '9876543210')
            ->set('password', 'secret-pass')
            ->set('password_confirmation', 'secret-pass')
            ->set('terms', true)
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('gym.dashboard'));

        $gym = Gym::firstOrFail();
        $this->assertSame('FitLife Jagatpura', $gym->name);
        $this->assertSame('https://maps.app.goo.gl/abc123', $gym->map_url);
        $this->assertSame($jaipur->id, $gym->city_id);
    }

    public function test_steps_cannot_be_skipped_forward(): void
    {
        Livewire::test(RegisterGym::class)
            ->call('goToStep', 3)
            ->assertSet('step', 1);
    }

    public function test_slugs_are_unique_and_avoid_reserved_words(): void
    {
        $a = $this->gymOf($this->createGymOwner('Iron Paradise'));
        $b = $this->gymOf($this->createGymOwner('Iron Paradise'));
        $c = $this->gymOf($this->createGymOwner('Login'));

        $this->assertSame('iron-paradise', $a->slug);
        $this->assertSame('iron-paradise-2', $b->slug);
        $this->assertSame('login-2', $c->slug);
        $this->assertNotSame($a->join_token, $b->join_token);
    }

    public function test_registration_validates_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        Livewire::test(RegisterGym::class)
            ->set('email', 'taken@example.com')
            ->call('register')
            ->assertHasErrors(['email', 'gym_name', 'terms'])
            ->assertSet('step', 1);

        $this->assertSame(0, Gym::count());
    }
}
