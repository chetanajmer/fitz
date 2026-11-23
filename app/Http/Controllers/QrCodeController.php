<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Services\QrCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class QrCodeController extends Controller
{
    public function __construct(protected QrCodeService $qr) {}

    /** The gym's QR registration code (encodes /join/{token}). */
    public function join(Request $request)
    {
        $gym = current_gym();

        return $this->svgResponse($this->qr->svg($gym->joinUrl(), 600), $request->boolean('download') ? "{$gym->slug}-join-qr.svg" : null);
    }

    /** A member's attendance QR (encodes the staff scan URL). */
    public function member(Request $request, Member $member)
    {
        Gate::authorize('view', $member);

        return $this->svgResponse($this->qr->svg(route('gym.attendance.scan', $member->qr_token), 400), $request->boolean('download') ? "{$member->member_code}-qr.svg" : null);
    }

    /** The signed-in member's own attendance QR. */
    public function self()
    {
        $member = request()->user()->memberProfile()->firstOrFail();

        return $this->svgResponse($this->qr->svg(route('gym.attendance.scan', $member->qr_token), 400));
    }

    protected function svgResponse(string $svg, ?string $downloadName = null)
    {
        $headers = ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'private, max-age=300'];

        if ($downloadName) {
            $headers['Content-Disposition'] = 'attachment; filename="'.$downloadName.'"';
        }

        return response($svg, 200, $headers);
    }
}
