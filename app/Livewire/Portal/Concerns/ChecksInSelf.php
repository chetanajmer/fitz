<?php

namespace App\Livewire\Portal\Concerns;

use App\Models\Member;
use App\Services\AttendanceService;
use Illuminate\Support\Facades\Auth;

/** Member portal: the signed-in member checks themself in and out (when the gym allows it). */
trait ChecksInSelf
{
    public function checkIn(AttendanceService $attendance): void
    {
        $record = $attendance->selfCheckIn($this->portalMember());
        $this->dispatch('notify', message: 'Checked in at '.$record->check_in_at->format('h:i A').'. Have a great workout!');
    }

    public function checkOut(AttendanceService $attendance): void
    {
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
