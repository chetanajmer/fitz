{{-- Where a website/app sign-up stands, as told to the person who made it. --}}
@props(['status'])
@php
    [$color, $label, $hint] = match ($status) {
        'approved' => ['green', 'Approved', 'You can now log in to the Fitnix app with your mobile number. Visit the front desk to choose a membership plan.'],
        'declined' => ['red', 'Not accepted', 'The gym did not accept this registration. Please contact the gym for details.'],
        'inactive' => ['gray', 'Inactive', 'Your membership at this gym is inactive. Please contact the gym.'],
        default => ['amber', 'Waiting for approval', 'The gym has your request. You can log in to the Fitnix app once they approve it.'],
    };
@endphp
<div {{ $attributes->merge(['class' => 'text-center']) }}>
    <x-ui.badge :color="$color" class="px-3 py-1 text-sm">{{ $label }}</x-ui.badge>
    <p class="mx-auto mt-2 max-w-sm text-sm text-gray-500">{{ $hint }}</p>
</div>
