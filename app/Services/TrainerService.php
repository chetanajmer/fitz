<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Member;
use App\Models\User;
use App\Support\TrainerAccess;
use App\Tenancy\TenantManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TrainerService
{
    public function __construct(protected TenantManager $tenant) {}

    public function create(array $data, ?UploadedFile $photo = null): User
    {
        $gymId = $this->tenant->id() ?? throw new \LogicException('No gym context.');

        return DB::transaction(function () use ($data, $photo, $gymId) {
            $trainer = User::create([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'specialization' => $data['specialization'] ?? null,
                'bio' => $data['bio'] ?? null,
                'status' => $data['status'] ?? 'active',
            ]);
            $trainer->gym_id = $gymId;
            if ($photo) {
                $trainer->profile_photo_path = $photo->store("gyms/{$gymId}/trainers", 'public');
            }
            $trainer->save();
            $trainer->assignRole(Role::Trainer->value);
            $trainer->syncPermissions(TrainerAccess::normalize($data['permissions'] ?? TrainerAccess::DEFAULTS));

            return $trainer;
        });
    }

    public function update(User $trainer, array $data, ?UploadedFile $photo = null): User
    {
        $this->ensureOwnTrainer($trainer);

        $trainer->fill(collect($data)->only(['name', 'phone', 'specialization', 'bio', 'status'])->all());
        $trainer->email = strtolower($data['email'] ?? $trainer->email);

        if (! empty($data['password'])) {
            $trainer->password = $data['password'];
        }

        if ($photo) {
            if ($trainer->profile_photo_path) {
                Storage::disk('public')->delete($trainer->profile_photo_path);
            }
            $trainer->profile_photo_path = $photo->store("gyms/{$trainer->gym_id}/trainers", 'public');
        }

        $trainer->save();

        return $trainer;
    }

    /**
     * Replace the sections this trainer can open in the gym panel.
     *
     * @param  array<int, string>  $permissions
     * @return list<string> the permissions actually granted
     */
    public function setPermissions(User $trainer, array $permissions): array
    {
        $this->ensureOwnTrainer($trainer);

        $granted = TrainerAccess::normalize($permissions);
        $trainer->syncPermissions($granted);

        return $granted;
    }

    public function setStatus(User $trainer, string $status): void
    {
        $this->ensureOwnTrainer($trainer);
        $trainer->status = $status;
        $trainer->save();
    }

    /** @param  list<int>  $memberIds */
    public function assignMembers(User $trainer, array $memberIds): int
    {
        $this->ensureOwnTrainer($trainer);

        // Member query is tenant-scoped, so foreign ids are silently ignored.
        return Member::whereIn('id', $memberIds)->update(['trainer_id' => $trainer->id]);
    }

    public function unassignMember(User $trainer, Member $member): void
    {
        $this->ensureOwnTrainer($trainer);

        if ($member->trainer_id === $trainer->id) {
            $member->trainer_id = null;
            $member->save();
        }
    }

    public function delete(User $trainer): void
    {
        $this->ensureOwnTrainer($trainer);

        DB::transaction(function () use ($trainer) {
            Member::where('trainer_id', $trainer->id)->update(['trainer_id' => null]);
            $trainer->delete();
        });
    }

    protected function ensureOwnTrainer(User $trainer): void
    {
        if ((int) $trainer->gym_id !== $this->tenant->id() || ! $trainer->isTrainer()) {
            throw ValidationException::withMessages(['trainer' => 'Trainer not found in this gym.']);
        }
    }
}
