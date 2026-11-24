<?php

namespace Tests\Feature;

use App\Livewire\Auth\RegisterGym;
use App\Livewire\Gym\Settings\Profile;
use App\Livewire\Platform\Cities;
use App\Livewire\PublicSite\BrowseGyms;
use App\Livewire\PublicSite\MemberSignup;
use App\Models\City;
use App\Models\Gym;
use App\Models\Locality;
use App\Models\User;
use App\Services\CityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GymLocalityTest extends TestCase
{
    use RefreshDatabase;

    protected function gymIn(string $name, ?string $locality): Gym
    {
        $gym = $this->gymOf($this->createGymOwner($name));
        app(CityService::class)->assignLocality($gym, $locality);
        $gym->save();

        return $gym->refresh();
    }

    public function test_gym_admin_sets_a_locality_which_is_shared_case_insensitively(): void
    {
        $owner = $this->createGymOwner('Iron Temple');
        $this->actingAs($owner);

        Livewire::test(Profile::class)
            ->assertSee('Profile')->assertSee('% complete')
            ->set('profile.locality', '  Jagatpura ')
            ->call('save')
            ->assertHasNoErrors();

        $other = $this->gymIn('Fit Hub', 'jagatpura');
        $gym = $this->gymOf($owner);

        $this->assertSame(1, Locality::count());
        $this->assertSame('Jagatpura', $gym->locality->name);
        $this->assertSame($gym->locality_id, $other->locality_id);
        $this->assertNotContains('Locality', $gym->profileCompleteness()['missing']);

        // Moving to another city clears the locality (it belongs to the old city).
        $mumbai = City::factory()->create(['name' => 'Mumbai']);
        Livewire::test(Profile::class)->set('profile.city_id', (string) $mumbai->id)->set('profile.locality', '')->call('save')->assertHasNoErrors();
        $this->assertNull($gym->refresh()->locality_id);
    }

    public function test_a_new_gym_can_pick_its_locality_when_registering(): void
    {
        $jaipur = City::factory()->create(['name' => 'Jaipur']);

        Livewire::test(RegisterGym::class)
            ->set('city_id', (string) $jaipur->id)->set('gym_name', 'Pink City Strength')->set('locality', 'C-Scheme')
            ->set('owner_name', 'Asha')->set('email', 'asha@example.test')->set('phone', '9876543210')
            ->set('password', 'secret-pass')->set('password_confirmation', 'secret-pass')->set('terms', true)
            ->call('register')
            ->assertHasNoErrors();

        $gym = Gym::where('name', 'Pink City Strength')->sole();
        $this->assertSame('C-Scheme', $gym->locality->name);
        $this->assertSame($jaipur->id, $gym->locality->city_id);
    }

    public function test_members_pick_a_locality_then_see_only_its_gyms(): void
    {
        $jagatpura = $this->gymIn('Iron Temple', 'Jagatpura');
        $this->gymIn('Fit Hub', 'Malviya Nagar');
        $this->gymIn('No Area Gym', null);

        Livewire::test(MemberSignup::class)
            ->call('selectCity', $jagatpura->city_id)
            ->assertSet('step', 1)
            ->assertSee('Which area of Pune')
            ->assertSee('Jagatpura')->assertSee('Malviya Nagar')->assertSee('3 gyms')
            ->call('selectLocality', $jagatpura->locality_id)
            ->assertSet('step', 2)
            ->assertSee('Iron Temple')->assertDontSee('Fit Hub')->assertDontSee('No Area Gym')
            ->call('back')
            ->assertSet('step', 1)
            ->call('selectLocality')
            ->assertSee('Iron Temple')->assertSee('Fit Hub')->assertSee('No Area Gym');
    }

    public function test_members_can_search_the_areas_of_a_city(): void
    {
        $gym = $this->gymIn('Iron Temple', 'Jagatpura');
        $this->gymIn('Fit Hub', 'Malviya Nagar');

        Livewire::test(MemberSignup::class)
            ->call('selectCity', $gym->city_id)
            ->set('localitySearch', 'malv')
            ->assertSee('Malviya Nagar')->assertDontSee('Jagatpura')->assertSee('All areas')
            ->set('localitySearch', 'zzz')
            ->assertSee('No area matches "zzz"', false);
    }

    public function test_cities_without_localities_go_straight_to_the_gym_list(): void
    {
        $gym = $this->gymIn('Iron Temple', null);

        Livewire::test(MemberSignup::class)->call('selectCity', $gym->city_id)->assertSet('step', 2)->assertSee('Iron Temple');
    }

    public function test_view_gym_shows_its_public_profile_and_join_picks_it(): void
    {
        $gym = $this->gymIn('Iron Temple', 'Jagatpura');
        $gym->forceFill(['description' => 'Open gym with free weights.', 'map_url' => 'https://maps.app.goo.gl/abc'])->save();
        $this->createPlan($gym, ['name' => 'Quarterly Saver', 'price' => 2500]);
        $this->createPlan($gym, ['name' => 'Secret Staff Plan', 'is_public' => false]);
        $elsewhere = $this->gymOf($this->createGymOwner('Elsewhere'));
        $elsewhere->forceFill(['city_id' => City::factory()->create(['name' => 'Delhi'])->id])->save();

        Livewire::test(MemberSignup::class)
            ->call('selectCity', $gym->city_id)
            ->call('selectLocality', $gym->locality_id)
            ->call('viewGym', $gym->id)
            ->assertSee('Open gym with free weights.')->assertSee('Quarterly Saver')->assertDontSee('Secret Staff Plan')
            ->assertSee('Get directions')->assertSee('Join this gym')
            ->call('selectGym', $gym->id)
            ->assertSet('step', 3)
            ->assertSet('previewGymId', null);

        Livewire::test(MemberSignup::class)->call('selectCity', $gym->city_id)->call('selectLocality')
            ->call('viewGym', $elsewhere->id)->assertNotFound();
    }

    public function test_app_api_lists_localities_filters_gyms_and_returns_a_gym_profile(): void
    {
        $iron = $this->gymIn('Iron Temple', 'Jagatpura');
        $fit = $this->gymIn('Fit Hub', 'Malviya Nagar');
        $this->createPlan($iron, ['name' => 'Monthly Pass', 'price' => 1200]);

        $this->getJson("/api/v1/cities/{$iron->city_id}/localities")->assertOk()
            ->assertJsonPath('data.gyms_count', 2)
            ->assertJsonPath('data.localities.0.name', 'Jagatpura')
            ->assertJsonPath('data.localities.0.gyms_count', 1)
            ->assertJsonCount(2, 'data.localities');

        $this->getJson("/api/v1/cities/{$iron->city_id}/gyms?locality_id={$fit->locality_id}")->assertOk()
            ->assertJsonCount(1, 'data.gyms')
            ->assertJsonPath('data.gyms.0.name', 'Fit Hub')
            ->assertJsonPath('data.gyms.0.locality', 'Malviya Nagar');

        $this->getJson("/api/v1/gyms/{$iron->id}/profile")->assertOk()
            ->assertJsonPath('data.name', 'Iron Temple')
            ->assertJsonPath('data.locality', 'Jagatpura')
            ->assertJsonPath('data.plans.0.name', 'Monthly Pass')
            ->assertJsonCount(7, 'data.opening_hours');

        $iron->forceFill(['status' => 'suspended'])->save();
        $this->getJson("/api/v1/gyms/{$iron->id}/profile")->assertNotFound()->assertJsonPath('code', 'GYM_NOT_FOUND');
    }

    public function test_anyone_can_browse_a_citys_gyms_and_open_their_profiles_in_a_new_tab(): void
    {
        $iron = $this->gymIn('Iron Temple', 'Jagatpura');
        $this->gymIn('Fit Hub', 'Malviya Nagar');

        $this->get(route('member.join'))->assertSee(route('gyms.browse'), false);
        $this->get(route('gyms.browse'))->assertOk()->assertSee('Browse gyms')->assertSee('Pune');

        Livewire::test(BrowseGyms::class)
            ->call('selectCity', $iron->city_id)
            ->assertSee('Iron Temple')->assertSee('Fit Hub')
            ->assertSeeHtml('href="'.$iron->publicUrl().'" target="_blank"')
            ->call('selectLocality', $iron->locality_id)
            ->assertSee('Iron Temple')->assertDontSee('Fit Hub')
            ->set('search', 'zzz')
            ->assertSee('No gym matches');

        // The filters live in the URL, so a link opens the same list.
        $this->get(route('gyms.browse', ['city' => $iron->city_id, 'area' => $iron->locality_id]))->assertOk()->assertSee('Iron Temple')->assertDontSee('Fit Hub');
    }

    public function test_every_gym_has_a_location_link(): void
    {
        $gym = $this->gymIn('Iron Temple', 'Jagatpura');
        $gym->forceFill(['address' => 'Plot 12'])->save();

        $this->assertStringStartsWith('https://www.google.com/maps/search/?api=1&query=Iron%20Temple%2C%20Plot%2012%2C%20Jagatpura%2C%20Pune', $gym->directionsUrl());

        $gym->forceFill(['map_url' => 'https://maps.app.goo.gl/abc'])->save();
        $this->assertSame('https://maps.app.goo.gl/abc', $gym->refresh()->directionsUrl());

        $this->getJson("/api/v1/cities/{$gym->city_id}/gyms")->assertOk()
            ->assertJsonPath('data.gyms.0.public_url', $gym->publicUrl())
            ->assertJsonPath('data.gyms.0.directions_url', 'https://maps.app.goo.gl/abc');
    }

    public function test_super_admin_renames_and_removes_localities(): void
    {
        $iron = $this->gymIn('Iron Temple', 'Jagatpura');
        $this->gymIn('Fit Hub', 'Jagatpur');
        $typo = Locality::where('name', 'Jagatpur')->sole();
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        Livewire::actingAs($admin)->test(Cities::class)
            ->call('showLocalities', $iron->city_id)
            ->assertSee('Jagatpur')
            ->call('editLocality', $typo->id)
            ->set('localityName', 'jagatpura')
            ->call('renameLocality')
            ->assertHasErrors('localityName')
            ->call('deleteLocality', $typo->id);

        $this->assertNull(Gym::where('name', 'Fit Hub')->sole()->locality_id);
        $this->assertSame('Jagatpura', $iron->refresh()->locality->name);
    }
}
