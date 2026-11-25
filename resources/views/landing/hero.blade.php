{{-- Hero: athlete photo, slamming headline on a slanted orange plate, live product cards (illustrative demo data) and a feature strip. --}}
@php
    $heroFeatures = [
        ['icon' => 'dumbbell', 'label' => 'Manage Members'],
        ['icon' => 'calendar', 'label' => 'Track Attendance'],
        ['icon' => 'card', 'label' => 'Handle Payments'],
        ['icon' => 'chart', 'label' => 'Get Reports'],
        ['icon' => 'star', 'label' => 'Grow Your Business'],
    ];
@endphp
<section id="home" data-lp-section class="relative isolate overflow-hidden bg-gym-black-deep pt-24 sm:pt-28 lg:pt-32">
    {{-- Athlete photo (decorative). Desktop: fills the right side from under the nav down to the feature strip —
         a blurred, dimmed copy is the gym backdrop and the sharp figure stands in the middle of it. Phones: a dim
         full-bleed backdrop behind the text. --}}
    <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10 overflow-hidden opacity-35 md:inset-auto md:top-16 md:right-0 md:bottom-32 md:left-[36%] md:opacity-100 lg:top-20 lg:bottom-[8.5rem] lg:left-[38%]">
        <img src="{{ asset('images/landing/hero-athlete.jpg') }}" alt="" class="lp-hero-ambient absolute inset-0 hidden size-full scale-110 object-cover md:block">
        <div class="absolute top-[12%] bottom-[8%] left-[42%] hidden w-[46%] -translate-x-1/2 rounded-full bg-gym-orange/15 blur-3xl md:block"></div>
        <img src="{{ asset('images/landing/hero-athlete.jpg') }}" alt="" fetchpriority="high" class="lp-hero-photo relative size-full object-cover object-[50%_15%] md:absolute md:top-0 md:left-[42%] md:h-full md:w-auto md:max-w-none md:-translate-x-1/2">
    </div>
    <div aria-hidden="true" class="pointer-events-none absolute -top-32 -left-40 -z-10 size-[34rem] rounded-full bg-gym-orange/10 blur-3xl"></div>

    <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <p class="lp-mono flex items-center gap-2.5 text-gym-light">
            <span class="relative flex size-2.5"><span class="absolute inset-0 animate-ping rounded-full bg-gym-orange"></span><span class="relative size-2.5 rounded-full bg-gym-orange"></span></span>
            Gym management software · made for Indian gyms
        </p>

        <h1 data-lp-hero-title class="lp-hero-title lp-display mt-4 sm:mt-6">
            <span class="block">Lift <br class="sm:hidden">your</span>
            <span class="mt-[0.06em] block pl-[0.3em]"><span class="lp-hl-slant">gym.</span></span>
        </h1>

        {{-- Rotating price sticker --}}
        <div data-lp-depth="0.6" class="absolute top-10 right-4 size-28 sm:top-0 sm:size-36 lg:right-8 lg:size-48">
            <svg viewBox="0 0 200 200" class="lp-badge absolute inset-0 size-full" aria-hidden="true">
                <defs><path id="lp-badge-path" d="M100 100 m-76 0 a76 76 0 1 1 152 0 a76 76 0 1 1 -152 0" /></defs>
                <circle cx="100" cy="100" r="97" fill="rgb(13 13 13 / 0.72)" stroke="var(--gym-orange)" stroke-width="3" />
                <circle cx="100" cy="100" r="58" fill="none" stroke="rgb(255 107 0 / 0.35)" stroke-width="1.5" />
                <text class="lp-mono" fill="var(--gym-orange)" font-size="15" font-weight="700" letter-spacing="4"><textPath href="#lp-badge-path">PER ACTIVE MEMBER ★ NO SETUP FEE ★</textPath></text>
            </svg>
            <p class="absolute inset-0 flex flex-col items-center justify-center text-gym-white">
                <span class="lp-display text-3xl text-gym-orange sm:text-4xl lg:text-5xl">{{ $priceLabel }}</span>
                <span class="lp-mono mt-1 text-[0.6rem] font-bold">/ month</span>
            </p>
        </div>

        {{-- Floating product cards (desktop): parallax with the pointer. Names and figures are demo data. --}}
        <div aria-hidden="true" class="pointer-events-none absolute right-4 bottom-0 hidden w-[24rem] md:block lg:right-8">
            <div data-lp-tile data-lp-depth="1.4" class="lp-hero-card absolute -top-[13.5rem] right-0 flex w-80 items-center gap-3.5 p-3.5">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-lg bg-gym-orange text-gym-white"><x-ui.icon name="qr" class="size-6" /></span>
                <span class="min-w-0 text-sm"><strong class="block text-base text-gym-white">Rohan M. checked in</strong><span class="text-gym-muted">6:42 AM · 21-day streak</span></span>
                <x-ui.icon name="fire" class="ml-auto size-6 shrink-0 text-gym-orange" />
            </div>
            <div data-lp-tile data-lp-depth="0.9" class="lp-hero-card absolute -top-[6rem] right-0 flex w-72 items-center gap-3.5 p-3.5 lg:-right-2">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-gym-orange/15 text-gym-orange"><x-ui.icon name="receipt" class="size-6" /></span>
                <span class="min-w-0 text-sm"><strong class="block text-base text-gym-orange">₹1,500 received</strong><span class="text-gym-light/80">UPI · receipt sent</span></span>
                <x-ui.icon name="arrow-right" class="ml-auto size-4 shrink-0 text-gym-muted" />
            </div>
        </div>

        <div class="relative mt-7 grid gap-8 md:max-w-[52%] lg:mt-9">
            <p class="max-w-xl text-lg leading-relaxed font-medium text-gym-light/90 sm:text-xl">
                Members, memberships, fees, QR check-ins, trainers, diet &amp; workout plans and an AI Coach — one panel for your front desk, one app for your members.
            </p>
            <div class="flex flex-wrap items-center gap-x-7 gap-y-4">
                <a href="{{ $ctaUrl }}" class="lp-btn lp-btn-solid rounded-full px-7 py-3.5 text-base">{{ $registrationsOpen ? 'Get Started Free' : $ctaLabel }} <x-ui.icon name="arrow-right" class="size-5" /></a>
                <a href="#day" class="group inline-flex items-center gap-3 text-base font-semibold text-gym-white">
                    <span class="lp-play flex size-12 items-center justify-center rounded-full border-2 border-gym-light/80">
                        <svg viewBox="0 0 24 24" class="ml-0.5 size-4" fill="currentColor" aria-hidden="true"><path d="M7 4.5v15a1 1 0 0 0 1.53.85l12-7.5a1 1 0 0 0 0-1.7l-12-7.5A1 1 0 0 0 7 4.5Z" /></svg>
                    </span>
                    Watch Demo
                </a>
            </div>
        </div>
    </div>

    {{-- Feature strip --}}
    <div class="relative mx-auto mt-12 max-w-7xl px-4 pb-10 sm:px-6 lg:mt-16 lg:px-8 lg:pb-14">
        <ul class="grid grid-cols-2 overflow-hidden rounded-2xl border border-gym-border bg-gym-charcoal/85 backdrop-blur-md sm:grid-cols-3 lg:grid-cols-5">
            @foreach ($heroFeatures as $feature)
                <li @class([
                    'relative flex items-center gap-3 px-5 py-4 sm:py-5 lg:justify-center',
                    'col-span-2 sm:col-span-1' => $loop->last,
                    'lg:before:absolute lg:before:inset-y-4 lg:before:left-0 lg:before:w-px lg:before:bg-gym-orange/50' => ! $loop->first,
                ])>
                    <x-ui.icon :name="$feature['icon']" class="size-7 shrink-0 text-gym-orange" />
                    <span class="text-sm font-semibold text-gym-light sm:text-base">{{ $feature['label'] }}</span>
                </li>
            @endforeach
        </ul>
    </div>
</section>
