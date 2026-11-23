<?php

namespace App\Livewire\Gym\Memberships;

use App\Models\Membership;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.gym')]
#[Title('Memberships')]
class Index extends Component
{
    use WithPagination;

    /** active | expiring | expired | upcoming | dues | all */
    #[Url]
    public string $filter = 'active';

    #[Url(except: '')]
    public string $search = '';

    public function updated(string $property): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $today = Carbon::today();

        $base = Membership::query()->with('member')
            ->when($this->search !== '', fn ($q) => $q->whereHas('member', fn ($m) => $m->search($this->search)));

        $query = match ($this->filter) {
            'active' => (clone $base)->valid()->orderBy('end_date'),
            'expiring' => (clone $base)->where('status', '!=', 'cancelled')->whereBetween('end_date', [$today->toDateString(), $today->copy()->addDays(15)->toDateString()])->orderBy('end_date'),
            // Only each member's latest lapsed membership, and only if they have not renewed.
            'expired' => (clone $base)->where('status', '!=', 'cancelled')->whereDate('end_date', '<', $today)
                ->whereNotExists(fn ($q) => $q->from('memberships as newer')->whereColumn('newer.member_id', 'memberships.member_id')
                    ->where('newer.status', '!=', 'cancelled')->whereColumn('newer.end_date', '>', 'memberships.end_date'))
                ->orderByDesc('end_date'),
            'upcoming' => (clone $base)->where('status', '!=', 'cancelled')->whereDate('start_date', '>', $today)->orderBy('start_date'),
            'dues' => (clone $base)->withBalance()->orderByDesc(DB::raw('total_amount - amount_paid')),
            default => (clone $base)->latest('start_date'),
        };

        return view('livewire.gym.memberships.index', [
            'memberships' => $query->paginate(20),
            'totalDue' => (float) Membership::withBalance()->sum(DB::raw('total_amount - amount_paid')),
        ]);
    }
}
