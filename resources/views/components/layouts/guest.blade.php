@props(['title' => null, 'wide' => false])
<x-layouts.base :title="$title" body-class="theme-dark">
    <div class="relative flex min-h-full flex-col items-center justify-center overflow-hidden px-4 py-10">
        {{-- Orange ambient glow, echoing neon gym lighting. --}}
        <div aria-hidden="true" class="pointer-events-none absolute -top-40 left-1/2 h-[28rem] w-[46rem] -translate-x-1/2 rounded-full bg-brand-600/20 blur-3xl"></div>
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-[linear-gradient(rgb(255_255_255/0.025)_1px,transparent_1px),linear-gradient(90deg,rgb(255_255_255/0.025)_1px,transparent_1px)] bg-[size:48px_48px]"></div>

        <a href="{{ route('home') }}" class="relative mb-8 flex items-center gap-3">
            <div class="flex size-11 items-center justify-center rounded-xl bg-brand-600 text-lg font-bold text-white shadow-[0_0_28px_-2px_rgb(255_107_0/0.7)]">G</div>
            <span class="text-2xl font-semibold tracking-tight text-white">Gymai</span>
        </a>
        <div class="relative w-full {{ $wide ? 'max-w-2xl' : 'max-w-md' }}">
            {{ $slot }}
        </div>
    </div>
</x-layouts.base>
