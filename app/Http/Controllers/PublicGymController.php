<?php

namespace App\Http\Controllers;

use App\Models\Gym;
use App\Models\MembershipPlan;
use App\Tenancy\TenantManager;
use Illuminate\Http\Request;

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

    /**
     * gymai.com/register/{token} — the gym's desk QR.
     * New visitors register here; a signed-in member of this gym is sent to their check-in page,
     * which checks them in (or out) with this token.
     */
    public function register(Request $request, string $token)
    {
        $gym = Gym::where('register_token', $token)->first();

        abort_unless($gym && $gym->isOperational(), 404);

        $user = $request->user();

        if ($user?->isMember() && (int) $user->gym_id === $gym->id) {
            return redirect()->route('portal.check-in', ['desk' => $token]);
        }

        if (! $user) {
            // "Already a member? Log in" brings them back here, which then checks them in.
            redirect()->setIntendedUrl($request->fullUrl());
        }

        return view('public.register', ['gym' => $gym, 'showMemberLogin' => ! $user]);
    }
}
