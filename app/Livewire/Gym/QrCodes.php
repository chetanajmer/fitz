<?php

namespace App\Livewire\Gym;

use App\Services\GymProfileService;
use App\Services\QrCodeService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The gym's one desk QR (/register/{token}). New visitors scan it to register; registered members scan it
 * to check in, and scan it again to check out.
 */
#[Layout('components.layouts.gym')]
#[Title('QR Code')]
class QrCodes extends Component
{
    /** Only staff who manage gym settings may invalidate the printed QR. */
    public function regenerateRegisterLink(GymProfileService $service): void
    {
        Gate::authorize('gym.settings');

        $service->regenerateRegisterToken(current_gym());
        $this->dispatch('notify', message: 'New gym QR generated. Old printed QR codes no longer work.');
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
