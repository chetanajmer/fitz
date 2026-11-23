<?php

namespace App\Livewire\Gym;

use App\Services\QrCodeService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.gym')]
#[Title('QR Codes')]
class QrCodes extends Component
{
    public function render(QrCodeService $qr): View
    {
        $gym = current_gym();

        return view('livewire.gym.qr-codes', [
            'gym' => $gym,
            'registerQr' => $qr->svg($gym->registerUrl(), 600),
        ]);
    }
}
