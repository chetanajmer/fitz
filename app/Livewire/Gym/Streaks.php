<?php

namespace App\Livewire\Gym;

use App\Models\Member;
use App\Models\MemberStreak;
use App\Services\StreakService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.gym')]
#[Title('Streaks')]
class Streaks extends Component
{
    /** current | longest */
    #[Url(except: 'current')]
    public string $board = 'current';

    public int $graceDays = 1;

    public bool $skipClosedDays = true;

    public string $milestones = '';

    public bool $selfCheckIn = true;

    public function mount(): void
    {
        $rules = current_gym()->streakRules();
        $this->graceDays = $rules['grace_days'];
        $this->skipClosedDays = $rules['skip_closed_days'];
        $this->milestones = implode(', ', $rules['milestones']);
        $this->selfCheckIn = current_gym()->allowsSelfCheckIn();
    }

    public function saveRules(StreakService $streaks): void
    {
        abort_unless(Auth::user()->can('gym.settings'), 403);

        $this->validate([
            'graceDays' => 'required|integer|min:0|max:7',
            'skipClosedDays' => 'boolean',
            'selfCheckIn' => 'boolean',
            'milestones' => ['required', 'string', 'max:200', 'regex:/^\s*\d+(\s*,\s*\d+)*\s*$/'],
        ], ['milestones.regex' => 'Enter numbers separated by commas, e.g. 7, 14, 30.']);

        $gym = current_gym();
        $gym->settings = array_merge($gym->settings ?? [], ['attendance' => ['member_self_checkin' => $this->selfCheckIn], 'streak' => [
            'grace_days' => $this->graceDays,
            'skip_closed_days' => $this->skipClosedDays,
            'milestones' => collect(explode(',', $this->milestones))->map(fn ($m) => (int) trim($m))->filter(fn ($m) => $m > 0)->unique()->sort()->values()->all(),
        ]]);
        $gym->save();

        // Rules changed: every member's streak must be recalculated.
        $streaks->forgetRules($gym->id);
        $count = $streaks->refreshGym($gym);
        $this->mount();

        $this->dispatch('notify', message: "Streak rules saved. {$count} member streaks recalculated.");
    }

    public function recalculate(StreakService $streaks): void
    {
        $count = $streaks->refreshGym(current_gym());
        $this->dispatch('notify', message: "{$count} member streaks recalculated from attendance.");
    }

    public function render(StreakService $streaks): View
    {
        $leaderboard = MemberStreak::with('member:id,name,member_code,photo_path,phone')
            ->whereHas('member', fn ($q) => $q->active())
            ->when($this->board === 'current', fn ($q) => $q->where('current_streak', '>', 0)->orderByDesc('current_streak')->orderByDesc('last_visit_on'))
            ->when($this->board === 'longest', fn ($q) => $q->where('longest_streak', '>', 0)->orderByDesc('longest_streak'))
            ->limit(25)
            ->get();

        // Members whose live streak breaks if they don't come in today.
        $atRisk = MemberStreak::with('member')
            ->where('current_streak', '>', 0)
            ->whereHas('member', fn ($q) => $q->active())
            ->orderByDesc('current_streak')
            ->limit(200)
            ->get()
            ->filter(fn (MemberStreak $s) => $s->member && $streaks->calculate($s->member)['at_risk'])
            ->take(20);

        return view('livewire.gym.streaks', [
            'leaderboard' => $leaderboard,
            'atRisk' => $atRisk,
            'stats' => [
                'active' => MemberStreak::where('current_streak', '>', 0)->count(),
                'average' => round((float) MemberStreak::where('current_streak', '>', 0)->avg('current_streak'), 1),
                'best' => (int) MemberStreak::max('longest_streak'),
                'members' => Member::active()->count(),
            ],
            'canEditRules' => Auth::user()->can('gym.settings'),
            'closedDays' => current_gym()->closedWeekdays(),
        ]);
    }
}
