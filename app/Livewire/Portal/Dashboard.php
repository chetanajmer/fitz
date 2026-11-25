<?php

namespace App\Livewire\Portal;

use App\Livewire\Portal\Concerns\LogsProgramDays;
use App\Models\Member;
use App\Services\AttendanceService;
use App\Services\ProgramService;
use App\Services\StreakService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.portal')]
#[Title('My membership')]
class Dashboard extends Component
{
    use LogsProgramDays;

    /** The signed-in member's own profile — never taken from request input. */
    protected function member(): Member
    {
        $member = Auth::user()->memberProfile()->with(['trainer', 'gym'])->first();

        abort_unless($member, 403, 'No member profile is linked to this account.');

        return $member;
    }

    public function checkIn(AttendanceService $attendance): void
    {
        $record = $attendance->selfCheckIn($this->member());
        $streak = app(StreakService::class)->summary($this->member());
        $this->dispatch('streak-grew', count: $streak['current_streak'], milestone: in_array($streak['current_streak'], $streak['milestones'], true));
        $this->dispatch('notify', message: 'Checked in at '.$record->check_in_at->format('h:i A').'. Have a great workout!');
    }

    public function checkOut(AttendanceService $attendance): void
    {
        $record = $attendance->selfCheckOut($this->member());
        $this->dispatch('notify', message: 'Checked out at '.$record->check_out_at->format('h:i A').' ('.$record->durationLabel().').');
    }

    public function render(): View
    {
        $member = $this->member();

        return view('livewire.portal.dashboard', [
            'member' => $member,
            'current' => $member->currentMembership(),
            'latest' => $member->latestMembership()->first(),
            'state' => $member->membershipState(),
            'pending' => $member->pendingAmount(),
            'memberships' => $member->memberships()->where('status', '!=', 'cancelled')->limit(10)->get(),
            'payments' => $member->payments()->limit(10)->get(),
            'attendance' => $member->attendances()->limit(15)->get(),
            'todayVisit' => app(AttendanceService::class)->today($member),
            'selfCheckIn' => $member->gym->allowsSelfCheckIn(),
            'streak' => app(StreakService::class)->summary($member),
            'programs' => app(ProgramService::class)->overview($member),
            'visitsThisMonth' => $member->attendances()->reorder()->where('date', '>=', now()->startOfMonth())->count(),
        ]);
    }
}
