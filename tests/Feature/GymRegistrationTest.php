<?php

namespace Tests\Feature;

use App\Livewire\Auth\RegisterGym;
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
        Livewire::test(RegisterGym::class)
            ->set('gym_name', 'Iron Paradise')
            ->set('city', 'Mumbai')
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
            ->assertHasErrors(['email', 'gym_name', 'terms']);

        $this->assertSame(0, Gym::count());
    }
}
