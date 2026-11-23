<?php

namespace App\Livewire\Gym\Billing;

use App\Models\SaasInvoice;
use App\Services\BillingService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.gym')]
#[Title('Gymai billing')]
class Index extends Component
{
    use WithPagination;

    public function render(BillingService $billing): View
    {
        $gym = current_gym();

        return view('livewire.gym.billing.index', [
            'estimate' => $billing->currentMonthEstimate($gym),
            'outstanding' => $billing->outstandingFor($gym),
            'invoices' => SaasInvoice::where('gym_id', $gym->id)->with('payments')->latest('period_start')->paginate(12),
        ]);
    }
}
