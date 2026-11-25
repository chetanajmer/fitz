<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Services\BodyProgressService;
use Illuminate\Http\Request;
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

    /** Member portal: the signed-in member's own photo. */
    public function own(Request $request, int $photoId, BodyProgressService $body): StreamedResponse
    {
        return $body->photoResponse($request->user()->memberProfile()->firstOrFail()->progressPhotos()->findOrFail($photoId));
    }
}
