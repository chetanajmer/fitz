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
    /**
     * Create owner + gym atomically, link them, assign gym_admin and sign the owner in.
     *
     * @param  array{owner_name:string, email:string, phone:?string, password:string, gym_name:string, gym_phone:?string, city:?string, address:?string}  $data
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
                'city' => $data['city'] ?? null,
                'address' => $data['address'] ?? null,
            ]);
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
