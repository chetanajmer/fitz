@props(['title' => null, 'wide' => false, 'split' => false])
{{-- `split`: a full-width two-panel card (image + form) that carries its own branding, over a blurred gym backdrop. --}}
<x-layouts.base :title="$title" body-class="theme-dark">
    {{-- overflow-clip (not hidden): clips the glows without becoming a scroll box that focusing an input can shift. --}}
    <div class="relative flex min-h-full flex-col items-center justify-center overflow-clip px-4 py-10">
        @if ($split)
            <div aria-hidden="true" class="pointer-events-none absolute inset-0">
                <img src="{{ asset('images/landing/hero-athlete.jpg') }}" alt="" class="size-full scale-110 object-cover opacity-30 blur-2xl">
                <div class="absolute inset-0 bg-gradient-to-b from-gym-black/30 via-gym-black/70 to-gym-black"></div>
            </div>
        @endif
        {{-- Orange ambient glow, echoing neon gym lighting. --}}
        <div aria-hidden="true" class="pointer-events-none absolute -top-40 left-1/2 h-[28rem] w-[46rem] -translate-x-1/2 rounded-full bg-brand-600/20 blur-3xl"></div>
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-[linear-gradient(rgb(255_255_255/0.025)_1px,transparent_1px),linear-gradient(90deg,rgb(255_255_255/0.025)_1px,transparent_1px)] bg-[size:48px_48px]"></div>

        @unless ($split)
            <a href="{{ route('home') }}" class="relative mb-8 flex items-center gap-3">
                <div class="flex size-11 items-center justify-center rounded-xl bg-brand-600 text-lg font-bold text-white shadow-[0_0_28px_-2px_rgb(255_107_0/0.7)]">G</div>
                <span class="text-2xl font-semibold tracking-tight text-white">Gymai</span>
            </a>
        @endunless
        <div class="relative w-full {{ $split ? 'max-w-6xl' : ($wide ? 'max-w-2xl' : 'max-w-md') }}">
            {{ $slot }}
        </div>
    </div>
</x-layouts.base>
