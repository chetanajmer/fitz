<?php

namespace App\Policies;

use App\Models\SaasInvoice;
use App\Models\User;

class SaasInvoicePolicy
{
    public function view(User $user, SaasInvoice $invoice): bool
    {
        return $user->can('gym.billing') && (int) $invoice->gym_id === (int) $user->gym_id;
    }
}
