<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\Role;
use App\Exceptions\ApiException;
use App\Mail\StaffDeletionRequestMail;
use App\Models\Member;
use App\Models\StaffDeletionRequest;
use App\Models\StaffDevice;
use App\Models\User;
use App\Services\StaffPhoneOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/** The signed-in staff user's own account: /api/v1/admin/me*, /admin/devices */
class AccountController extends AdminController
{
    /** GET /admin/me */
    public function show(Request $request): JsonResponse
    {
        return $this->success($this->staffProfile($this->staff($request)), 'Profile.');
    }

    /** PUT /admin/me { name, email, phone? } */
    public function update(Request $request): JsonResponse
    {
        $user = $this->staff($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $user->update(['name' => $data['name'], 'email' => strtolower($data['email']), 'phone' => $data['phone'] ?? null]);

        return $this->success($this->staffProfile($user->fresh()), 'Profile updated.');
    }

    /** PUT /admin/me/password { current_password, password, password_confirmation } */
    public function password(Request $request): JsonResponse
    {
        $user = $this->staff($request);
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Your current password is incorrect.']);
        }

        $user->update(['password' => Hash::make($data['password'])]);

        return $this->success(null, 'Password changed.');
    }

    /** POST /admin/me/login-phone/send-otp { phone } */
    public function sendLoginPhoneOtp(Request $request, StaffPhoneOtpService $otps): JsonResponse
    {
        $digits = $this->loginPhoneDigits($request);
        $this->ensurePhoneFree($digits, $this->staff($request));

        $this->otpCall(fn () => $otps->send($digits, StaffPhoneOtpService::VERIFY, $request->ip()));

        return $this->success([
            'otp_length' => (int) config('gymai.otp.length', 6),
            'expires_in' => (int) config('gymai.otp.ttl_seconds'),
            'resend_after' => (int) config('gymai.otp.resend_cooldown_seconds'),
        ], 'OTP sent to this mobile number.');
    }

    /** POST /admin/me/login-phone/verify-otp { phone, otp } */
    public function verifyLoginPhoneOtp(Request $request, StaffPhoneOtpService $otps): JsonResponse
    {
        $user = $this->staff($request);
        $digits = $this->loginPhoneDigits($request);
        $request->validate(['otp' => ['required', 'digits:'.(int) config('gymai.otp.length', 6)]]);

        $this->otpCall(fn () => $otps->verify($digits, StaffPhoneOtpService::VERIFY, (string) $request->input('otp')));
        $this->ensurePhoneFree($digits, $user);

        $user->forceFill(['login_phone' => $digits])->save();

        return $this->success($this->staffProfile($user), 'Mobile verified. You can now sign in with it.');
    }

    /** DELETE /admin/me/login-phone */
    public function removeLoginPhone(Request $request): JsonResponse
    {
        $user = $this->staff($request);
        $user->forceFill(['login_phone' => null])->save();

        return $this->success($this->staffProfile($user), 'Mobile sign-in removed.');
    }

    /** POST /admin/devices { token, platform, app_version? } — called on every app launch. */
    public function registerDevice(Request $request): JsonResponse
    {
        $user = $this->staff($request);
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['required', Rule::in(StaffDevice::PLATFORMS)],
            'app_version' => ['nullable', 'string', 'max:20'],
        ]);

        DB::transaction(function () use ($user, $data, $request) {
            // The phone may have been used by another staff user (of any gym) before.
            StaffDevice::withoutGlobalScopes()->where('token', $data['token'])->where('user_id', '!=', $user->id)->delete();

            $device = StaffDevice::where('user_id', $user->id)->where('token', $data['token'])->first() ?? new StaffDevice;
            $device->forceFill([
                'user_id' => $user->id,
                'access_token_id' => $request->user('staff')->currentAccessToken()?->id,
                'token' => $data['token'],
                'platform' => $data['platform'],
                'app_version' => $data['app_version'] ?? null,
                'last_seen_at' => now(),
            ])->save();
        });

        return $this->success(null, 'Device registered.');
    }

    /** DELETE /admin/devices { token } */
    public function removeDevice(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:512']]);
        StaffDevice::where('user_id', $this->staff($request)->id)->where('token', $data['token'])->delete();

        return $this->success(null, 'Device removed.');
    }

    /**
     * POST /admin/me/delete-request { reason? }
     * Records the request and emails the gym admins and the Super Admin. Nothing is deleted automatically:
     * a trainer's request is handled by the gym, a gym admin's request by the Super Admin.
     */
    public function deleteRequest(Request $request): JsonResponse
    {
        $user = $this->staff($request);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);

        $existing = StaffDeletionRequest::where('user_id', $user->id)->where('status', StaffDeletionRequest::PENDING)->first();

        if ($existing) {
            return $this->success($this->deletionData($existing), 'Your deletion request is already with us.');
        }

        $deletion = new StaffDeletionRequest;
        $deletion->forceFill([
            'user_id' => $user->id,
            'role' => $user->isGymAdmin() ? Role::GymAdmin->value : Role::Trainer->value,
            'reason' => $data['reason'] ?? null,
            'status' => StaffDeletionRequest::PENDING,
        ])->save();

        $gymAdmins = User::role(Role::GymAdmin->value)->where('gym_id', $user->gym_id)->whereKeyNot($user->id)->where('status', 'active')->whereNotNull('email')->pluck('email');
        $superAdmins = User::role(Role::SuperAdmin->value)->where('status', 'active')->whereNotNull('email')->pluck('email');

        foreach ($gymAdmins->concat($superAdmins)->unique() as $email) {
            Mail::to($email)->queue(new StaffDeletionRequestMail($deletion->id, $user->name, (string) $user->email, $user->gym->name, $deletion->role, $deletion->reason));
        }

        return $this->success($this->deletionData($deletion), 'Request received. We will delete your account within 30 days and email you when it is done.', 201);
    }

    /** @return array<string, mixed> */
    protected function deletionData(StaffDeletionRequest $deletion): array
    {
        return [
            'id' => $deletion->id,
            'status' => $deletion->status,
            'requested_at' => $this->iso($deletion->created_at),
            'handled_by' => $deletion->role === Role::GymAdmin->value ? 'super_admin' : 'gym_admin',
        ];
    }

    protected function loginPhoneDigits(Request $request): string
    {
        $request->merge(['phone' => preg_replace('/[\s()-]/', '', (string) $request->input('phone'))]);
        $request->validate(['phone' => ['required', 'regex:/^(\+?91|0)?[6-9]\d{9}$/']], ['phone.regex' => 'Enter a valid 10-digit Indian mobile number.']);

        return (string) Member::normalizePhone($request->input('phone'));
    }

    protected function ensurePhoneFree(string $digits, User $user): void
    {
        if (User::where('login_phone', $digits)->whereKeyNot($user->id)->exists()) {
            throw new ApiException('Another Fitnix account already signs in with this number.', 'PHONE_TAKEN', 422);
        }
    }

    /** Staff phone OTP limits use the same code as every other OTP endpoint: OTP_TOO_MANY. */
    protected function otpCall(\Closure $call): void
    {
        try {
            $call();
        } catch (ApiException $e) {
            if (in_array($e->errorCode, ['OTP_COOLDOWN', 'OTP_LIMIT_REACHED'], true)) {
                throw new ApiException($e->getMessage(), 'OTP_TOO_MANY', 429, ['retry_after' => (int) config('gymai.otp.resend_cooldown_seconds', 30)]);
            }

            throw $e;
        }
    }
}
