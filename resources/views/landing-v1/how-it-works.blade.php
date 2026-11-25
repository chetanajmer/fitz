@php
    $steps = [
        ['user', 'Create Your Account', 'Sign up with your name, email and phone. It takes less than a minute.'],
        ['building', 'Register Your Gym', 'Add your gym name, city and address. Your own gym page and QR code are ready instantly.'],
        ['card', 'Choose Your Subscription', 'One simple plan with every feature. You pay only for members with an active membership.'],
        ['chart', 'Manage Your Gym', 'Add plans, members and trainers, collect fees and track attendance from day one.'],
    ];
@endphp
<section id="how-it-works" data-lp-section class="relative overflow-hidden border-y border-ink-700 bg-ink-900 py-14 sm:py-20">
    <div aria-hidden="true" class="pointer-events-none absolute top-1/2 left-1/2 h-80 w-[50rem] -translate-x-1/2 -translate-y-1/2 rounded-full bg-brand-600/10 blur-3xl"></div>
    <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center">
            <p class="lp-reveal text-sm font-semibold tracking-[0.2em] text-brand-500 uppercase">How it works</p>
            <h2 data-split class="lp-reveal mt-3 text-3xl font-bold tracking-tight sm:text-5xl" style="--d: 80ms">Up and running in four steps</h2>
        </div>

        <ol class="relative mt-10 grid gap-10 md:grid-cols-4 md:gap-6">
            {{-- Connector line (desktop) --}}
            <div aria-hidden="true" class="absolute top-7 right-[12%] left-[12%] hidden h-px bg-ink-700 md:block"></div>
            <div aria-hidden="true" data-lp-connector class="absolute top-7 right-[12%] left-[12%] hidden h-0.5 origin-left bg-gradient-to-r from-brand-700 via-brand-500 to-brand-400 shadow-[0_0_14px_rgb(238_68_68/0.8)] md:block"></div>
            @foreach ($steps as $i => [$icon, $title, $text])
                <li class="lp-reveal relative text-center" style="--d: {{ $i * 140 }}ms">
                    <div class="relative mx-auto flex size-14 items-center justify-center rounded-2xl border border-brand-600/40 bg-ink-850 text-brand-400 shadow-[0_0_30px_-6px_rgb(238_68_68/0.7)]">
                        <x-ui.icon :name="$icon" class="size-6" />
                        <span class="absolute -top-2 -right-2 flex size-6 items-center justify-center rounded-full bg-brand-600 text-xs font-bold text-white">{{ $i + 1 }}</span>
                    </div>
                    <p class="mt-6 text-xs font-semibold tracking-widest text-zinc-500 uppercase">Step {{ $i + 1 }}</p>
                    <h3 class="mt-1 text-lg font-semibold">{{ $title }}</h3>
                    <p class="mx-auto mt-2 max-w-xs text-sm leading-relaxed text-zinc-400">{{ $text }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>
