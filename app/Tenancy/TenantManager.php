<?php

namespace App\Tenancy;

use App\Models\Gym;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Auth;

/**
 * Resolves the gym ("tenant") the current request operates on.
 *
 * Resolution order:
 *  1. A gym explicitly set via set()/runAs() (public profile pages, QR join, jobs, CLI).
 *  2. The authenticated user's own gym_id (gym_admin / trainer / member).
 *
 * The gym id ALWAYS comes from the server side (session user or a route token resolved
 * by the server). It is never read from request input.
 */
class TenantManager
{
    protected ?Gym $gym = null;

    protected bool $explicit = false;

    public function set(?Gym $gym): void
    {
        $this->gym = $gym;
        $this->explicit = $gym !== null;
    }

    public function forget(): void
    {
        $this->gym = null;
        $this->explicit = false;
    }

    /**
     * Run a callback scoped to the given gym, restoring the previous tenant afterwards.
     */
    public function runAs(Gym $gym, Closure $callback): mixed
    {
        [$previousGym, $previousExplicit] = [$this->gym, $this->explicit];
        $this->set($gym);

        try {
            return $callback($gym);
        } finally {
            $this->gym = $previousGym;
            $this->explicit = $previousExplicit;
        }
    }

    public function id(): ?int
    {
        if ($this->explicit) {
            return $this->gym?->id;
        }

        $user = $this->user();

        return $user && ! $user->isSuperAdmin() ? $user->gym_id : null;
    }

    public function gym(): ?Gym
    {
        if ($this->explicit) {
            return $this->gym;
        }

        $id = $this->id();

        if ($id === null) {
            return null;
        }

        if ($this->gym?->id !== $id) {
            $this->gym = Gym::find($id);
        }

        return $this->gym;
    }

    public function check(): bool
    {
        return $this->id() !== null;
    }

    /**
     * True when the current actor may see data across all gyms (Super Admin, CLI, queue).
     */
    public function bypasses(): bool
    {
        if ($this->explicit) {
            return false;
        }

        $user = $this->user();

        if ($user === null) {
            // Unauthenticated web requests must never see tenant data unless a gym was set explicitly.
            return app()->runningInConsole() && ! app()->runningUnitTests();
        }

        return $user->isSuperAdmin();
    }

    protected function user(): ?User
    {
        // Only staff/portal Users resolve a tenant implicitly. Mobile API members (Sanctum
        // "member" guard) get their tenant set explicitly by SetMemberApiContext.
        $user = Auth::guard('web')->user();

        return $user instanceof User ? $user : null;
    }
}
