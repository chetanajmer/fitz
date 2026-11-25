<?php

namespace App\Livewire\Gym\Members;

use App\Livewire\Concerns\RecordsBodyProgress;
use App\Models\Member;
use App\Services\AiAccessService;
use App\Services\TransformationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** "AI Progress" tab on a member's page: measurements, progress photos and AI reports. */
class AiProgress extends Component
{
    use RecordsBodyProgress;

    #[Locked]
    public Member $member;

    public function mount(Member $member): void
    {
        Gate::authorize('useAi', $member);
        $this->member = $member;
        $this->resetForms();
    }

    /** Hand-pick the member for AI coach (gym eligibility "selected"). */
    public function toggleAccess(): void
    {
        Gate::authorize('useAi', $this->member);
        abort_unless(Auth::user()->can('ai.manage'), 403);
        $this->member->forceFill(['ai_access' => ! $this->member->ai_access])->save();
    }

    protected function progressMember(): Member
    {
        return $this->member;
    }

    protected function authorizeProgress(): void
    {
        Gate::authorize('useAi', $this->member);
    }

    protected function progressActor(): array
    {
        return ['staff', Auth::user()];
    }

    public function render(AiAccessService $access, TransformationService $transformation): View
    {
        return view('livewire.gym.members.ai-progress', [
            'status' => $access->status($this->member),
            'measurements' => $this->member->measurements()->get()->reverse(),
            'photos' => $this->member->progressPhotos()->get(),
            'transformation' => $transformation->summary($this->member),
            'photoUrl' => fn (int $id) => route('gym.members.progress-photo', [$this->member, $id]),
            'reports' => $this->member->aiReports()->limit(20)->get(),
        ]);
    }
}
