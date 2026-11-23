<?php

namespace App\Services;

use App\Enums\GymStatus;
use App\Enums\Role;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GymRegistrationService
{
    public function __construct(protected CityService $cities) {}

    /**
     * Create owner + gym atomically, link them, assign gym_admin and sign the owner in.
     * The gym's city is `city_id` (from the active list) or, for seeders, a `city` name.
     *
     * @param  array{owner_name:string, email:string, phone:?string, password:string, gym_name:string, gym_phone:?string, city_id?:?int, city?:?string, address:?string}  $data
     */
    public function register(array $data, bool $login = true): User
    {
        $owner = DB::transaction(function () use ($data) {
            $owner = User::create([
                'name' => $data['owner_name'],
                'email' => strtolower($data['email']),
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'status' => 'active',
            ]);

            $gym = new Gym([
                'name' => $data['gym_name'],
                'phone' => $data['gym_phone'] ?? $data['phone'] ?? null,
                'email' => strtolower($data['email']),
                'address' => $data['address'] ?? null,
            ]);
            $this->cities->assignToGym($gym, $this->cities->resolve($data['city_id'] ?? null, $data['city'] ?? null));
            $gym->owner_id = $owner->id;
            $gym->slug = Gym::generateUniqueSlug($data['gym_name']);
            $gym->join_token = Gym::generateJoinToken();
            $gym->register_token = Gym::generateRegisterToken();
            $gym->status = GymStatus::Active;
            $gym->is_active = true;
            $gym->settings = Gym::DEFAULT_SETTINGS;
            $gym->save();

            $owner->gym_id = $gym->id;
            $owner->save();
            $owner->assignRole(Role::GymAdmin->value);

            return $owner;
        });

        if ($login) {
            Auth::login($owner);
            session()->regenerate();
            $owner->forceFill(['last_login_at' => now()])->save();
        }

        return $owner;
    }
}
