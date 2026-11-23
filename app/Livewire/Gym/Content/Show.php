<?php

namespace App\Livewire\Gym\Content;

use App\Models\FitnessPlan;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Staff preview of a plan, exactly as members see it. */
#[Layout('components.layouts.gym')]
class Show extends Component
{
    #[Locked]
    public FitnessPlan $plan;

    public function mount(FitnessPlan $plan): void
    {
        Gate::authorize('view', $plan);
        $this->plan = $plan;
    }

    public function render(): View
    {
        $plan = $this->plan->fresh(['sections', 'category', 'member', 'creator']);

        return view('livewire.gym.content.show', ['p' => $plan])->title($plan->title);
    }
}
