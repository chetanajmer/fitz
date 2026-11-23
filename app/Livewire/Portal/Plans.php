<?php

namespace App\Livewire\Portal;

use App\Enums\FitnessPlanType;
use App\Livewire\Portal\Concerns\LogsProgramDays;
use App\Services\FitnessPlanService;
use App\Services\ProgramService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Member portal: "My diet charts" / "My workout plans". */
#[Layout('components.layouts.portal')]
class Plans extends Component
{
    use LogsProgramDays;

    #[Locked]
    public string $type;

    public function mount(string $type): void
    {
        abort_unless(in_array($type, FitnessPlanType::values(), true), 404);
        $this->type = $type;
    }

    public function render(FitnessPlanService $plans, ProgramService $programs): View
    {
        $member = Auth::user()->memberProfile()->firstOrFail();
        $type = FitnessPlanType::from($this->type);

        return view('livewire.portal.plans', ['planType' => $type, 'programs' => $programs->overview($member)] + $plans->libraryFor($member, $type))
            ->title($type->pluralLabel());
    }
}
