<?php

namespace App\Livewire\Gym;

use App\Models\Attendance;
use App\Models\Member;
use App\Models\Membership;
use App\Services\DashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.gym')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    public function render(DashboardService $dashboard): View
    {
        $user = Auth::user();

        if (! $user->isGymAdmin()) {
            return $this->trainerView($dashboard);
        }

        return view('livewire.gym.dashboard', [
            'stats' => $dashboard->gymStats(),
            'expiries' => $dashboard->upcomingExpiries(),
            'payments' => $dashboard->recentPayments(),
            'enquiries' => $dashboard->recentEnquiries(),
            'growth' => $dashboard->memberGrowth(),
            'revenue' => $dashboard->revenueSeries(),
            'gym' => current_gym(),
        ]);
    }

    protected function trainerView(DashboardService $dashboard): View
    {
        $trainer = Auth::user();
        $memberIds = Member::where('trainer_id', $trainer->id)->pluck('id');

        return view('livewire.gym.trainer-dashboard', [
            'stats' => $dashboard->trainerStats($trainer),
            'members' => Member::where('trainer_id', $trainer->id)->with('latestMembership')->orderBy('name')->limit(12)->get(),
            'todayAttendance' => Attendance::with('member')->whereIn('member_id', $memberIds)->whereDate('date', Carbon::today())->latest('check_in_at')->get(),
            'expiring' => Membership::with('member')->whereIn('member_id', $memberIds)->where('status', '!=', 'cancelled')
                ->whereBetween('end_date', [Carbon::today(), Carbon::today()->addDays(7)])->orderBy('end_date')->get(),
        ]);
    }
}
