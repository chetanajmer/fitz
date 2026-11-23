<?php

namespace App\Livewire\Gym;

use App\Services\GymProfileService;
use App\Services\QrCodeService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.gym')]
#[Title('QR Codes')]
class QrCodes extends Component
{
    /** Only staff who manage gym settings may invalidate the printed registration QR. */
    public function regenerateRegisterLink(GymProfileService $service): void
    {
        Gate::authorize('gym.settings');

        $service->regenerateRegisterToken(current_gym());
        $this->dispatch('notify', message: 'New registration QR generated. Old printed QR codes no longer work.');
    }

    public function render(QrCodeService $qr): View
    {
        $gym = current_gym()->fresh();

        return view('livewire.gym.qr-codes', [
            'gym' => $gym,
            'registerQr' => $qr->svg($gym->registerUrl(), 600),
        ]);
    }
}
