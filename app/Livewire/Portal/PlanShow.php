<?php

namespace App\Livewire\Portal;

use App\Services\FitnessPlanService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.portal')]
class PlanShow extends Component
{
    #[Locked]
    public int $planId;

    public function mount(int $plan): void
    {
        $this->planId = $plan;
    }

    public function render(FitnessPlanService $plans): View
    {
        $member = Auth::user()->memberProfile()->firstOrFail();
        // Only published defaults or this member's own customised plans — others 404.
        $plan = $plans->findForMember($member, $this->planId) ?? abort(404);
        $plan->setRelation('member', $member);

        return view('livewire.portal.plan-show', ['plan' => $plan])->title($plan->title);
    }
}
