<?php

namespace App\Livewire\Gym;

use App\Services\CheckInCodeService;
use App\Services\QrCodeService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Full-screen check-in QR for a tablet or PC at the desk; the code changes every 30 seconds. */
#[Layout('components.layouts.base', ['bodyClass' => 'bg-gym-black'])]
#[Title('Check-in screen')]
class CheckInScreen extends Component
{
    public function render(CheckInCodeService $codes, QrCodeService $qr): View
    {
        $gym = current_gym()->fresh();

        return view('livewire.gym.check-in-screen', [
            'gym' => $gym,
            'isActive' => $codes->mode($gym) === CheckInCodeService::SCREEN,
            'qr' => $qr->svg($codes->url($codes->screenCode($gym)), 600),
        ]);
    }
}
