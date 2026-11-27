<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\DietPreference;
use App\Exceptions\ApiException;
use App\Models\Member;
use App\Services\MemberProfileService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PUT|PATCH /api/v1/me/profile — a partial update of the member's own details.
 * Only the fields listed in MemberProfileService::EDITABLE_FIELDS may be sent; anything else
 * (name, phone, member code, gym, membership…) is rejected with FIELD_NOT_EDITABLE.
 */
class UpdateMemberProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->member() !== null;
    }

    public function member(): ?Member
    {
        return $this->user('member');
    }

    protected function prepareForValidation(): void
    {
        $notEditable = array_values(array_diff(array_keys($this->input()), MemberProfileService::EDITABLE_FIELDS, ['_method']));

        if ($notEditable !== []) {
            throw new ApiException(
                'These fields can\'t be changed from the app: '.implode(', ', $notEditable).'. Please ask your gym.',
                'FIELD_NOT_EDITABLE', 422, ['fields' => $notEditable, 'editable_fields' => MemberProfileService::EDITABLE_FIELDS],
            );
        }

        // Accept "+91 98765-43210" style input; store digits only.
        if (is_string($this->input('emergency_contact_phone'))) {
            $this->merge(['emergency_contact_phone' => preg_replace('/[\s\-()]+|^\+/', '', $this->input('emergency_contact_phone'))]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $member = $this->member();

        return [
            'email' => ['sometimes', 'nullable', 'email', 'max:150',
                Rule::unique('members', 'email')->where('gym_id', $member->gym_id)->whereNull('deleted_at')->ignore($member->id),
                // The web-portal login uses the email as username, so it must stay unique among users too.
                Rule::unique('users', 'email')->ignore($member->user_id)],
            'gender' => ['sometimes', 'nullable', 'in:male,female,other'],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today', 'after:1920-01-01'],
            'blood_group' => ['sometimes', 'nullable', 'in:A+,A-,B+,B-,AB+,AB-,O+,O-'],
            'height_cm' => ['sometimes', 'nullable', 'numeric', 'between:50,250'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:80'],
            'emergency_contact_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['sometimes', 'nullable', 'regex:/^[0-9]{10,15}$/', $this->notOwnPhone($member)],
            'emergency_contact_relation' => ['sometimes', 'nullable', 'string', 'max:50'],
            'fitness_goal' => ['sometimes', 'nullable', 'string', 'max:100'],
            'diet_preference' => ['sometimes', 'nullable', Rule::enum(DietPreference::class)],
            'medical_notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.unique' => 'This email is already used by another account.',
            'emergency_contact_phone.regex' => 'Enter a phone number of 10 to 15 digits.',
            'date_of_birth.after' => 'Enter a valid date of birth.',
        ];
    }

    /** The emergency contact must be someone else: compare the last 10 digits with the member's phone. */
    protected function notOwnPhone(Member $member): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($member): void {
            $own = substr(preg_replace('/\D/', '', (string) $member->phone), -10);

            if ($own !== '' && substr((string) $value, -10) === $own) {
                $fail('The emergency contact number must be different from your own number.');
            }
        };
    }

    /**
     * The validated fields, with blank strings turned into null (clears the field).
     *
     * @return array<string, mixed>
     */
    public function profileData(): array
    {
        return collect($this->validated())
            ->map(fn ($value) => is_string($value) && trim($value) === '' ? null : $value)
            ->all();
    }
}
