<?php

namespace App\Livewire\Gym;

use App\Services\CheckInCodeService;
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

    public function regenerateCheckinQr(GymProfileService $service): void
    {
        Gate::authorize('gym.settings');

        $service->regenerateCheckinToken(current_gym());
        $this->dispatch('notify', message: 'New check-in QR generated. Old printed check-in QRs no longer work.');
    }

    public function setCheckinMode(string $mode, GymProfileService $service): void
    {
        Gate::authorize('gym.settings');

        $service->setCheckinQrMode(current_gym(), $mode);
        $this->dispatch('notify', message: 'Members now scan the '.lcfirst(CheckInCodeService::MODES[$mode]).'.');
    }

    public function render(QrCodeService $qr, CheckInCodeService $codes): View
    {
        $gym = current_gym()->fresh();

        return view('livewire.gym.qr-codes', [
            'gym' => $gym,
            'registerQr' => $qr->svg($gym->registerUrl(), 600),
            'checkinMode' => $codes->mode($gym),
            'checkinQr' => $qr->svg($codes->url($codes->printedCode($gym)), 600),
        ]);
    }
}
