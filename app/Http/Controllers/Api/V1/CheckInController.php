<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Models\Attendance;
use App\Models\Member;
use App\Services\AttendanceService;
use App\Services\StreakService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Member self check-in / check-out from the mobile app ("CHECK IN NOW").
 * Responses include the refreshed streak so the app can update the card immediately.
 */
class CheckInController extends ApiController
{
    public function __construct(protected AttendanceService $attendance, protected StreakService $streaks) {}

    /** POST /api/v1/me/check-in. "code" (the scanned check-in QR) is optional for now, but must be right when sent. */
    public function checkIn(Request $request): JsonResponse
    {
        /** @var Member $member */
        $member = $request->user('member');

        if (! $member->gym->allowsSelfCheckIn()) {
            throw new ApiException('Self check-in is not enabled at your gym. Please show your QR code at the front desk.', 'SELF_CHECKIN_DISABLED', 403);
        }

        $this->verifyCode($request, $member);

        if ($existing = $this->attendance->today($member)) {
            throw new ApiException('You already checked in today at '.$existing->check_in_at->format('h:i A').'.', 'ALREADY_CHECKED_IN', 409, [
                'attendance' => $this->attendancePayload($existing),
            ]);
        }

        if (! $member->memberships()->valid()->exists()) {
            throw new ApiException('You don\'t have an active membership today. Please renew to check in.', 'NO_ACTIVE_MEMBERSHIP', 403);
        }

        try {
            $record = $this->attendance->selfCheckIn($member);
        } catch (ValidationException $e) {
            throw new ApiException(collect($e->errors())->flatten()->first(), 'CHECK_IN_FAILED', 422);
        }

        return $this->success([
            'attendance' => $this->attendancePayload($record),
            'streak' => $this->streaks->summary($member),
        ], 'Checked in. Have a great workout!', 201);
    }

    /** POST /api/v1/me/check-out */
    public function checkOut(Request $request): JsonResponse
    {
        /** @var Member $member */
        $member = $request->user('member');
        $this->verifyCode($request, $member);
        $today = $this->attendance->today($member);

        if (! $today) {
            throw new ApiException('You have not checked in today.', 'NOT_CHECKED_IN', 409);
        }

        if ($today->check_out_at) {
            throw new ApiException('You already checked out at '.$today->check_out_at->format('h:i A').'.', 'ALREADY_CHECKED_OUT', 409, [
                'attendance' => $this->attendancePayload($today),
            ]);
        }

        $record = $this->attendance->checkOut($today);

        return $this->success([
            'attendance' => $this->attendancePayload($record),
            'streak' => $this->streaks->summary($member),
        ], 'Checked out. See you next time!');
    }

    /** GET /api/v1/me/check-in — today's status for the button state. */
    public function status(Request $request): JsonResponse
    {
        /** @var Member $member */
        $member = $request->user('member');
        $today = $this->attendance->today($member);

        return $this->success([
            'self_checkin_enabled' => $member->gym->allowsSelfCheckIn(),
            'has_active_membership' => $member->memberships()->valid()->exists(),
            'state' => match (true) {
                ! $today => 'not_checked_in',
                ! $today->check_out_at => 'checked_in',
                default => 'checked_out',
            },
            'attendance' => $today ? $this->attendancePayload($today) : null,
        ], 'Check-in status.');
    }

    protected function verifyCode(Request $request, Member $member): void
    {
        $data = $request->validate(['code' => ['nullable', 'string', 'max:500']]);

        if (! isset($data['code'])) {
            return;
        }

        try {
            $this->attendance->verifyGymCode($member, $data['code']);
        } catch (ValidationException $e) {
            throw new ApiException(collect($e->errors())->flatten()->first(), 'INVALID_CHECKIN_CODE', 422);
        }
    }

    /** @return array<string, mixed> */
    protected function attendancePayload(Attendance $attendance): array
    {
        return [
            'date' => $attendance->date->toDateString(),
            'check_in_at' => $attendance->check_in_at->toIso8601String(),
            'check_out_at' => $attendance->check_out_at?->toIso8601String(),
            'duration' => $attendance->durationLabel(),
            'method' => $attendance->method,
        ];
    }
}
