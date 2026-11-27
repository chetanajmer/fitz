<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Member;
use App\Models\MemberConsent;
use App\Services\ConsentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Versioned consents (progress photos, AI coach) and deleting the member's AI data. */
class ConsentController extends ApiController
{
    public function __construct(protected ConsentService $consents) {}

    /** GET /api/v1/me/consents */
    public function index(Request $request): JsonResponse
    {
        return $this->success($this->consents->all($this->member($request)), 'Consents.');
    }

    /** POST /api/v1/me/consents { type: progress_photos|ai_coach, accepted: bool } */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(MemberConsent::TYPES)],
            'accepted' => ['required', 'boolean'],
        ]);

        $member = $this->member($request);
        $accepted = (bool) $data['accepted'];
        $deleted = $this->consents->set($member, $data['type'], $accepted, $request);

        return $this->success($this->consents->all($member) + ($accepted ? [] : ['deleted' => $deleted]),
            $accepted ? 'Thank you. Consent saved.' : 'Consent withdrawn.');
    }

    /** DELETE /api/v1/me/ai/data — withdraw both consents and delete all progress photos and AI reports. */
    public function destroyAiData(Request $request): JsonResponse
    {
        $member = $this->member($request);
        $deleted = $this->consents->deleteAiData($member, $request);

        return $this->success(['deleted' => $deleted] + $this->consents->all($member), 'Your progress photos and AI reports were deleted.');
    }

    protected function member(Request $request): Member
    {
        return $request->user('member');
    }
}
