<x-mail::message>
# Account deletion request

{{ $staffName }} ({{ $roleLabel }} at {{ $gymName }}) asked in the staff app for their {{ $platformName }} account to be deleted.

- **Email:** {{ $staffEmail }}
- **Request number:** {{ $requestId }}
@if ($reason)
- **Reason:** {{ $reason }}
@endif

@if ($role === 'gym_admin')
This is a gym admin account, so the Super Admin handles it. Nothing has been deleted yet.
@else
The gym can switch the trainer off or delete them in Trainers. Nothing has been deleted yet.
@endif

Please complete the request within 30 days.

{{ $platformName }}
</x-mail::message>
