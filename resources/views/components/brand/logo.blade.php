{{-- Fitnix logo: the mark on an orange tile, and the FITNIX wordmark in the display face. size: sm | md | lg --}}
@props(['size' => 'md', 'wordmark' => true])
@php
    [$tile, $mark, $text] = [
        'sm' => ['size-8 rounded-lg', 'size-5', 'text-2xl'],
        'md' => ['size-10 rounded-xl', 'size-6', 'text-3xl'],
        'lg' => ['size-12 rounded-xl', 'size-7', 'text-4xl'],
    ][$size] ?? ['size-10 rounded-xl', 'size-6', 'text-3xl'];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <span class="flex {{ $tile }} shrink-0 items-center justify-center bg-gym-orange text-gym-black shadow-[0_0_24px_-6px_var(--gym-orange)]">
        <x-brand.mark class="{{ $mark }}" />
    </span>
    @if ($wordmark)
        <span class="font-display {{ $text }} leading-none tracking-wide text-white uppercase">Fit<span class="text-gym-orange">nix</span></span>
    @endif
</span>
