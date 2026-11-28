<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Models\Attendance;
use App\Models\Member;
use App\Services\AttendanceService;
use App\Services\CheckInCodeService;
use App\Services\GymQrResolver;
use App\Services\StreakService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Member self check-in / check-out from the mobile app ("CHECK IN NOW" and scanning the gym's QR).
 * Responses include the refreshed streak so the app can update the card immediately.
 */
class CheckInController extends ApiController
{
    public function __construct(
        protected AttendanceService $attendance,
        protected StreakService $streaks,
        protected GymQrResolver $qr,
        protected CheckInCodeService $codes,
    ) {}

    /** POST /api/v1/me/check-in. "code" (the scanned check-in QR) is optional for now, but must be right when sent. */
    public function checkIn(Request $request): JsonResponse
    {
        /** @var Member $member */
        $member = $request->user('member');

        if (! $member->gym->allowsSelfCheckIn()) {
            throw new ApiException('Self check-in is not enabled at your gym. Please show your QR code at the front desk.', 'SELF_CHECKIN_DISABLED', 403);
        }

        $this->verifyCode($request, $member);
        $this->ensureOpenNow($member);

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

    /**
     * POST /api/v1/me/check-in/qr { "qr": "<scanned text>" } — the member scans the gym's QR code
     * (join / registration link, or the check-in QR) to mark today's attendance. Scanning the desk QR
     * (registration link) again after checking in checks the member out, once a few minutes have passed.
     */
    public function qrCheckIn(Request $request): JsonResponse
    {
        /** @var Member $member */
        $member = $request->user('member');
        $data = $request->validate(['qr' => ['required', 'string', 'max:2000']]);
        $gym = $member->gym;
        $scan = $this->qr->resolve($data['qr'], $gym);

        if (! $scan) {
            $this->rejectScan($member, $data['qr'], 'INVALID_QR', 'This isn\'t a Fitnix gym QR code. Scan the QR code at your gym\'s front desk.', 422);
        }

        if ($scan['gym']->id !== $member->gym_id) {
            $this->rejectScan($member, $data['qr'], 'WRONG_GYM', 'This QR belongs to another gym.', 403);
        }

        $today = $scan['kind'] === GymQrResolver::KIND_REGISTER ? $this->attendance->today($member) : null;

        // A gym showing the live screen QR wants members at the desk: printed codes and links are not enough
        // to check in. The printed desk QR (registration link) may still close a visit that is already open.
        if ($this->codes->mode($gym) === CheckInCodeService::SCREEN && $scan['kind'] !== GymQrResolver::KIND_CHECKIN_SCREEN && ! $today) {
            $this->rejectScan($member, $data['qr'], 'QR_EXPIRED', 'Please scan the live check-in QR on the screen at the front desk.', 422);
        }

        // Scanning the desk QR again while checked in checks the member out.
        if ($today) {
            if ($today->check_out_at) {
                throw new ApiException('Your visit today is already complete ('.$today->check_in_at->format('h:i A').' – '.$today->check_out_at->format('h:i A').').', 'ALREADY_CHECKED_OUT', 409, $this->statusPayload($member));
            }

            try {
                $this->attendance->ensureQrCheckOutIsNotTooSoon($today);
            } catch (ValidationException $e) {
                throw new ApiException(collect($e->errors())->flatten()->first(), 'CHECK_OUT_TOO_SOON', 409, $this->statusPayload($member));
            }

            $record = $this->attendance->checkOut($today);

            return $this->success($this->statusPayload($member) + [
                'action' => 'checked_out',
                'source' => $record->source(),
                'streak' => $this->streaks->summary($member),
            ], 'Checked out at '.$record->check_out_at->format('h:i A').' ('.$record->durationLabel().'). See you next time!');
        }

        if (! $member->memberships()->valid()->exists()) {
            throw new ApiException('You don\'t have an active membership today. Please renew to check in.', 'NO_ACTIVE_MEMBERSHIP', 403);
        }

        if (! $gym->allowsSelfCheckIn()) {
            throw new ApiException('Self check-in is not enabled at your gym. Please show your QR code at the front desk.', 'SELF_CHECKIN_DISABLED', 403);
        }

        if (! $gym->allowsQrCheckIn()) {
            throw new ApiException('QR check-in is not enabled at your gym. Please check in at the front desk.', 'QR_CHECKIN_DISABLED', 403);
        }

        $this->ensureOpenNow($member);

        try {
            $record = $this->attendance->checkInByGymQr($member);
        } catch (ValidationException $e) {
            throw new ApiException(collect($e->errors())->flatten()->first(), 'CHECK_IN_FAILED', 422);
        }

        if (! $record) {
            $existing = $this->attendance->today($member);

            throw new ApiException('You already checked in today at '.$existing->check_in_at->format('h:i A').'.', 'ALREADY_CHECKED_IN', 409, $this->statusPayload($member));
        }

        return $this->success($this->statusPayload($member) + [
            'action' => 'checked_in',
            'source' => $record->source(),
            'streak' => $this->streaks->summary($member),
        ], "Checked in at {$gym->name}. Have a great workout!");
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

    /** GET /api/v1/me/check-in — today's status; the app uses it to decide whether to show the scan popup. */
    public function status(Request $request): JsonResponse
    {
        return $this->success($this->statusPayload($request->user('member')), 'Check-in status.');
    }

    /** @return array<string, mixed> */
    protected function statusPayload(Member $member): array
    {
        $gym = $member->gym;
        $today = $this->attendance->today($member);

        return [
            'state' => match (true) {
                ! $today => 'not_checked_in',
                ! $today->check_out_at => 'checked_in',
                default => 'checked_out',
            },
            'checked_in_today' => (bool) $today,
            'attendance' => $today ? $this->attendancePayload($today) : null,
            'has_active_membership' => $member->memberships()->valid()->exists(),
            'self_checkin_enabled' => $gym->allowsSelfCheckIn(),
            'qr_checkin_enabled' => $gym->allowsSelfCheckIn() && $gym->allowsQrCheckIn(),
            // printed = any of the gym's QR codes work; screen = only the live QR on the desk screen.
            'qr_mode' => $this->codes->mode($gym),
        ];
    }

    /** Log a rejected scan (for abuse checks) and answer with the error. */
    protected function rejectScan(Member $member, string $qr, string $code, string $message, int $status): never
    {
        Log::warning('Gym QR check-in rejected', [
            'member_id' => $member->id,
            'gym_id' => $member->gym_id,
            'reason' => $code,
            'qr' => Str::limit($qr, 500),
        ]);

        throw new ApiException($message, $code, $status);
    }

    /** When the gym only allows check-ins during opening hours. */
    protected function ensureOpenNow(Member $member): void
    {
        if ($member->gym->limitsCheckInToOpeningHours() && ! $member->gym->isOpenAt(now())) {
            throw new ApiException("{$member->gym->name} is closed right now. You can check in during opening hours.", 'GYM_CLOSED', 403);
        }
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
            'source' => $attendance->source(),
        ];
    }
}
