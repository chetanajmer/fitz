<?php

namespace App\Livewire\Portal;

use App\Livewire\Portal\Concerns\ChecksInSelf;
use App\Services\AttendanceService;
use App\Services\StreakService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Member portal: the member's own check-in QR (served by portal.qr), self check-in and attendance history. */
#[Layout('components.layouts.portal')]
#[Title('QR check-in')]
class CheckIn extends Component
{
    use ChecksInSelf;

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
