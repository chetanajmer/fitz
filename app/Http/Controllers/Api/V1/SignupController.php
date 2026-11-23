<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\MemberOtpService;
use App\Services\MemberSignupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * New member sign-up from the app: verify phone by OTP, choose city and gym, send details.
 * The member is created as pending and can log in (via /auth/send-otp) once the gym approves.
 */
class SignupController extends ApiController
{
    protected const PHONE_RULE = ['required', 'regex:/^\+?[0-9\s\-]{10,15}$/'];

    public function __construct(protected MemberSignupService $signup) {}

    /** POST /api/v1/auth/register/send-otp  { phone } */
    public function sendOtp(Request $request): JsonResponse
    {
        $data = $request->validate(['phone' => self::PHONE_RULE], ['phone.regex' => 'Enter a valid 10-digit mobile number.']);

        $result = $this->signup->sendOtp((string) $data['phone'], $request->ip());

        $payload = [
            'phone' => MemberOtpService::maskPhone((string) $data['phone']),
            'expires_in' => (int) config('gymai.otp.ttl_seconds'),
            'resend_after' => (int) config('gymai.otp.resend_cooldown_seconds'),
        ];

        if (config('gymai.otp.expose_in_response') && ! app()->isProduction()) {
            $payload['debug_otp'] = $result['code'];
        }

        return $this->success($payload, 'OTP sent to your mobile number.');
    }

    /** POST /api/v1/auth/register/verify-otp  { phone, otp } → registration_token */
    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => self::PHONE_RULE,
            'otp' => ['required', 'digits:'.(int) config('gymai.otp.length', 6)],
        ]);

        $token = $this->signup->verifyOtp((string) $data['phone'], (string) $data['otp']);

        return $this->success([
            'registration_token' => $token,
            'expires_in' => MemberSignupService::TOKEN_TTL_MINUTES * 60,
        ], 'Phone verified. Choose your city and gym.');
    }

    /** POST /api/v1/auth/register  { registration_token, city_id, gym_id, name, ...details } */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'registration_token' => ['required', 'string', 'size:64'],
            'city_id' => ['required', 'integer'],
            'gym_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'gender' => ['nullable', 'in:male,female,other'],
            'date_of_birth' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'address' => ['nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:120'],
            'emergency_contact_phone' => ['nullable', 'string', 'regex:/^[0-9+\-\s]{8,20}$/'],
            'fitness_goal' => ['nullable', 'string', 'max:255'],
            'medical_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $member = $this->signup->register($data['registration_token'], (int) $data['city_id'], (int) $data['gym_id'], $data);

        return $this->success([
            'member_code' => $member->member_code,
            'status' => $member->status->value,
            'gym' => ['gym_id' => $member->gym_id, 'name' => $member->gym->name],
        ], "Registration sent to {$member->gym->name}. You can log in once the gym approves it.", 201);
    }
}
