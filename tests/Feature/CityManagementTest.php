<?php

namespace Tests\Feature;

use App\Livewire\Gym\Settings\Profile;
use App\Livewire\Platform\Cities;
use App\Models\City;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CityManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function superAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        return $admin;
    }

    public function test_super_admin_can_add_rename_and_hide_cities(): void
    {
        $admin = $this->superAdmin();
        $gym = $this->gymOf($this->createGymOwner());
        $pune = $gym->cityRecord;

        $this->actingAs($admin)->get(route('platform.cities'))->assertOk()->assertSee('Pune');

        Livewire::actingAs($admin)->test(Cities::class)
            ->set('name', 'Jaipur')->set('state', 'Rajasthan')->call('save')->assertHasNoErrors()
            ->set('name', ' jaipur ')->call('save')->assertHasErrors('name')
            ->call('edit', $pune->id)->set('name', 'Pune City')->call('save')->assertHasNoErrors()
            ->call('toggle', $pune->id);

        $this->assertDatabaseHas('cities', ['name' => 'Jaipur', 'state' => 'Rajasthan', 'is_active' => true]);
        $this->assertSame(1, City::whereRaw('LOWER(name) = ?', ['jaipur'])->count());
        $this->assertSame('Pune City', $gym->fresh()->city);
        $this->assertFalse($pune->fresh()->is_active);
    }

    public function test_only_super_admin_can_open_the_cities_page(): void
    {
        $owner = $this->createGymOwner();

        $this->actingAs($owner)->get(route('platform.cities'))->assertForbidden();
    }

    public function test_cities_given_by_name_are_matched_case_insensitively(): void
    {
        $first = $this->gymOf($this->createGymOwner('Gym One'));
        City::where('id', $first->city_id)->update(['name' => 'PUNE']);

        $second = $this->gymOf($this->createGymOwner('Gym Two'));

        $this->assertSame($first->city_id, $second->city_id);
        $this->assertSame(1, City::count());
    }

    public function test_gym_admin_picks_their_city_from_the_list(): void
    {
        $owner = $this->createGymOwner();
        $gym = $this->gymOf($owner);
        $jaipur = City::factory()->create(['name' => 'Jaipur']);
        $hidden = City::factory()->inactive()->create(['name' => 'Hidden Town']);

        Livewire::actingAs($owner)->test(Profile::class)
            ->assertSee('Jaipur')->assertDontSee('Hidden Town')
            ->set('profile.city_id', (string) $hidden->id)->call('save')->assertHasErrors('profile.city_id')
            ->set('profile.city_id', (string) $jaipur->id)->call('save')->assertHasNoErrors();

        $gym->refresh();
        $this->assertSame($jaipur->id, $gym->city_id);
        $this->assertSame('Jaipur', $gym->city);
    }

    public function test_gym_keeps_its_city_after_the_city_is_hidden(): void
    {
        $owner = $this->createGymOwner();
        $gym = $this->gymOf($owner);
        $gym->cityRecord->update(['is_active' => false]);

        Livewire::actingAs($owner)->test(Profile::class)->call('save')->assertHasNoErrors();

        $this->assertSame('Pune', $gym->fresh()->city);
    }
}
