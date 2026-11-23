<?php

namespace App\Http\Controllers;

use App\Services\AttendanceService;
use Illuminate\Validation\ValidationException;

/**
 * Hit when gym staff scan a member's QR code with a phone camera while signed in.
 * Toggles check-in / check-out for today. Tenant scoping ensures a QR from another
 * gym is rejected.
 */
class AttendanceScanController extends Controller
{
    public function __invoke(string $token, AttendanceService $attendance)
    {
        try {
            $result = $attendance->handleQrScan($token, auth()->user());
            $member = $result['attendance']->member;
            $message = $result['action'] === 'checked_in'
                ? "{$member->name} checked in at ".$result['attendance']->check_in_at->format('h:i A')
                : "{$member->name} checked out at ".$result['attendance']->check_out_at->format('h:i A');

            return redirect()->route('gym.attendance.index')->with('success', $message);
        } catch (ValidationException $e) {
            return redirect()->route('gym.attendance.index')->with('error', collect($e->errors())->flatten()->first());
        }
    }
}
