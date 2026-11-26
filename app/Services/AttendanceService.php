<?php

namespace App\Services;

use App\Enums\MemberStatus;
use App\Models\Attendance;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function __construct(protected StreakService $streaks, protected CheckInCodeService $codes) {}

    /**
     * Check a member in for today. Duplicate check-ins are rejected both here and by the
     * unique (member_id, date) index, which also covers concurrent double-scans.
     */
    public function checkIn(Member $member, string $method = 'manual', bool $requireValidMembership = true): Attendance
    {
        if ($member->status !== MemberStatus::Active) {
            throw ValidationException::withMessages(['member' => "{$member->name} is marked inactive."]);
        }

        if ($requireValidMembership && ! $member->memberships()->valid()->exists()) {
            throw ValidationException::withMessages(['member' => "{$member->name} has no active membership today."]);
        }

        $frozen = $member->memberships()->valid()->with('freezes')->get()->map->freezeOn()->filter()->first();
        if ($frozen) {
            throw ValidationException::withMessages(['member' => "{$member->name}'s membership is frozen until {$frozen->end_date->format('d M Y')}. End the freeze on their page first."]);
        }

        $today = Carbon::today()->toDateString();

        $existing = Attendance::where('member_id', $member->id)->whereDate('date', $today)->first();
        if ($existing) {
            throw ValidationException::withMessages(['member' => "{$member->name} already checked in today at {$existing->check_in_at->format('h:i A')}."]);
        }

        try {
            $attendance = new Attendance;
            $attendance->gym_id = $member->gym_id;
            $attendance->member_id = $member->id;
            $attendance->date = $today;
            $attendance->check_in_at = now();
            $attendance->method = $method;
            // Staff/portal user who recorded it; null for mobile-app self check-ins (member token).
            $attendance->recorded_by = Auth::guard('web')->id();
            $attendance->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['member' => "{$member->name} already checked in today."]);
        }

        $this->streaks->refresh($member);

        return $attendance;
    }

    /** Today's attendance row for a member, if any. */
    public function today(Member $member): ?Attendance
    {
        return Attendance::where('member_id', $member->id)->whereDate('date', Carbon::today())->first();
    }

    /**
     * Member checks themselves in (portal button / mobile app). Allowed only when the gym
     * has member self check-in switched on.
     */
    public function selfCheckIn(Member $member): Attendance
    {
        if (! $member->gym->allowsSelfCheckIn()) {
            throw ValidationException::withMessages(['member' => 'Self check-in is not enabled at your gym. Please show your QR code at the front desk.']);
        }

        return $this->checkIn($member, 'self');
    }

    /** The member must be at the gym: the scanned QR has to be their own gym's current check-in code. */
    public function verifyGymCode(Member $member, string $scanned): void
    {
        if (! $this->codes->accepts($member->gym, $scanned)) {
            throw ValidationException::withMessages(['member' => "That code has expired or isn't {$member->gym->name}'s check-in QR. Scan the one on display at the gym."]);
        }
    }

    public function selfCheckOut(Member $member): Attendance
    {
        $attendance = $this->today($member);

        if (! $attendance) {
            throw ValidationException::withMessages(['member' => 'You have not checked in today.']);
        }

        return $this->checkOut($attendance);
    }

    public function checkOut(Attendance $attendance): Attendance
    {
        if ($attendance->check_out_at) {
            throw ValidationException::withMessages(['member' => 'Already checked out at '.$attendance->check_out_at->format('h:i A').'.']);
        }

        $attendance->check_out_at = now();
        $attendance->save();

        return $attendance;
    }

    /**
     * QR flow: a scanned member QR toggles check-in → check-out for the day.
     * The token lookup is tenant-scoped, so a QR from another gym resolves to nothing.
     *
     * @return array{attendance: Attendance, action: 'checked_in'|'checked_out'}
     */
    public function handleQrScan(string $token, ?User $actor = null): array
    {
        $member = Member::where('qr_token', trim($token))->first();

        if (! $member) {
            throw ValidationException::withMessages(['member' => 'QR code not recognised for this gym.']);
        }

        if ($actor && ! $actor->can('checkIn', $member)) {
            throw ValidationException::withMessages(['member' => "You are not allowed to record attendance for {$member->name}."]);
        }

        $today = Attendance::where('member_id', $member->id)->whereDate('date', Carbon::today())->first();

        if ($today && ! $today->check_out_at) {
            return ['attendance' => $this->checkOut($today), 'action' => 'checked_out'];
        }

        return ['attendance' => $this->checkIn($member, 'qr'), 'action' => 'checked_in'];
    }
}
