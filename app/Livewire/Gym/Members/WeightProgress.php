<?php

namespace App\Livewire\Gym\Members;

use App\Models\Member;
use App\Services\WeightTrackingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** "Progress" tab on a member's page: weigh-ins and the weight goal, recorded by staff. */
class WeightProgress extends Component
{
    #[Locked]
    public Member $member;

    public string $weight = '';

    public string $measuredOn = '';

    public string $note = '';

    public string $target = '';

    public string $targetDate = '';

    public function mount(Member $member): void
    {
        Gate::authorize('trackProgress', $member);
        $this->member = $member;
        $this->measuredOn = Carbon::today()->toDateString();
    }

    public function record(WeightTrackingService $weights): void
    {
        Gate::authorize('trackProgress', $this->member);
        $this->validate([
            'weight' => 'required|numeric|between:'.WeightTrackingService::MIN_KG.','.WeightTrackingService::MAX_KG,
            'measuredOn' => 'required|date|before_or_equal:today',
            'note' => 'nullable|string|max:255',
        ], [], ['measuredOn' => 'date']);

        $result = $weights->record($this->member, (float) $this->weight, $this->measuredOn, 'staff', Auth::user(), $this->note ?: null);
        $this->reset('weight', 'note');
        $this->measuredOn = Carbon::today()->toDateString();

        $this->dispatch('notify', message: $result['goal_achieved'] ? "{$this->member->name} reached their target weight!" : 'Weight saved.');
    }

    public function setGoal(WeightTrackingService $weights): void
    {
        Gate::authorize('trackProgress', $this->member);
        $this->validate([
            'target' => 'required|numeric|between:'.WeightTrackingService::MIN_KG.','.WeightTrackingService::MAX_KG,
            'targetDate' => 'nullable|date|after:today',
            'weight' => 'nullable|numeric|between:'.WeightTrackingService::MIN_KG.','.WeightTrackingService::MAX_KG,
        ], [], ['targetDate' => 'target date', 'weight' => 'current weight']);

        $weights->setGoal($this->member, (float) $this->target, $this->targetDate ?: null, $this->weight !== '' ? (float) $this->weight : null, 'staff', Auth::user());
        $this->reset('target', 'targetDate', 'weight');
        $this->dispatch('notify', message: 'Target set. The member sees it in their app.');
    }

    public function deleteLog(int $logId, WeightTrackingService $weights): void
    {
        Gate::authorize('trackProgress', $this->member);
        $weights->deleteLog($this->member, $logId);
        $this->dispatch('notify', message: 'Reading deleted.');
    }

    public function render(WeightTrackingService $weights): View
    {
        return view('livewire.gym.members.weight-progress', [
            'summary' => $weights->summary($this->member, 120),
        ]);
    }
}
