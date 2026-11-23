<?php

namespace App\Livewire\Platform;

use App\Models\Gym;
use App\Models\PricingRate;
use App\Models\SaasInvoice;
use App\Services\DashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.platform')]
#[Title('Platform overview')]
class Dashboard extends Component
{
    public function render(DashboardService $dashboard): View
    {
        $today = Carbon::today()->toDateString();

        $topGyms = Gym::withCount([
            'members as active_members_count' => fn ($q) => $q->whereNull('deleted_at')->where('status', 'active')
                ->whereHas('memberships', fn ($m) => $m->withoutGlobalScopes()->valid($today)),
        ])->orderByDesc('active_members_count')->limit(8)->get();

        return view('livewire.platform.dashboard', [
            'stats' => $dashboard->platformStats(),
            'revenue' => $dashboard->platformRevenueSeries(),
            'signups' => $dashboard->gymSignupSeries(),
            'topGyms' => $topGyms,
            'recentGyms' => Gym::with('owner')->latest()->limit(6)->get(),
            'overdue' => SaasInvoice::with('gym')->outstanding()->whereDate('due_on', '<', $today)->orderBy('due_on')->limit(6)->get(),
            'rate' => PricingRate::rateOn(),
        ]);
    }
}
