<?php

namespace App\Livewire\Portal;

use App\Livewire\Portal\Concerns\ChecksInSelf;
use App\Services\AttendanceService;
use App\Services\StreakService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Member portal: the member's own check-in QR (served by portal.qr), self check-in, desk QR scanner and attendance history. */
#[Layout('components.layouts.portal')]
#[Title('QR check-in')]
class CheckIn extends Component
{
    use ChecksInSelf;

    /** Desk QR token handed over when the member scanned the printed QR with their phone camera. */
    #[Locked]
    public ?string $pendingDeskToken = null;

    public function mount(): void
    {
        $token = request()->query('desk');
        $this->pendingDeskToken = is_string($token) && preg_match('/^[A-Za-z0-9]{20,64}$/', $token) ? $token : null;
    }

    /**
     * Called with whatever the in-page camera scanner read, or with the token from a phone-camera scan.
     * Accepts the gym's desk QR link (/register/{token}) or the bare token, and toggles check-in / check-out.
     */
    public function scanDeskQr(string $scanned, AttendanceService $attendance): void
    {
        $this->pendingDeskToken = null;
        $this->resetErrorBag();

        $token = $this->deskTokenFrom($scanned);

        if ($token === null) {
            throw ValidationException::withMessages(['member' => 'This is not your gym\'s check-in QR. Scan the QR code at the front desk.']);
        }

        $result = $attendance->handleDeskQrScan($this->portalMember(), $token);
        $record = $result['attendance'];

        $this->dispatch('notify', message: $result['action'] === 'checked_in'
            ? 'Checked in at '.$record->check_in_at->format('h:i A').'. Have a great workout!'
            : 'Checked out at '.$record->check_out_at->format('h:i A').' ('.$record->durationLabel().').');
    }

    /** Pulls the register token out of a scanned desk QR link, or accepts a bare token. */
    protected function deskTokenFrom(string $scanned): ?string
    {
        $scanned = trim($scanned);

        if (preg_match('#/register/([A-Za-z0-9]{20,64})(?:[/?\#]|$)#', $scanned, $matches)) {
            return $matches[1];
        }

        return preg_match('/^[A-Za-z0-9]{20,64}$/', $scanned) ? $scanned : null;
    }

    public function render(): View
    {
        $member = $this->portalMember();
        $monthStart = Carbon::today()->startOfMonth();
        $visitedThisMonth = $member->attendances()->reorder()->where('date', '>=', $monthStart)->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())->unique()->values()->all();

        return view('livewire.portal.check-in', [
            'member' => $member,
            'todayVisit' => app(AttendanceService::class)->today($member),
            'selfCheckIn' => $member->gym->allowsSelfCheckIn(),
            'streak' => app(StreakService::class)->summary($member),
            'attendance' => $member->attendances()->limit(30)->get(),
            'monthStart' => $monthStart,
            'visitedThisMonth' => $visitedThisMonth,
            'totalVisits' => $member->attendances()->reorder()->count(),
        ]);
    }
}
