<?php

namespace App\Http\Controllers;

use App\Models\Gym;
use App\Models\MembershipPlan;
use App\Tenancy\TenantManager;

class PublicGymController extends Controller
{
    public function __construct(protected TenantManager $tenant) {}

    /** gymai.com/{slug} */
    public function show(Gym $gym)
    {
        abort_unless($gym->isOperational(), 404);

        return $this->tenant->runAs($gym, fn () => view('public.gym-profile', [
            'gym' => $gym,
            'plans' => MembershipPlan::active()->where('is_public', true)->orderBy('sort_order')->orderBy('price')->get(),
            'photos' => $gym->photos()->get(),
        ]));
    }

    /** gymai.com/join/{token} — the gym is resolved from the secret token, server side. */
    public function join(string $token)
    {
        $gym = Gym::where('join_token', $token)->first();

        abort_unless($gym && $gym->isOperational(), 404);

        return view('public.join', ['gym' => $gym]);
    }
}
