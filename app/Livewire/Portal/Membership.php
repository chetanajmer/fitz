<?php

namespace App\Livewire\Portal;

use App\Livewire\Portal\Concerns\ChecksInSelf;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Member portal: current plan, renewal, membership history and payments with receipts. */
#[Layout('components.layouts.portal')]
#[Title('My membership')]
class Membership extends Component
{
    use ChecksInSelf;

    public function render(): View
    {
        $member = $this->portalMember();
        $memberships = $member->memberships()->where('status', '!=', 'cancelled')->get();
        $payments = $member->payments()->get();

        return view('livewire.portal.membership', [
            'member' => $member,
            'current' => $member->currentMembership(),
            'latest' => $member->latestMembership()->first(),
            'state' => $member->membershipState(),
            'pending' => $member->pendingAmount(),
            'memberships' => $memberships,
            'payments' => $payments,
            'totalPaid' => (float) $payments->sum('amount'),
        ]);
    }
}
