<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\MemberStatus;
use App\Exceptions\ApiException;
use App\Models\Attendance;
use App\Models\Member;
use App\Services\AttendanceService;
use App\Services\CheckInCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Front-desk attendance in the staff app: /api/v1/admin/attendance*, /admin/check-in-qr.
 * Staff check members in by hand (members have no QR to show in v1).
 */
class AttendanceController extends AdminController
{
    public function __construct(protected AttendanceService $attendance) {}

    /** GET /admin/attendance/today?search=&page=&per_page= — counts cover the whole day; the list is searchable. */
    public function today(Request $request): JsonResponse
    {
        $user = $this->staff($request);
        $today = Carbon::today();

        $base = Attendance::whereDate('date', $today)
            ->when(! $user->can('members.all'), fn ($q) => $q->whereHas('member', fn ($m) => $m->where('trainer_id', $user->id)));

        $page = (clone $base)->with('member')
            ->when(filled($request->input('search')), fn ($q) => $q->whereHas('member', fn ($m) => $m->search($request->input('search'))))
            ->latest('check_in_at')
            ->paginate($this->perPage($request));

        return $this->success([
            'checked_in' => (clone $base)->count(),
            'inside_now' => (clone $base)->whereNull('check_out_at')->count(),
            'list' => collect($page->items())->map(fn (Attendance $a) => $this->attendanceData($a))->all(),
            'pagination' => $this->pagination($page),
        ], 'Today\'s attendance.');
    }

    /** POST /admin/attendance/check-in { member_id } */
    public function checkIn(Request $request): JsonResponse
    {
        $data = $request->validate(['member_id' => ['required', 'integer']]);
        $user = $this->staff($request);
        $member = Member::visibleTo($user)->findOrFail($data['member_id']);

        if (! $user->can('checkIn', $member)) {
            throw $this->forbidden();
        }

        $attendance = DB::transaction(function () use ($member) {
            Member::whereKey($member->id)->lockForUpdate()->first();

            if ($member->status !== MemberStatus::Active) {
                throw new ApiException("{$member->name} is marked {$member->status->label()}.", 'MEMBER_INACTIVE', 422);
            }

            if ($existing = $this->attendance->today($member)) {
                throw new ApiException("{$member->name} already checked in today at {$existing->check_in_at->format('h:i A')}.", 'ALREADY_CHECKED_IN', 409, ['attendance' => $this->attendanceData($existing, false)]);
            }

            if (! $member->memberships()->valid()->exists()) {
                throw new ApiException("{$member->name} has no active membership today.", 'NO_ACTIVE_MEMBERSHIP', 422);
            }

            try {
                return $this->attendance->checkIn($member);
            } catch (ValidationException $e) {
                throw new ApiException(collect($e->errors())->flatten()->first() ?? 'Could not check in.', 'ALREADY_CHECKED_IN', 409);
            }
        });

        return $this->success($this->attendanceData($attendance->load('member')), "{$member->name} checked in at {$attendance->check_in_at->format('h:i A')}.", 201);
    }

    /** POST /admin/attendance/{id}/check-out */
    public function checkOut(Request $request, int $attendanceId): JsonResponse
    {
        $user = $this->staff($request);
        $attendance = Attendance::with('member')->findOrFail($attendanceId);

        if (! $attendance->member || ! $user->can('view', $attendance->member)) {
            abort(404);
        }

        if (! $user->can('checkIn', $attendance->member)) {
            throw $this->forbidden();
        }

        if ($attendance->check_out_at) {
            throw new ApiException('Already checked out at '.$attendance->check_out_at->format('h:i A').'.', 'ALREADY_CHECKED_OUT', 409);
        }

        $this->attendance->checkOut($attendance);

        return $this->success($this->attendanceData($attendance), "{$attendance->member->name} checked out.");
    }

    /** GET /admin/check-in-qr — the gym's check-in QR for the "show this at the desk" screen. */
    public function checkInQr(Request $request, CheckInCodeService $codes): JsonResponse
    {
        return $this->success($this->checkInQrData($this->staff($request)->gym->fresh(), $codes), 'Check-in QR.');
    }
}
