@props(['label', 'value', 'hint' => null, 'icon' => null, 'color' => 'brand', 'href' => null])
@php
    $tones = [
        'brand' => 'bg-indigo-50 text-indigo-600',
        'green' => 'bg-emerald-50 text-emerald-600',
        'red' => 'bg-red-50 text-red-600',
        'amber' => 'bg-amber-50 text-amber-600',
        'blue' => 'bg-sky-50 text-sky-600',
        'gray' => 'bg-gray-100 text-gray-600',
    ];
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'card card-body flex items-start gap-4 '.($href ? 'transition hover:border-indigo-300 hover:shadow' : '')]) }}>
    @if ($icon)
        <div class="flex size-10 shrink-0 items-center justify-center rounded-lg {{ $tones[$color] ?? $tones['brand'] }}">
            <x-ui.icon :name="$icon" />
        </div>
    @endif
    <div class="min-w-0">
        <p class="text-xs font-medium tracking-wide text-gray-500 uppercase">{{ $label }}</p>
        <p class="mt-1 truncate text-2xl font-semibold text-gray-900">{{ $value }}</p>
        @if ($hint)
            <p class="mt-0.5 text-xs text-gray-500">{{ $hint }}</p>
        @endif
    </div>
</{{ $tag }}>
