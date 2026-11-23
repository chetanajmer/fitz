<?php

namespace App\Livewire\Gym\Trainers;

use App\Models\Member;
use App\Models\User;
use App\Services\TrainerService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.gym')]
class Show extends Component
{
    #[Locked]
    public int $trainerId;

    public string $search = '';

    /** @var list<int> */
    public array $selected = [];

    public function mount(int $trainer): void
    {
        // Resolve within the signed-in admin's gym only.
        $this->trainerId = User::trainersOf(Auth::user()->gym_id)->findOrFail($trainer)->id;
    }

    protected function trainer(): User
    {
        return User::trainersOf(Auth::user()->gym_id)->findOrFail($this->trainerId);
    }

    public function assignSelected(TrainerService $service): void
    {
        $count = $service->assignMembers($this->trainer(), array_map('intval', $this->selected));
        $this->reset('selected', 'search');
        $this->dispatch('notify', message: "{$count} member(s) assigned.");
    }

    public function unassign(int $memberId, TrainerService $service): void
    {
        $service->unassignMember($this->trainer(), Member::findOrFail($memberId));
        $this->dispatch('notify', message: 'Member unassigned.');
    }

    public function render(): View
    {
        $trainer = $this->trainer();

        return view('livewire.gym.trainers.show', [
            'trainer' => $trainer,
            'members' => Member::where('trainer_id', $trainer->id)->with('latestMembership')->orderBy('name')->get(),
            'candidates' => Member::active()->where(fn ($q) => $q->whereNull('trainer_id')->orWhere('trainer_id', '!=', $trainer->id))
                ->search($this->search)->with('trainer')->orderBy('name')->limit(25)->get(),
        ])->title($trainer->name);
    }
}
