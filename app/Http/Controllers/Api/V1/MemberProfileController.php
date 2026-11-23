<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DietPreference;
use App\Http\Resources\V1\MemberResource;
use App\Models\Member;
use App\Services\MemberMembershipService;
use App\Services\MemberProfileService;
use App\Services\MemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MemberProfileController extends ApiController
{
    public function __construct(
        protected MemberMembershipService $memberships,
        protected MemberProfileService $profiles,
        protected MemberService $members,
    ) {}

    /** GET /api/v1/me — the signed-in member's profile (same shape as the login response "member"). */
    public function show(Request $request): JsonResponse
    {
        return $this->success((new MemberResource($request->user('member')))->resolve($request), 'Profile.');
    }

    /** GET /api/v1/me/membership — the home-screen membership card plus membership history. */
    public function membership(Request $request): JsonResponse
    {
        /** @var Member $member */
        $member = $request->user('member');

        return $this->success([
            'card' => $this->memberships->card($member),
            'history' => $this->memberships->history($member),
        ], 'Membership.');
    }

    /** GET /api/v1/me/profile — everything for the profile screen. */
    public function profile(Request $request): JsonResponse
    {
        return $this->success($this->profiles->full($request->user('member')), 'Profile.');
    }

    /**
     * PUT|PATCH /api/v1/me/profile — the member edits their own details.
     * Only fields sent are changed; send null to clear one. Name and phone are managed by the gym.
     */
    public function update(Request $request): JsonResponse
    {
        /** @var Member $member */
        $member = $request->user('member');

        $data = $request->validate([
            'email' => ['sometimes', 'nullable', 'email', 'max:255',
                // A web-portal login uses the email as username, so it must stay unique among users.
                Rule::unique('users', 'email')->ignore($member->user_id)],
            'gender' => ['sometimes', 'nullable', 'in:male,female,other'],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today', 'after:1900-01-01'],
            'blood_group' => ['sometimes', 'nullable', 'in:A+,A-,B+,B-,AB+,AB-,O+,O-'],
            'height_cm' => ['sometimes', 'nullable', 'numeric', 'between:50,250'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:80'],
            'emergency_contact_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'emergency_contact_phone' => ['sometimes', 'nullable', 'regex:/^\+?[0-9\s\-]{8,15}$/'],
            'emergency_contact_relation' => ['sometimes', 'nullable', 'string', 'max:50'],
            'fitness_goal' => ['sometimes', 'nullable', 'string', 'max:255'],
            'diet_preference' => ['sometimes', 'nullable', Rule::enum(DietPreference::class)],
            'medical_notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $data = collect($data)->only(MemberProfileService::EDITABLE_FIELDS)
            ->map(fn ($value) => is_string($value) && trim($value) === '' ? null : $value)
            ->all();

        if ($data === []) {
            return $this->success($this->profiles->full($member), 'Nothing to update.');
        }

        $this->members->update($member, $data);

        return $this->success($this->profiles->full($member->refresh()), 'Profile updated.');
    }

    /** POST /api/v1/me/profile/photo (multipart, field "photo") — upload or replace the profile photo. */
    public function updatePhoto(Request $request): JsonResponse
    {
        $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096']]);

        /** @var Member $member */
        $member = $request->user('member');
        $this->members->update($member, [], [], $request->file('photo'));

        return $this->success(['photo_url' => $member->refresh()->photoUrl(), 'has_photo' => true], 'Photo updated.');
    }

    /** DELETE /api/v1/me/profile/photo — remove the profile photo (falls back to initials). */
    public function deletePhoto(Request $request): JsonResponse
    {
        /** @var Member $member */
        $member = $request->user('member');

        if ($member->photo_path) {
            Storage::disk('public')->delete($member->photo_path);
            $member->forceFill(['photo_path' => null])->save();
        }

        return $this->success(['photo_url' => $member->photoUrl(), 'has_photo' => false], 'Photo removed.');
    }
}
