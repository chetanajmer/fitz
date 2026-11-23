<?php

namespace App\Services;

use App\Enums\MemberStatus;
use App\Enums\Role;
use App\Models\Gym;
use App\Models\Member;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MemberService
{
    public function __construct(protected TenantManager $tenant) {}

    /**
     * @param  array  $data  validated member attributes (fillable)
     * @param  array{trainer_id?:?int, enquiry_id?:?int, portal_password?:?string}  $extra
     */
    public function create(array $data, array $extra = [], ?UploadedFile $photo = null): Member
    {
        $gym = $this->requireGym();

        return DB::transaction(function () use ($gym, $data, $extra, $photo) {
            // Serialise sequence allocation per gym.
            Gym::whereKey($gym->id)->lockForUpdate()->first();

            $sequence = (int) Member::withTrashed()->where('gym_id', $gym->id)->max('sequence') + 1;

            $member = new Member($data);
            $member->sequence = $sequence;
            $member->member_code = 'M'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $member->qr_token = $this->uniqueQrToken();
            $member->status ??= 'active';
            $member->joining_date ??= now()->toDateString();
            $member->trainer_id = $this->validTrainerId($extra['trainer_id'] ?? null);

            if (! empty($extra['enquiry_id'])) {
                $member->enquiry_id = $extra['enquiry_id'];
            }

            if ($photo) {
                $member->photo_path = $photo->store("gyms/{$gym->id}/members", 'public');
            }

            $member->save();

            if (! empty($extra['portal_password']) && $member->email) {
                $this->createPortalLogin($member, $extra['portal_password']);
            }

            return $member;
        });
    }

    public function update(Member $member, array $data, array $extra = [], ?UploadedFile $photo = null): Member
    {
        return DB::transaction(function () use ($member, $data, $extra, $photo) {
            $member->fill($data);

            if (array_key_exists('trainer_id', $extra)) {
                $member->trainer_id = $this->validTrainerId($extra['trainer_id']);
            }

            if ($photo) {
                if ($member->photo_path) {
                    Storage::disk('public')->delete($member->photo_path);
                }
                $member->photo_path = $photo->store("gyms/{$member->gym_id}/members", 'public');
            }

            $member->save();

            if ($member->user && $member->wasChanged(['name', 'email', 'phone'])) {
                $member->user->forceFill(['name' => $member->name, 'phone' => $member->phone] + ($member->email ? ['email' => $member->email] : []))->save();
            }

            if (! empty($extra['portal_password']) && $member->email) {
                $member->user
                    ? $member->user->forceFill(['password' => $extra['portal_password']])->save()
                    : $this->createPortalLogin($member, $extra['portal_password']);
            }

            return $member;
        });
    }

    public function assignTrainer(Member $member, ?int $trainerId): void
    {
        $member->trainer_id = $this->validTrainerId($trainerId);
        $member->save();
    }

    public function createPortalLogin(Member $member, string $password): User
    {
        if (User::where('email', $member->email)->exists()) {
            throw ValidationException::withMessages(['email' => 'A user account with this email already exists.']);
        }

        $user = User::create([
            'name' => $member->name,
            'email' => strtolower($member->email),
            'phone' => $member->phone,
            'password' => $password,
            'status' => 'active',
        ]);
        $user->gym_id = $member->gym_id;
        $user->save();
        $user->assignRole(Role::Member->value);

        $member->user_id = $user->id;
        $member->save();

        return $user;
    }

    public function delete(Member $member): void
    {
        DB::transaction(function () use ($member) {
            $member->user?->forceFill(['status' => 'inactive'])->save();
            $member->tokens()->delete(); // sign the member out of the mobile app
            $member->delete();
        });
    }

    /** Accept a website/app sign-up: the member becomes active and can log in. */
    public function approveRegistration(Member $member): Member
    {
        $this->ensurePending($member);
        $member->status = MemberStatus::Active;
        $member->save();

        return $member;
    }

    /** Turn down a website/app sign-up. Soft-deleted, so the person can apply again later. */
    public function declineRegistration(Member $member): void
    {
        $this->ensurePending($member);
        $this->delete($member);
    }

    protected function ensurePending(Member $member): void
    {
        if ($member->status !== MemberStatus::Pending) {
            throw ValidationException::withMessages(['member' => "{$member->name} is not waiting for approval."]);
        }
    }

    /** Ensures the trainer belongs to the current gym — never trust a posted id. */
    public function validTrainerId(?int $trainerId): ?int
    {
        if (! $trainerId) {
            return null;
        }

        $exists = User::trainersOf($this->requireGym()->id)->whereKey($trainerId)->exists();

        if (! $exists) {
            throw ValidationException::withMessages(['trainer_id' => 'Selected trainer is not part of this gym.']);
        }

        return $trainerId;
    }

    protected function uniqueQrToken(): string
    {
        do {
            $token = Str::random(32);
        } while (Member::withoutGlobalScopes()->where('qr_token', $token)->exists());

        return $token;
    }

    protected function requireGym(): Gym
    {
        return $this->tenant->gym() ?? throw new \LogicException('No gym context.');
    }
}
