<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Services\BodyProgressService;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** A member's private progress photo, for gym staff who may use AI progress for that member. */
class ProgressPhotoController extends Controller
{
    public function __invoke(Member $member, int $photoId, BodyProgressService $body): StreamedResponse
    {
        Gate::authorize('useAi', $member);

        return $body->photoResponse($member->progressPhotos()->findOrFail($photoId));
    }
}
