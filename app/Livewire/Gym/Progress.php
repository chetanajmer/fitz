<?php

namespace App\Livewire\Gym;

use App\Models\Member;
use App\Models\WeightGoal;
use App\Models\WeightLog;
use App\Services\WeightTrackingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Gym-wide weight tracking: who is due for a weigh-in, who is close to their target, who achieved it. */
#[Layout('components.layouts.gym')]
#[Title('Weight tracking')]
class Progress extends Component
{
    use WithPagination;

    /** due | near | achieved */
    #[Url(except: 'due')]
    public string $view = 'due';

    public int $everyDays = 15;

    public function mount(): void
    {
        $this->everyDays = current_gym()->weighInEveryDays();
    }

    public function updatedView(): void
    {
        $this->resetPage();
    }

    public function saveInterval(): void
    {
        abort_unless(Auth::user()->can('gym.settings'), 403);
        $this->validate(['everyDays' => 'required|in:7,15,30']);

        $gym = current_gym();
        $gym->settings = array_merge($gym->settings ?? [], ['progress' => ['weigh_in_every_days' => (int) $this->everyDays]]);
        $gym->save();

        $this->dispatch('notify', message: "Members will be asked for their weight every {$this->everyDays} days.");
    }

    public function render(WeightTrackingService $weights): View
    {
        $user = Auth::user();
        $gym = current_gym();
        $every = $gym->weighInEveryDays();
        $visibleIds = fn () => Member::visibleTo($user)->select('id');

        $due = $this->view === 'due'
            ? $weights->dueQuery($every)->visibleTo($user)->orderByRaw('last_weighed_on is not null')->orderBy('last_weighed_on')->orderBy('name')->paginate(20)
            : null;

        $goals = $this->view === 'achieved'
            ? WeightGoal::with('member')->where('status', WeightGoal::ACHIEVED)->whereIn('member_id', $visibleIds())->latest('achieved_at')->paginate(20)
            : null;

        $near = $this->view === 'near'
            ? WeightGoal::with('member')->where('status', WeightGoal::ACTIVE)->whereIn('member_id', $visibleIds())->get()
                ->map(fn (WeightGoal $g) => ['goal' => $g, 'progress' => $g->member ? $weights->summary($g->member, 1)['goal'] : null])
                ->filter(fn ($row) => $row['progress'])
                ->sortByDesc(fn ($row) => $row['progress']['progress_percent'])
                ->values()
            : collect();

        return view('livewire.gym.progress', [
            'due' => $due,
            'goals' => $goals,
            'near' => $near,
            'stats' => [
                'tracking' => WeightLog::whereIn('member_id', $visibleIds())->where('measured_on', '>=', Carbon::today()->subDays($every))->distinct()->count('member_id'),
                'due' => $weights->dueQuery($every)->visibleTo($user)->count(),
                'active_goals' => WeightGoal::where('status', WeightGoal::ACTIVE)->whereIn('member_id', $visibleIds())->count(),
                'achieved_30' => WeightGoal::where('status', WeightGoal::ACHIEVED)->whereIn('member_id', $visibleIds())->where('achieved_at', '>=', now()->subDays(30))->count(),
            ],
            'every' => $every,
            'canEditSettings' => $user->can('gym.settings'),
        ]);
    }
}
