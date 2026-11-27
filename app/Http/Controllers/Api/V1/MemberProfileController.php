<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\UpdateMemberProfileRequest;
use App\Http\Resources\V1\MemberResource;
use App\Models\Member;
use App\Services\MemberMembershipService;
use App\Services\MemberProfileService;
use App\Services\MemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
     * Only fields sent are changed; send null to clear one. Name, phone, member code, gym and membership
     * are managed by the gym (FIELD_NOT_EDITABLE). Returns the same object as GET /me/profile.
     */
    public function update(UpdateMemberProfileRequest $request): JsonResponse
    {
        $member = $request->member();
        $data = $request->profileData();

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
