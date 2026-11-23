<?php

namespace App\Livewire\Portal;

use App\Enums\FitnessPlanType;
use App\Services\FitnessPlanService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Member portal: "My diet charts" / "My workout plans". */
#[Layout('components.layouts.portal')]
class Plans extends Component
{
    #[Locked]
    public string $type;

    public function mount(string $type): void
    {
        abort_unless(in_array($type, FitnessPlanType::values(), true), 404);
        $this->type = $type;
    }

    public function render(FitnessPlanService $plans): View
    {
        $member = Auth::user()->memberProfile()->firstOrFail();
        $type = FitnessPlanType::from($this->type);

        return view('livewire.portal.plans', ['planType' => $type] + $plans->libraryFor($member, $type))
            ->title($type->pluralLabel());
    }
}
