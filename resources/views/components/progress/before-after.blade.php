{{-- Drag the slider to compare two photos. --}}
@props(['before', 'after'])
<div x-data="{ at: 50 }" wire:ignore class="relative aspect-[3/4] w-full overflow-hidden rounded-lg border border-gray-200 bg-gray-100 select-none">
    <img src="{{ $after }}" alt="After" class="absolute inset-0 size-full object-contain">
    <img src="{{ $before }}" alt="Before" class="absolute inset-0 size-full bg-gray-100 object-contain" :style="`clip-path: inset(0 ${100 - at}% 0 0)`">
    <div class="pointer-events-none absolute inset-y-0 w-0.5 bg-white shadow" :style="`left: ${at}%`"></div>
    <span class="absolute top-2 left-2 rounded bg-black/60 px-2 py-0.5 text-xs text-white">Before</span>
    <span class="absolute top-2 right-2 rounded bg-black/60 px-2 py-0.5 text-xs text-white">After</span>
    <input type="range" min="0" max="100" x-model="at" aria-label="Compare before and after" class="absolute inset-0 size-full cursor-ew-resize opacity-0">
</div>
