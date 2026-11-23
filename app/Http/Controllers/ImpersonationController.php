<?php

namespace App\Http\Controllers;

use App\Services\ImpersonationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    public function __construct(protected ImpersonationService $impersonation) {}

    /** GET /login-as/{token} — magic link target. */
    public function consume(Request $request, string $token): RedirectResponse
    {
        $user = $this->impersonation->consume($token, $request);

        return redirect($user->homeRoute())->with('success', "You are now logged in as {$user->name}.");
    }

    /** POST /login-as/leave */
    public function leave(Request $request): RedirectResponse
    {
        $gymId = $request->session()->get(ImpersonationService::SESSION_KEY.'.gym_id');
        $admin = $this->impersonation->leave($request);

        if ($admin) {
            return redirect($gymId ? route('platform.gyms.show', $gymId) : route('platform.dashboard'))
                ->with('success', 'Back in your Super Admin account.');
        }

        return redirect()->route('login')->with('success', 'Signed out of the account.');
    }
}
