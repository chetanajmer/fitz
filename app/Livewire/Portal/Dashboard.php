<?php

namespace App\Livewire\Portal;

use App\Livewire\Portal\Concerns\ChecksInSelf;
use App\Livewire\Portal\Concerns\LogsProgramDays;
use App\Models\Member;
use App\Models\ProgramDayLog;
use App\Services\AttendanceService;
use App\Services\ProgramService;
use App\Services\StreakService;
use App\Services\WeightTrackingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.portal')]
#[Title('Overview')]
class Dashboard extends Component
{
    use ChecksInSelf;
    use LogsProgramDays;

    protected function member(): Member
    {
        return $this->portalMember();
    }

    /**
     * Visits per week for the last 8 weeks (Monday-based), oldest first.
     *
     * @return list<array{label: string, visits: int}>
     */
    protected function weeklyVisits(Member $member): array
    {
        $from = Carbon::today()->startOfWeek()->subWeeks(7);
        $dates = $member->attendances()->reorder()->where('date', '>=', $from)->pluck('date');

        return collect(range(0, 7))->map(function (int $i) use ($from, $dates) {
            $start = $from->copy()->addWeeks($i);
            $end = $start->copy()->endOfWeek();

            return [
                'label' => $i === 7 ? 'This wk' : $start->format('d M'),
                'visits' => $dates->filter(fn ($d) => Carbon::parse($d)->betweenIncluded($start, $end))->count(),
            ];
        })->all();
    }

    /** The weight card saved a weigh-in or goal: refresh the Fitness progress tile. */
    #[On('weight-saved')]
    public function weightSaved(): void {}

    public function render(): View
    {
        $member = $this->member();
        $current = $member->currentMembership();
        $weight = app(WeightTrackingService::class)->summary($member, 2);

        return view('livewire.portal.dashboard', [
            'member' => $member,
            'current' => $current,
            'latest' => $member->latestMembership()->first(),
            'state' => $member->membershipState(),
            'pending' => $member->pendingAmount(),
            'todayVisit' => app(AttendanceService::class)->today($member),
            'selfCheckIn' => $member->gym->allowsSelfCheckIn(),
            'streak' => app(StreakService::class)->summary($member),
            'programs' => app(ProgramService::class)->overview($member),
            'visitsThisMonth' => $member->attendances()->reorder()->where('date', '>=', now()->startOfMonth())->count(),
            'totalVisits' => $member->attendances()->reorder()->count(),
            'workoutsDone' => ProgramDayLog::where('member_id', $member->id)->where('type', 'workout')->where('status', 'done')->count(),
            'weight' => $weight,
            'weeklyVisits' => $this->weeklyVisits($member),
            'recentPayments' => $member->payments()->limit(3)->get(),
        ]);
    }
}
