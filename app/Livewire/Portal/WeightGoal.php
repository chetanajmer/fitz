<?php

namespace App\Livewire\Portal;

use App\Models\Member;
use App\Services\WeightTrackingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/** Member dashboard card: the weight goal (lose / gain), its chart and pace, with weigh-in and goal forms. */
class WeightGoal extends Component
{
    /** null | weight | goal: which form is open. */
    public ?string $form = null;

    public string $weight = '';

    public string $measuredOn = '';

    public string $target = '';

    public string $targetDate = '';

    public function mount(): void
    {
        $this->measuredOn = Carbon::today()->toDateString();
    }

    /** Always the signed-in member's own profile. */
    protected function member(): Member
    {
        $member = Auth::user()->memberProfile()->with('gym')->first();
        abort_unless($member, 403, 'No member profile is linked to this account.');

        return $member;
    }

    public function open(?string $form): void
    {
        $this->form = in_array($form, ['weight', 'goal'], true) ? $form : null;
        $this->resetValidation();
    }

    public function record(WeightTrackingService $weights): void
    {
        $this->validate([
            'weight' => 'required|numeric|between:'.WeightTrackingService::MIN_KG.','.WeightTrackingService::MAX_KG,
            'measuredOn' => 'required|date|before_or_equal:today|after:2000-01-01',
        ], [], ['measuredOn' => 'date']);

        $result = $this->onForm(fn () => $weights->record($this->member(), (float) $this->weight, $this->measuredOn, 'app'));
        $this->reset('weight', 'form');
        $this->measuredOn = Carbon::today()->toDateString();
        $this->dispatch('weight-saved');
        $this->dispatch('notify', message: $result['goal_achieved'] ? 'You reached your target weight. Well done!' : 'Weight saved.');
    }

    public function setGoal(WeightTrackingService $weights): void
    {
        $range = 'between:'.WeightTrackingService::MIN_KG.','.WeightTrackingService::MAX_KG;
        $this->validate([
            'target' => ['required', 'numeric', $range],
            'targetDate' => ['nullable', 'date', 'after:today'],
            'weight' => ['nullable', 'numeric', $range],
        ], [], ['target' => 'target weight', 'targetDate' => 'target date', 'weight' => 'current weight']);

        $this->onForm(fn () => $weights->setGoal($this->member(), (float) $this->target, $this->targetDate ?: null, $this->weight !== '' ? (float) $this->weight : null));
        $this->reset('target', 'targetDate', 'weight', 'form');
        $this->dispatch('weight-saved');
        $this->dispatch('notify', message: 'Goal set.');
    }

    /** Runs a WeightTrackingService call and shows its errors on this form's fields. */
    protected function onForm(callable $call): mixed
    {
        try {
            return $call();
        } catch (ValidationException $e) {
            $fields = ['weight_kg' => 'weight', 'current_weight_kg' => 'weight', 'measured_on' => 'measuredOn', 'target_weight_kg' => 'target', 'target_date' => 'targetDate'];

            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($messages, $key) => [$fields[$key] ?? $key => $messages])->all());
        }
    }

    /** The member has read the "target achieved" or "almost there" message. */
    public function dismiss(string $type, WeightTrackingService $weights): void
    {
        $weights->acknowledge($this->member(), $type);
    }

    /**
     * With a target date: on track when the progress made is close to the share of time used. Null without a date.
     *
     * @param  array<string, mixed>  $goal
     * @return array{on_track: bool, overdue: bool, kg_per_week: float}|null
     */
    protected function pace(array $goal): ?array
    {
        if ($goal['status'] !== 'active' || ! $goal['target_date']) {
            return null;
        }

        $start = Carbon::parse($goal['start_date']);
        $end = Carbon::parse($goal['target_date']);
        $totalDays = max(1, $start->diffInDays($end));
        $timeUsed = min(100, max(0, $start->diffInDays(Carbon::today()) / $totalDays * 100));
        $weeksLeft = max(1, $goal['days_to_target_date'] / 7);

        return [
            'on_track' => $goal['progress_percent'] >= $timeUsed - 10,
            'overdue' => $goal['days_to_target_date'] < 0,
            'kg_per_week' => round($goal['remaining_kg'] / $weeksLeft, 1),
        ];
    }

    public function render(WeightTrackingService $weights): View
    {
        $summary = $weights->summary($this->member(), 60);

        return view('livewire.portal.weight-goal', [
            'summary' => $summary,
            'goal' => $summary['goal'],
            'pace' => $summary['goal'] ? $this->pace($summary['goal']) : null,
        ]);
    }
}
