@props(['color' => 'gray'])
@php
    $colors = [
        'gray' => 'bg-gray-100 text-gray-700 ring-gray-200',
        'green' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'red' => 'bg-red-50 text-red-700 ring-red-200',
        'amber' => 'bg-amber-50 text-amber-800 ring-amber-200',
        'blue' => 'bg-sky-50 text-sky-700 ring-sky-200',
        'indigo' => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
        'purple' => 'bg-purple-50 text-purple-700 ring-purple-200',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset '.($colors[$color] ?? $colors['gray'])]) }}>{{ $slot }}</span>
