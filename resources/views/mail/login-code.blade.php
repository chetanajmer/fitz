<x-mail::message>
# Your sign-in code

Use this code to sign in to {{ $platformName }}:

<x-mail::panel>
**{{ $code }}**
</x-mail::panel>

It is valid for {{ $minutes }} minutes. Do not share it with anyone. If you did not try to sign in, you can ignore this email.

{{ $platformName }}
</x-mail::message>
