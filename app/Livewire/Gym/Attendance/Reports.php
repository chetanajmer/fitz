<?php

namespace App\Livewire\Gym\Attendance;

use App\Models\Attendance;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.gym')]
#[Title('Attendance reports')]
class Reports extends Component
{
    use WithPagination;

    /** daily | monthly */
    #[Url]
    public string $view = 'daily';

    #[Url]
    public string $date = '';

    #[Url]
    public string $month = '';

    public function mount(): void
    {
        $this->date = $this->date ?: Carbon::today()->toDateString();
        $this->month = $this->month ?: Carbon::today()->format('Y-m');
    }

    public function updated(): void
    {
        $this->validate(['date' => 'required|date', 'month' => 'required|date_format:Y-m', 'view' => 'in:daily,monthly']);
        $this->resetPage();
    }

    public function render(): View
    {
        if ($this->view === 'monthly') {
            $start = Carbon::createFromFormat('Y-m-d', $this->month.'-01')->startOfMonth();
            $end = $start->copy()->endOfMonth();

            $perDay = Attendance::whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->selectRaw('DATE(date) as day, COUNT(*) as total')->groupBy('day')->pluck('total', 'day');

            $labels = $values = [];
            for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                $labels[] = $d->format('d');
                $values[] = (int) ($perDay[$d->toDateString()] ?? 0);
            }

            $topMembers = Attendance::with('member')
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->selectRaw('member_id, COUNT(*) as visits')->groupBy('member_id')->orderByDesc('visits')->paginate(20);

            return view('livewire.gym.attendance.reports', [
                'labels' => $labels, 'values' => $values, 'topMembers' => $topMembers,
                'total' => array_sum($values), 'unique' => $topMembers->total(),
                'avg' => round(array_sum($values) / max(1, $start->daysInMonth), 1),
                'busiest' => $perDay->isNotEmpty() ? Carbon::parse($perDay->sortDesc()->keys()->first())->format('d M').' ('.$perDay->max().')' : '—',
            ]);
        }

        $records = Attendance::with('member')->whereDate('date', $this->date)->orderBy('check_in_at')->paginate(30);
        $all = Attendance::whereDate('date', $this->date)->get(['check_in_at', 'check_out_at']);
        $hours = array_fill(5, 19, 0);
        foreach ($all as $a) {
            $h = (int) $a->check_in_at->format('G');
            if (isset($hours[$h])) {
                $hours[$h]++;
            }
        }
        $completed = $all->whereNotNull('check_out_at');
        $avgMinutes = $completed->isNotEmpty() ? (int) round($completed->avg(fn ($a) => $a->check_in_at->diffInMinutes($a->check_out_at))) : null;

        return view('livewire.gym.attendance.reports', [
            'records' => $records,
            'total' => $all->count(),
            'checkedOut' => $completed->count(),
            'avgDuration' => $avgMinutes !== null ? intdiv($avgMinutes, 60).'h '.($avgMinutes % 60).'m' : '—',
            'labels' => array_map(fn ($h) => Carbon::createFromTime($h)->format('ga'), array_keys($hours)),
            'values' => array_values($hours),
        ]);
    }
}
