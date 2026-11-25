{{-- Drag the slider to compare two photos. "reveal" sweeps it once, from the before photo, when it comes into view. --}}
@props(['before', 'after', 'reveal' => false])
<div x-data="{
        at: 50,
        reveal() {
            if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            const start = performance.now();
            const frame = (now) => {
                if (! this.$el.isConnected || this.touched) return;
                const t = Math.max(0, Math.min((now - start - 400) / 1800, 1));
                this.at = 100 - 50 * (1 - Math.pow(1 - t, 3));
                if (t < 1) requestAnimationFrame(frame);
            };
            requestAnimationFrame(frame);
        },
        touched: false,
    }" @if ($reveal) x-intersect.once.half="reveal()" @endif x-on:pointerdown="touched = true" x-on:keydown="touched = true" wire:ignore
    {{ $attributes->class('relative aspect-[3/4] w-full overflow-hidden bg-gray-100 select-none '.($reveal ? '' : 'rounded-lg border border-gray-200')) }}>
    <img src="{{ $after }}" alt="After" class="absolute inset-0 size-full object-contain">
    <img src="{{ $before }}" alt="Before" class="absolute inset-0 size-full bg-gray-100 object-contain" :style="`clip-path: inset(0 ${100 - at}% 0 0)`">
    <div class="pointer-events-none absolute inset-y-0 w-0.5 bg-white shadow" :style="`left: ${at}%`"></div>
    <span class="absolute top-2 left-2 rounded bg-black/60 px-2 py-0.5 text-xs text-white">Before</span>
    <span class="absolute top-2 right-2 rounded bg-black/60 px-2 py-0.5 text-xs text-white">After</span>
    <input type="range" min="0" max="100" x-model="at" aria-label="Compare before and after" class="absolute inset-0 size-full cursor-ew-resize opacity-0">
</div>
