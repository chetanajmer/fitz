<?php

namespace App\Livewire\Portal;

use App\Livewire\Concerns\RecordsBodyProgress;
use App\Models\Member;
use App\Services\AiAccessService;
use App\Services\TransformationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** The signed-in member's transformation: photos, measurements, AI consent and AI reports. */
#[Layout('components.layouts.portal')]
#[Title('My progress')]
class Progress extends Component
{
    use RecordsBodyProgress;

    public function mount(): void
    {
        $this->resetForms();
    }

    public function setConsent(bool $accepted, AiAccessService $access): void
    {
        $access->setConsent($this->progressMember(), $accepted);
        $this->dispatch('notify', message: $accepted ? 'Thanks! AI coach is ready.' : 'Consent withdrawn.');
    }

    /** Always the signed-in member's own profile, never taken from the request. */
    protected function progressMember(): Member
    {
        $member = Auth::user()->memberProfile()->first();
        abort_unless($member, 403, 'No member profile is linked to this account.');

        return $member;
    }

    protected function authorizeProgress(): void {}

    protected function progressActor(): array
    {
        return ['app', null];
    }

    public function render(AiAccessService $access, TransformationService $transformation): View
    {
        $member = $this->progressMember();

        return view('livewire.portal.progress', [
            'status' => $access->status($member),
            'measurements' => $member->measurements()->get()->reverse(),
            'photos' => $member->progressPhotos()->get(),
            'transformation' => $transformation->summary($member),
            'photoUrl' => fn (int $id) => route('portal.progress-photo', $id),
            'reports' => $member->aiReports()->limit(20)->get(),
        ]);
    }
}
