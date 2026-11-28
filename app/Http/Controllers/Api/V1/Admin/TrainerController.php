<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ApiException;
use App\Models\Member;
use App\Models\User;
use App\Services\TrainerService;
use App\Support\TrainerAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Trainer management for the staff app (gym admins: permission trainers.manage, which is never grantable
 * to trainers). Mirrors the web panel's Trainers pages through the same TrainerService.
 */
class TrainerController extends AdminController
{
    public function __construct(protected TrainerService $trainers) {}

    /** GET /admin/trainers?search=&status=active|inactive */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);
        $search = trim((string) ($data['search'] ?? ''));

        $trainers = User::trainersOf($this->staff($request)->gym_id)
            ->with('permissions')
            ->withCount('assignedMembers')
            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like));
            })
            ->orderBy('name')
            ->get();

        return $this->success([
            'trainers' => $trainers->map(fn (User $trainer) => $this->trainerData($trainer))->values(),
        ], 'Trainers.');
    }

    /** GET /admin/trainers/{id} — details, assigned members and the access panel. */
    public function show(Request $request, int $trainerId): JsonResponse
    {
        return $this->success($this->detail($this->findTrainer($request, $trainerId)), 'Trainer.');
    }

    /** POST /admin/trainers (JSON, or multipart with "photo") */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, null);

        $trainer = $this->trainers->create($data, $request->file('photo'));

        return $this->success($this->detail($trainer->fresh()), 'Trainer added.', 201);
    }

    /** PUT /admin/trainers/{id} — partial; password only when it should change. Photo: POST /admin/trainers/{id}/photo. */
    public function update(Request $request, int $trainerId): JsonResponse
    {
        $trainer = $this->findTrainer($request, $trainerId);
        $data = $this->validated($request, $trainer);

        $this->trainers->update($trainer, $data);

        return $this->success($this->detail($trainer->fresh()), 'Trainer saved.');
    }

    /** POST /admin/trainers/{id}/photo (multipart: photo, ≤ 2 MB) */
    public function uploadPhoto(Request $request, int $trainerId): JsonResponse
    {
        $trainer = $this->findTrainer($request, $trainerId);
        $request->validate(['photo' => ['required', 'image', 'max:2048']]);

        $this->trainers->update($trainer, [], $request->file('photo'));

        return $this->success($this->detail($trainer->fresh()), 'Photo updated.');
    }

    /**
     * POST /admin/trainers/{id}/toggle { status?: active|inactive } — without a body it flips the status.
     * An inactive trainer cannot sign in; their app gets 403 ACCOUNT_DISABLED on the next request.
     */
    public function toggle(Request $request, int $trainerId): JsonResponse
    {
        $trainer = $this->findTrainer($request, $trainerId);
        $data = $request->validate(['status' => ['nullable', Rule::in(['active', 'inactive'])]]);

        $this->trainers->setStatus($trainer, $data['status'] ?? ($trainer->status === 'active' ? 'inactive' : 'active'));

        return $this->success($this->detail($trainer->fresh()), $trainer->status === 'active' ? 'Trainer switched on.' : 'Trainer switched off.');
    }

    /** DELETE /admin/trainers/{id} — their members become unassigned and their app sessions end. */
    public function destroy(Request $request, int $trainerId): JsonResponse
    {
        $trainer = $this->findTrainer($request, $trainerId);

        $trainer->tokens()->delete();
        $this->trainers->delete($trainer);

        return $this->success(null, 'Trainer removed. Their members are now unassigned.');
    }

    /** PUT /admin/trainers/{id}/access { permissions: [...] } or { preset: trainer|registration|front_desk|none } */
    public function access(Request $request, int $trainerId): JsonResponse
    {
        $trainer = $this->findTrainer($request, $trainerId);
        $data = $request->validate([
            'preset' => ['required_without:permissions', 'prohibits:permissions', Rule::in(array_keys(TrainerAccess::PRESETS))],
            'permissions' => ['required_without:preset', 'array'],
            'permissions.*' => ['string', Rule::in(TrainerAccess::all())],
        ]);

        $permissions = isset($data['preset']) ? TrainerAccess::PRESETS[$data['preset']]['permissions'] : $data['permissions'];
        $this->trainers->setPermissions($trainer, $permissions);

        return $this->success($this->detail($trainer->fresh()), 'Access updated.');
    }

    /**
     * POST /admin/trainers/{id}/password { password, password_confirmation }
     * The trainer is signed out of the staff app and the website and signs in again with the new password.
     */
    public function password(Request $request, int $trainerId): JsonResponse
    {
        $trainer = $this->findTrainer($request, $trainerId);
        $data = $request->validate(['password' => ['required', 'confirmed', Password::min(8)]]);

        $this->trainers->setPassword($trainer, $data['password']);

        return $this->success($this->detail($trainer->fresh()), 'Password reset. The trainer has been signed out and signs in with the new password.');
    }

    /** GET /admin/trainers/access-options — the access checklist for the "Add trainer" screen (defaults ticked). */
    public function accessOptions(): JsonResponse
    {
        return $this->success(['access' => $this->accessCatalog(TrainerAccess::DEFAULTS)], 'Access options.');
    }

    /** POST /admin/trainers/{id}/members { member_ids: [] } — members of other gyms are ignored. */
    public function assignMembers(Request $request, int $trainerId): JsonResponse
    {
        $trainer = $this->findTrainer($request, $trainerId);
        $data = $request->validate([
            'member_ids' => ['required', 'array', 'min:1', 'max:200'],
            'member_ids.*' => ['integer'],
        ]);

        $count = $this->trainers->assignMembers($trainer, array_map('intval', $data['member_ids']));

        return $this->success($this->detail($trainer->fresh()) + ['assigned' => $count], "{$count} member(s) assigned.");
    }

    /** DELETE /admin/trainers/{id}/members/{memberId} */
    public function unassignMember(Request $request, int $trainerId, int $memberId): JsonResponse
    {
        $trainer = $this->findTrainer($request, $trainerId);
        $member = Member::find($memberId) ?? throw new ApiException('Member not found.', 'NOT_FOUND', 404);

        $this->trainers->unassignMember($trainer, $member);

        return $this->success($this->detail($trainer->fresh()), 'Member unassigned.');
    }

    /**
     * Same rules as the web panel's trainer form. On update every field is optional.
     *
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?User $trainer): array
    {
        $creating = $trainer === null;
        $sometimes = $creating ? [] : ['sometimes'];

        $data = $request->validate([
            'name' => [...$sometimes, 'required', 'string', 'max:120'],
            'email' => [...$sometimes, 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($trainer?->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'specialization' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'status' => [...$sometimes, $creating ? 'nullable' : 'required', Rule::in(['active', 'inactive'])],
            'password' => [$creating ? 'required' : 'nullable', Password::min(8)],
            'photo' => $creating ? ['nullable', 'image', 'max:2048'] : ['prohibited'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in(TrainerAccess::all())],
        ], [
            'photo.prohibited' => 'Upload the photo with POST /admin/trainers/{id}/photo.',
        ]);

        unset($data['photo']);
        if (! $creating) {
            unset($data['permissions']); // access changes go through PUT /admin/trainers/{id}/access
        }

        return array_map(fn (mixed $value) => is_string($value) && trim($value) === '' ? null : $value, $data);
    }

    protected function findTrainer(Request $request, int $trainerId): User
    {
        return User::trainersOf($this->staff($request)->gym_id)->find($trainerId)
            ?? throw new ApiException('Trainer not found.', 'NOT_FOUND', 404);
    }

    /** @return array<string, mixed> */
    protected function trainerData(User $trainer): array
    {
        return [
            'id' => $trainer->id,
            'name' => $trainer->name,
            'email' => $trainer->email,
            'phone' => $trainer->phone,
            'photo_url' => $trainer->photoUrl(),
            'specialization' => $trainer->specialization,
            'status' => $trainer->status,
            'members_count' => (int) ($trainer->assigned_members_count ?? $trainer->assignedMembers()->count()),
            'permissions' => $this->granted($trainer),
            'last_login_at' => $this->iso($trainer->last_login_at),
        ];
    }

    /** @return array<string, mixed> */
    protected function detail(User $trainer): array
    {
        $granted = $this->granted($trainer);
        $members = Member::where('trainer_id', $trainer->id)->with('latestMembership')->orderBy('name')->get();

        return [
            'trainer' => $this->trainerData($trainer) + [
                'bio' => $trainer->bio,
                'created_at' => $this->iso($trainer->created_at),
            ],
            'members' => $members->map(fn (Member $member) => $this->memberBrief($member) + [
                'status' => $member->status,
                'membership' => $member->latestMembership ? $this->membershipData($member->latestMembership) : null,
            ])->values()->all(),
            'access' => $this->accessCatalog($granted),
        ];
    }

    /**
     * The sections an admin can switch on, with presets and defaults.
     *
     * @param  list<string>  $granted
     * @return array{sections: list<array<string, mixed>>, presets: list<array<string, mixed>>, defaults: list<string>, requires: array<string, string>}
     */
    protected function accessCatalog(array $granted): array
    {
        return [
            'sections' => collect(TrainerAccess::SECTIONS)->map(fn (array $items, string $title) => [
                'title' => $title,
                'permissions' => collect($items)->map(fn (array $item, string $key) => [
                    'key' => $key,
                    'label' => $item['label'],
                    'hint' => $item['hint'],
                    'granted' => in_array($key, $granted, true),
                ])->values()->all(),
            ])->values()->all(),
            'presets' => collect(TrainerAccess::PRESETS)->map(fn (array $preset, string $key) => [
                'key' => $key,
                'label' => $preset['label'],
                'permissions' => $preset['permissions'],
            ])->values()->all(),
            'defaults' => TrainerAccess::DEFAULTS,
            'requires' => TrainerAccess::REQUIRES,
        ];
    }

    /** @return list<string> Sections the admin switched on for this trainer. */
    protected function granted(User $trainer): array
    {
        return $trainer->getDirectPermissions()->pluck('name')->intersect(TrainerAccess::all())->sort()->values()->all();
    }
}
