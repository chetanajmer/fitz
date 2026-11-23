<?php

namespace App\Livewire\Gym\Members;

use App\Enums\FitnessPlanType;
use App\Enums\ProgramGoal;
use App\Models\FitnessPlan;
use App\Models\FitnessProgram;
use App\Models\Member;
use App\Models\MemberProgram;
use App\Services\ProgramService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Programs section on a member's "Diet & Workout" tab: assign a workout / diet program and see the schedule. */
class MemberPrograms extends Component
{
    #[Locked]
    public Member $member;

    public bool $showAssign = false;

    public string $assignType = 'workout';

    public string $goal = '';

    public string $preference = '';

    public string $programId = '';

    public string $startDate = '';

    public string $notes = '';

    public function mount(Member $member): void
    {
        Gate::authorize('view', $member);
        $this->member = $member;
    }

    protected function authorizeAssign(): void
    {
        Gate::authorize('create', [FitnessPlan::class, $this->member]);
    }

    public function openAssign(string $type): void
    {
        $this->authorizeAssign();
        abort_unless(in_array($type, ['workout', 'diet'], true), 404);
        $this->resetErrorBag();
        $this->assignType = $type;
        $this->goal = $this->guessGoal($type);
        $this->preference = $type === 'diet' ? (string) $this->member->diet_preference?->value : '';
        $this->programId = '';
        $this->startDate = Carbon::today()->toDateString();
        $this->notes = '';
        $this->showAssign = true;
    }

    public function updatedGoal(): void
    {
        $this->programId = '';
    }

    public function updatedPreference(): void
    {
        $this->programId = '';
    }

    public function assign(ProgramService $programs): void
    {
        $this->authorizeAssign();
        $this->validate([
            'programId' => 'required|integer',
            'startDate' => 'required|date|after_or_equal:'.Carbon::today()->subDays(7)->toDateString(),
            'notes' => 'nullable|string|max:500',
        ], ['programId.required' => 'Choose a program.'], ['startDate' => 'start date']);

        $program = FitnessProgram::ofType($this->assignType)->active()->findOrFail((int) $this->programId);
        $programs->assign($this->member, $program, $this->startDate, Auth::user(), $this->notes ?: null);

        $this->showAssign = false;
        $this->dispatch('notify', message: "{$program->title} assigned to {$this->member->name}.");
    }

    public function stop(int $assignmentId, ProgramService $programs): void
    {
        $this->authorizeAssign();
        $programs->stop(MemberProgram::where('member_id', $this->member->id)->findOrFail($assignmentId));
        $this->dispatch('notify', message: 'Program stopped.');
    }

    /** Pre-select a goal from the member's free-text fitness goal. */
    protected function guessGoal(string $type): string
    {
        $text = strtolower((string) $this->member->fitness_goal);
        $guess = match (true) {
            str_contains($text, 'loss') || str_contains($text, 'fat') || str_contains($text, 'slim') => ProgramGoal::WeightLoss,
            str_contains($text, 'gain') || str_contains($text, 'muscle') || str_contains($text, 'bulk') => ProgramGoal::MuscleGain,
            str_contains($text, 'lean') || str_contains($text, 'tone') || str_contains($text, 'toning') => ProgramGoal::Lean,
            str_contains($text, 'strength') => ProgramGoal::Strength,
            default => null,
        };

        return $guess && in_array($guess, ProgramGoal::forType(FitnessPlanType::from($type)), true) ? $guess->value : '';
    }

    public function render(ProgramService $programs): View
    {
        $today = Carbon::today();
        $current = MemberProgram::with('program.days.plan')->where('member_id', $this->member->id)->current()->orderBy('start_date')->get();
        $type = FitnessPlanType::from($this->assignType);

        return view('livewire.gym.members.member-programs', [
            'current' => collect(['workout', 'diet'])->mapWithKeys(fn ($t) => [$t => $current->first(fn ($a) => $a->type->value === $t)]),
            'summaries' => $current->mapWithKeys(fn ($a) => [$a->id => $programs->assignmentSummary($a)]),
            'today' => $programs->scheduleOn($this->member, $today, false),
            'week' => $programs->week($this->member, $today),
            'history' => MemberProgram::with('program:id,title', 'assigner:id,name')->where('member_id', $this->member->id)
                ->whereNotIn('id', $current->pluck('id'))->latest('start_date')->limit(10)->get(),
            'canAssign' => Auth::user()->can('create', [FitnessPlan::class, $this->member]),
            'options' => $this->showAssign ? FitnessProgram::ofType($type)->active()
                ->when($this->goal !== '', fn ($q) => $q->where('goal', $this->goal))
                ->when($this->preference !== '' && $type === FitnessPlanType::Diet, fn ($q) => $q->where('diet_preference', $this->preference))
                ->orderBy('title')->get() : collect(),
            'goals' => ProgramGoal::optionsFor($type),
            'hasPrograms' => FitnessProgram::exists(),
        ]);
    }
}
