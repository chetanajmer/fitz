<?php

namespace App\Livewire\Platform;

use App\Models\PricingRate;
use App\Services\BillingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.platform')]
#[Title('Pricing')]
class Pricing extends Component
{
    public string $price = '';

    public string $effectiveFrom = '';

    public string $notes = '';

    public function mount(): void
    {
        $this->price = (string) PricingRate::rateOn();
        $this->effectiveFrom = Carbon::today()->addMonthNoOverflow()->startOfMonth()->toDateString();
    }

    public function save(BillingService $billing): void
    {
        $this->validate([
            'price' => 'required|numeric|min:0|max:10000',
            'effectiveFrom' => 'required|date|after_or_equal:'.Carbon::today()->startOfMonth()->toDateString(),
            'notes' => 'nullable|string|max:255',
        ], ['effectiveFrom.after_or_equal' => 'Price changes cannot be backdated before the current month — past invoices must stay unchanged.']);

        $billing->setRate((float) $this->price, $this->effectiveFrom, $this->notes ?: null);
        $this->reset('notes');
        $this->dispatch('notify', message: 'New price scheduled.');
    }

    public function render(): View
    {
        return view('livewire.platform.pricing', [
            'current' => PricingRate::rateOn(),
            'default' => (float) config('gymai.default_price_per_member'),
            'rates' => PricingRate::with('creator')->orderByDesc('effective_from')->orderByDesc('id')->get(),
        ]);
    }
}
