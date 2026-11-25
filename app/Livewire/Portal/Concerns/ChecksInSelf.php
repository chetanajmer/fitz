<?php

namespace App\Livewire\Portal\Concerns;

use App\Models\Member;
use App\Services\AttendanceService;
use App\Services\StreakService;
use Illuminate\Support\Facades\Auth;

/**
 * Member portal: the signed-in member checks themself in and out (when the gym allows it).
 * $scanned is what their camera read from the gym's check-in QR.
 */
trait ChecksInSelf
{
    public function checkIn(string $scanned, AttendanceService $attendance): void
    {
        $attendance->verifyGymCode($this->portalMember(), $scanned);
        $record = $attendance->selfCheckIn($this->portalMember());
        $streak = app(StreakService::class)->summary($this->portalMember());
        $this->dispatch('streak-grew', count: $streak['current_streak'], milestone: in_array($streak['current_streak'], $streak['milestones'], true));
        $this->dispatch('notify', message: 'Checked in at '.$record->check_in_at->format('h:i A').'. Have a great workout!');
    }

    public function checkOut(string $scanned, AttendanceService $attendance): void
    {
        $attendance->verifyGymCode($this->portalMember(), $scanned);
        $record = $attendance->selfCheckOut($this->portalMember());
        $this->dispatch('notify', message: 'Checked out at '.$record->check_out_at->format('h:i A').' ('.$record->durationLabel().').');
    }

    /** The signed-in member's own profile — never taken from request input. */
    protected function portalMember(): Member
    {
        $member = Auth::user()->memberProfile()->with(['trainer', 'gym'])->first();

        abort_unless($member, 403, 'No member profile is linked to this account.');

        return $member;
    }
}
