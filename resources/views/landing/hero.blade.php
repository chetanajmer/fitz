{{-- Hero: slamming headline, live product "tiles" (illustrative demo data) and a heartbeat line. --}}
<section id="home" data-lp-section class="relative overflow-hidden pt-24 sm:pt-28 lg:pt-32">
    <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <p class="lp-mono flex items-center gap-2.5">
            <span class="relative flex size-2.5"><span class="absolute inset-0 animate-ping rounded-full bg-clay"></span><span class="relative size-2.5 rounded-full bg-clay"></span></span>
            Gym management software · made for Indian gyms
        </p>

        <h1 data-lp-hero-title class="lp-hero-title lp-display mt-4 sm:mt-6">
            <span class="block">Lift <br class="sm:hidden">your</span>
            <span class="block"><span class="lp-hl">gym.</span></span>
        </h1>

        {{-- Rotating price sticker --}}
        <div data-lp-depth="0.6" class="absolute top-12 right-4 size-28 sm:top-0 sm:size-36 lg:right-8 lg:size-44">
            <svg viewBox="0 0 200 200" class="lp-badge absolute inset-0 size-full" aria-hidden="true">
                <defs><path id="lp-badge-path" d="M100 100 m-78 0 a78 78 0 1 1 156 0 a78 78 0 1 1 -156 0" /></defs>
                <circle cx="100" cy="100" r="98" fill="var(--color-forest)" />
                <text class="lp-mono" fill="var(--color-volt)" font-size="15" letter-spacing="4"><textPath href="#lp-badge-path">PER ACTIVE MEMBER · NO SETUP FEE ·</textPath></text>
            </svg>
            <p class="absolute inset-0 flex flex-col items-center justify-center text-chalk">
                <span class="lp-display text-3xl text-volt sm:text-4xl lg:text-5xl">{{ $priceLabel }}</span>
                <span class="lp-mono mt-1 text-[0.6rem]">/ month</span>
            </p>
        </div>

        {{-- Floating product tiles (desktop): parallax with the pointer. Names and figures are demo data. --}}
        <div aria-hidden="true" class="pointer-events-none absolute right-6 bottom-[-2rem] hidden w-[26rem] md:block lg:right-8 xl:w-[30rem]">
            <div data-lp-tile data-lp-depth="1.4" class="lp-tile absolute -top-[19rem] right-0 flex w-72 items-center gap-3 border-2 border-forest bg-chalk p-3.5">
                <span class="flex size-10 shrink-0 items-center justify-center bg-volt"><x-ui.icon name="qr" class="size-5" /></span>
                <span class="min-w-0 text-sm"><strong class="block">Rohan M. checked in</strong><span class="text-forest/70">6:42 AM · 21-day streak</span></span>
                <x-ui.icon name="fire" class="ml-auto size-5 shrink-0 text-clay" />
            </div>
            <div data-lp-tile data-lp-depth="0.9" class="lp-tile absolute -top-[13.5rem] right-40 flex w-64 items-center gap-3 border-2 border-forest bg-forest p-3.5 text-chalk xl:right-56">
                <span class="flex size-10 shrink-0 items-center justify-center bg-clay text-forest"><x-ui.icon name="receipt" class="size-5" /></span>
                <span class="text-sm"><strong class="block text-volt">₹1,500 received</strong><span class="text-chalk/70">UPI · receipt sent</span></span>
            </div>
            <div data-lp-tile data-lp-depth="1.8" class="lp-tile absolute -top-[8rem] right-4 w-72 border-2 border-forest bg-volt p-4">
                <span class="lp-mono flex items-center gap-2"><x-ui.icon name="sparkles" class="size-4" /> AI Coach · progress report</span>
                <span class="mt-2 flex items-end justify-between gap-3">
                    <span class="lp-display text-4xl">−26 kg</span>
                    <span class="flex h-10 items-end gap-1">
                        @foreach ([30, 45, 40, 62, 70, 88] as $height)
                            <span class="w-2.5 bg-forest" style="height: {{ $height }}%"></span>
                        @endforeach
                    </span>
                </span>
                <span class="mt-1 block text-xs font-medium">36 weeks · photos compared</span>
            </div>
        </div>

        <div class="relative mt-8 grid gap-8 md:max-w-[calc(100%-27rem)] lg:mt-10">
            <p class="max-w-xl text-lg leading-relaxed font-medium sm:text-xl">
                Members, memberships, fees, QR check-ins, trainers, diet &amp; workout plans and an AI Coach — one panel for your front desk, one app for your members.
            </p>
            <div class="flex flex-wrap items-center gap-4">
                <a href="{{ $ctaUrl }}" class="lp-btn lp-btn-solid">{{ $ctaLabel }} <x-ui.icon name="arrow-right" class="size-4" /></a>
                <a href="{{ route('member.join') }}" class="lp-btn lp-btn-ghost">Register as Member</a>
                <a href="{{ route('gyms.browse') }}" class="px-1 text-sm font-bold underline decoration-2 underline-offset-4 hover:text-clay">Browse gyms near you →</a>
            </div>
        </div>
    </div>

    {{-- Heartbeat line: landing.js sweeps it in from left to right on load, led by a glowing "pen". --}}
    <div class="relative mt-6" aria-hidden="true">
        <svg data-lp-ecg class="lp-ecg block h-28 w-full sm:h-36" viewBox="0 0 1440 140" preserveAspectRatio="none">
            <path pathLength="1" d="M0 92 H470 l26 -64 l30 104 l26 -86 l20 46 H820 l22 -36 l22 52 l16 -16 H1440" fill="none" stroke="var(--color-forest)" stroke-width="12" stroke-linejoin="round" />
            <path pathLength="1" d="M0 92 H470 l26 -64 l30 104 l26 -86 l20 46 H820 l22 -36 l22 52 l16 -16 H1440" fill="none" stroke="var(--color-volt)" stroke-width="6" stroke-linejoin="round" />
        </svg>
        <span data-lp-ecg-pen class="lp-ecg-pen pointer-events-none absolute top-0 left-0 size-4 rounded-full border-[3px] border-forest bg-volt opacity-0"></span>
    </div>
</section>
