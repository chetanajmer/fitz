<?php

namespace App\Livewire\Gym\Attendance;

use App\Models\Attendance;
use App\Models\Member;
use App\Services\AttendanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.gym')]
#[Title('Attendance')]
class Index extends Component
{
    public string $search = '';

    /** Value typed/scanned into the QR field — either a raw token or the full scan URL. */
    public string $scan = '';

    public function checkIn(int $memberId, AttendanceService $attendance): void
    {
        $member = Member::visibleTo(Auth::user())->findOrFail($memberId);
        Gate::authorize('checkIn', $member);

        $record = $attendance->checkIn($member);
        $this->search = '';
        $this->dispatch('notify', message: "{$member->name} checked in at {$record->check_in_at->format('h:i A')}.");
    }

    public function checkOut(int $attendanceId, AttendanceService $attendance): void
    {
        $record = Attendance::with('member')->findOrFail($attendanceId);
        Gate::authorize('checkIn', $record->member);

        $attendance->checkOut($record);
        $this->dispatch('notify', message: "{$record->member->name} checked out.");
    }

    public function submitScan(AttendanceService $attendance): void
    {
        $token = trim($this->scan);
        if (str_contains($token, '/')) {
            $token = basename(parse_url($token, PHP_URL_PATH) ?: $token);
        }
        $this->scan = '';

        if (! preg_match('/^[A-Za-z0-9]{20,64}$/', $token)) {
            $this->addError('scan', 'That does not look like a Gymai member QR code.');

            return;
        }

        $result = $attendance->handleQrScan($token, auth()->user());
        $member = $result['attendance']->member;
        $this->dispatch('notify', message: $result['action'] === 'checked_in' ? "{$member->name} checked in." : "{$member->name} checked out.");
    }

    public function render(): View
    {
        $user = Auth::user();
        $today = Carbon::today();

        $results = strlen(trim($this->search)) >= 2
            ? Member::visibleTo($user)->active()->search($this->search)->with('latestMembership')->limit(8)->get()
            : collect();

        $checkedInIds = Attendance::whereDate('date', $today)->pluck('member_id')->all();

        $todays = Attendance::with('member')
            ->whereDate('date', $today)
            ->when(! $user->isGymAdmin(), fn ($q) => $q->whereHas('member', fn ($m) => $m->where('trainer_id', $user->id)))
            ->latest('check_in_at')
            ->get();

        return view('livewire.gym.attendance.index', compact('results', 'checkedInIds', 'todays'));
    }
}
