<?php

namespace App\Livewire\Gym\Members;

use App\Models\Member;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.gym')]
#[Title('Members')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $membership = '';

    #[Url(except: '')]
    public string $trainer = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'membership', 'trainer'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status', 'membership', 'trainer');
        $this->resetPage();
    }

    public function render(): View
    {
        $user = Auth::user();
        $today = Carbon::today()->toDateString();

        $members = Member::query()
            ->visibleTo($user)
            ->with(['latestMembership', 'trainer'])
            ->search($this->search)
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->membership === 'active', fn ($q) => $q->withValidMembership())
            ->when($this->membership === 'expired', fn ($q) => $q->expired())
            ->when($this->membership === 'upcoming', fn ($q) => $q->whereHas('memberships', fn ($m) => $m->where('status', '!=', 'cancelled')->whereDate('start_date', '>', $today))->whereDoesntHave('memberships', fn ($m) => $m->valid()))
            ->when($this->membership === 'none', fn ($q) => $q->whereDoesntHave('memberships', fn ($m) => $m->where('status', '!=', 'cancelled')))
            ->when($this->trainer !== '' && $user->isGymAdmin(), fn ($q) => $this->trainer === 'none' ? $q->whereNull('trainer_id') : $q->where('trainer_id', (int) $this->trainer))
            ->latest('id')
            ->paginate(15);

        return view('livewire.gym.members.index', [
            'members' => $members,
            'trainers' => $user->isGymAdmin() ? User::trainersOf($user->gym_id)->orderBy('name')->get(['id', 'name']) : collect(),
            'canManage' => $user->can('members.manage'),
        ]);
    }
}
