<?php

namespace App\Services;

use App\Models\ImpersonationToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Super Admin "login as" via single-use magic links.
 *
 *  - Links expire after LINK_MINUTES and work once. Only a SHA-256 hash is stored.
 *  - Super Admin accounts can never be opened this way.
 *  - "Return to Super Admin" is offered only when the link is opened in the same browser
 *    session where the issuing Super Admin is signed in, so a leaked link can never be
 *    used to reach the Super Admin account.
 *  - Every link is an audit record (who, for whom, when, from which IP).
 */
class ImpersonationService
{
    public const LINK_MINUTES = 10;

    public const SESSION_KEY = 'impersonation';

    /** @return array{url: string, token: ImpersonationToken} */
    public function createLink(User $target, User $admin): array
    {
        if (! $admin->isSuperAdmin()) {
            abort(403);
        }

        if ($target->isSuperAdmin()) {
            throw ValidationException::withMessages(['impersonate' => 'Super Admin accounts cannot be opened with a magic link.']);
        }

        if (! $target->isActive()) {
            throw ValidationException::withMessages(['impersonate' => "{$target->name}'s account is inactive. Activate it first."]);
        }

        $plain = Str::random(64);

        $token = new ImpersonationToken;
        $token->token_hash = hash('sha256', $plain);
        $token->user_id = $target->id;
        $token->created_by = $admin->id;
        $token->gym_id = $target->gym_id;
        $token->expires_at = now()->addMinutes(self::LINK_MINUTES);
        $token->save();

        return ['url' => route('impersonate.consume', $plain), 'token' => $token];
    }

    /**
     * Validate and burn the token, then sign in as the target user.
     */
    public function consume(string $plain, Request $request): User
    {
        $current = $request->user();

        $token = DB::transaction(function () use ($plain, $request) {
            $token = ImpersonationToken::where('token_hash', hash('sha256', $plain))->lockForUpdate()->first();

            if (! $token || $token->used_at !== null || $token->expires_at->isPast()) {
                return null;
            }

            $token->used_at = now();
            $token->used_ip = $request->ip();
            $token->used_user_agent = Str::limit((string) $request->userAgent(), 250, '');
            $token->save();

            return $token;
        });

        $target = $token?->user;
        $issuer = $token?->creator;

        if (! $token || ! $target || ! $issuer?->isSuperAdmin() || $target->isSuperAdmin() || ! $target->isActive()) {
            abort(403, 'This login link is invalid, expired or has already been used.');
        }

        // Return-to-admin only for the issuing Super Admin's own browser session.
        $canReturn = $current && $current->id === $issuer->id;

        Auth::guard('web')->login($target);
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, [
            'token_id' => $token->id,
            'by_name' => $issuer->name,
            'return_to' => $canReturn ? $issuer->id : null,
            'gym_id' => $target->gym_id,
        ]);

        return $target;
    }

    /** End impersonation. Returns the Super Admin if the session may switch back. */
    public function leave(Request $request): ?User
    {
        $data = $request->session()->pull(self::SESSION_KEY);
        $admin = ! empty($data['return_to']) ? User::find($data['return_to']) : null;

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($admin?->isSuperAdmin() && $admin->isActive()) {
            Auth::guard('web')->login($admin);
            $request->session()->regenerate();

            return $admin;
        }

        return null;
    }
}
