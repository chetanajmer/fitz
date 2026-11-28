<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\MemberOtpService;
use App\Services\StaffAuthService;
use App\Tenancy\TenantManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Staff app sign-in: /api/v1/admin/auth/* */
class AuthController extends AdminController
{
    public function __construct(protected StaffAuthService $auth, protected TenantManager $tenant) {}

    /** POST /admin/auth/login { email, password, device_name? } — "email" may also be the verified sign-in mobile. */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $accounts = $this->auth->attemptPassword($data['email'], $data['password'], $request->ip());

        return $this->signInOrChoose($request, $accounts, $data['device_name'] ?? null);
    }

    /** POST /admin/auth/send-otp { identifier } — same answer whether or not an account exists. */
    public function sendOtp(Request $request): JsonResponse
    {
        $identifier = trim((string) $request->input('identifier'));
        $byEmail = StaffAuthService::channel($identifier) === 'email';
        $request->merge(['identifier' => $byEmail ? $identifier : preg_replace('/[\s()-]/', '', $identifier)]);

        $data = $request->validate(
            ['identifier' => $byEmail ? ['required', 'email', 'max:255'] : ['required', 'regex:/^(\+?91|0)?[6-9]\d{9}$/']],
            ['identifier.regex' => 'Enter a valid email address or 10-digit mobile number.'],
        );

        $this->auth->sendCode($data['identifier'], $request->ip());

        return $this->success([
            'sent_to' => $this->maskedDestination($data['identifier']),
            'otp_length' => (int) config('gymai.otp.length', 6),
            'expires_in' => (int) config('gymai.otp.ttl_seconds'),
            'resend_after' => (int) config('gymai.otp.resend_cooldown_seconds'),
        ], 'If this email or mobile belongs to a Fitnix staff account, a code has been sent.');
    }

    /** POST /admin/auth/verify-otp { identifier, otp, device_name? } */
    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'otp' => ['required', 'digits:'.(int) config('gymai.otp.length', 6)],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $identifier = StaffAuthService::channel($data['identifier']) === 'email' ? trim($data['identifier']) : preg_replace('/[\s()-]/', '', $data['identifier']);
        $this->auth->verifyCode($identifier, (string) $data['otp']);

        $accounts = $this->auth->staffAccounts($identifier);

        if ($accounts->isEmpty()) {
            throw new ApiException('No active staff account uses this email or mobile. Members sign in with the member app.', 'NO_STAFF_ACCOUNT', 404);
        }

        return $this->signInOrChoose($request, $accounts, $data['device_name'] ?? null);
    }

    /** POST /admin/auth/select-account { selection_token, account, device_name? } */
    public function selectAccount(Request $request): JsonResponse
    {
        $data = $request->validate([
            'selection_token' => ['required', 'string', 'max:100'],
            'account' => ['required', 'string', 'max:40'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $this->auth->redeemSelection($data['selection_token'], $data['account']);

        return $this->tokenResponse($user, $data['device_name'] ?? null);
    }

    /** POST /admin/auth/logout { device_token? } */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->input('device_token');
        $this->auth->logout($this->staff($request), is_string($token) && $token !== '' ? $token : null);

        return $this->success(null, 'Logged out.');
    }

    /** @param  Collection<int, User>  $accounts */
    protected function signInOrChoose(Request $request, Collection $accounts, ?string $deviceName): JsonResponse
    {
        if ($accounts->count() === 1) {
            return $this->tokenResponse($accounts->first(), $deviceName);
        }

        throw new ApiException('Choose which account to sign in to.', 'CHOOSE_ACCOUNT', 409, [
            'accounts' => $this->auth->choices($accounts),
            'selection_token' => $this->auth->issueSelectionToken($accounts),
            'expires_in' => StaffAuthService::SELECTION_TTL_SECONDS,
        ]);
    }

    protected function tokenResponse(User $user, ?string $deviceName): JsonResponse
    {
        $token = $this->auth->issueToken($user, $deviceName);
        $profile = $this->tenant->runAs($user->gym, fn () => $this->staffProfile($user));

        return $this->success(['token' => $token, 'token_type' => 'Bearer'] + $profile, 'Login successful.');
    }

    protected function maskedDestination(string $identifier): string
    {
        if (StaffAuthService::channel($identifier) === 'sms') {
            return MemberOtpService::maskPhone($identifier);
        }

        [$name, $domain] = explode('@', StaffAuthService::destination($identifier), 2);

        return Str::substr($name, 0, 2).'•••@'.$domain;
    }
}
