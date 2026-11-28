<?php

namespace App\Services;

use App\Models\StaffDevice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A password set for someone else: a gym admin for a trainer or a member, the Super Admin for a gym admin.
 * The old password may be known to someone it should not be, so every existing sign-in of that account ends.
 */
class AccountPasswordService
{
    public function setFor(User $user, string $password): void
    {
        DB::transaction(function () use ($user, $password) {
            // "password" is cast to hashed; a new remember token ends "remember me" logins.
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();

            $this->signOutEverywhere($user);
        });
    }

    /** Ends the account's staff app tokens and pushes, and its website sessions. */
    public function signOutEverywhere(User $user): void
    {
        $user->tokens()->delete();
        StaffDevice::withoutGlobalScopes()->where('user_id', $user->id)->delete();

        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }
}
