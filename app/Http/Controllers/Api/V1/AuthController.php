<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\MemberResource;
use App\Services\MemberOtpService;
use App\Services\PushNotificationService;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends ApiController
{
    public function __construct(protected MemberOtpService $otp, protected TenantManager $tenant) {}

    /**
     * POST /api/v1/auth/send-otp  { phone, gym_id?, name?, city?, latitude?, longitude?, app_platform?, app_version? }
     *
     * The optional fields are only used when the number is not connected to any gym:
     * it is then saved as a platform lead so the support team can call back with nearby gyms.
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'regex:/^\+?[0-9\s\-]{10,15}$/'],
            'gym_id' => ['nullable', 'integer'],
            'name' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:80'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'app_platform' => ['nullable', 'in:android,ios'],
            'app_version' => ['nullable', 'string', 'max:20'],
        ], ['phone.regex' => 'Enter a valid 10-digit mobile number.']);

        $leadInfo = collect($data)->only(['name', 'city', 'latitude', 'longitude', 'app_platform', 'app_version'])->filter()->all();

        $result = $this->otp->send((string) $data['phone'], $data['gym_id'] ?? null, $request->ip(), $leadInfo);

        $payload = [
            'phone' => MemberOtpService::maskPhone((string) $data['phone']),
            'gym_id' => $result['member']->gym_id,
            'otp_length' => (int) config('gymai.otp.length', 6),
            'expires_in' => (int) config('gymai.otp.ttl_seconds'),
            'resend_after' => (int) config('gymai.otp.resend_cooldown_seconds'),
        ];

        if (config('gymai.otp.expose_in_response') && app()->isLocal()) {
            $payload['debug_otp'] = $result['code'];
        }

        return $this->success($payload, 'OTP sent to your mobile number.');
    }

    /**
     * POST /api/v1/auth/verify-otp  { phone, otp, gym_id?, device_name? }
     * Returns a Sanctum bearer token and the member's gym_id (used to load branding).
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $length = (int) config('gymai.otp.length', 6);

        $data = $request->validate([
            'phone' => ['required', 'regex:/^\+?[0-9\s\-]{10,15}$/'],
            'otp' => ['required', 'digits:'.$length],
            'gym_id' => ['nullable', 'integer'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $member = $this->otp->verify((string) $data['phone'], (string) $data['otp'], $data['gym_id'] ?? null);

        $token = $member->createToken($data['device_name'] ?? 'member-app', ['member'])->plainTextToken;

        // No tenant is set on this public route; resolve the member (memberships etc.) inside their own gym,
        // otherwise the fail-closed GymScope hides their memberships and membership_state reads "none".
        $memberData = $this->tenant->runAs($member->gym, fn () => (new MemberResource($member))->resolve($request));

        return $this->success([
            'token' => $token,
            'token_type' => 'Bearer',
            'gym_id' => $member->gym_id,
            'member' => $memberData,
        ], 'Login successful.');
    }

    /**
     * POST /api/v1/auth/logout — revokes the current token only.
     */
    public function logout(Request $request): JsonResponse
    {
        $member = $request->user('member');

        // Optional: the app sends its Firebase token so this phone stops receiving notifications.
        if (is_string($token = $request->input('device_token')) && $token !== '') {
            $this->tenant->runAs($member->gym, fn () => app(PushNotificationService::class)->removeDevice($member, $token));
        }

        $member->currentAccessToken()?->delete();

        return $this->success(null, 'Logged out.');
    }
}
