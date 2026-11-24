{{-- Hero: headline, CTAs and a CSS-3D showcase that rotates through three product screens (dashboard, AI body
     transformation, attendance streaks) with matching floating cards. Figures in the mockups are illustrative demo data. --}}
@php
    // [tab icon, tab label, mock URL, headline line 1, headline line 2, sub-copy] — copy changes with the screen shown.
    $heroSlides = [
        ['chart', 'Dashboard', 'gymai.app/dashboard', 'Run Your Gym Smarter.', 'Grow Your Fitness Business Faster.',
            'All-in-one gym management software designed to simplify memberships, payments, attendance, staff management and business growth.'],
        ['sparkles', 'AI Transform', 'gymai.app/member/ai-transform', 'Show Members Their Goal Body.', 'Before They Even Start.',
            'Members see an AI preview of their goal physique, follow a plan made for them and track progress week by week, so they stay motivated.'],
        ['fire', 'Streaks', 'gymai.app/member/streak', 'Turn Check-ins Into Habits.', 'Streaks That Bring Members Back.',
            'Every QR check-in grows a member\'s streak. Weekly goals and badges keep them showing up and renewing, month after month.'],
    ];
@endphp
<section id="home" data-lp-section data-lp-spotlight class="lp-noise lp-spotlight relative overflow-hidden pt-28 pb-10 sm:pt-32 lg:pt-36 lg:pb-14">
    {{-- Ambient background: red glow, grid, slow ring and rising particles. --}}
    <div aria-hidden="true" class="pointer-events-none absolute inset-0">
        <div class="lp-glow absolute -top-48 left-1/2 h-[36rem] w-[60rem] -translate-x-1/2 rounded-full bg-brand-600/25 blur-[120px]"></div>
        <div class="absolute inset-0 bg-[linear-gradient(rgb(255_255_255/0.03)_1px,transparent_1px),linear-gradient(90deg,rgb(255_255_255/0.03)_1px,transparent_1px)] bg-[size:56px_56px] [mask-image:radial-gradient(ellipse_at_top,black_30%,transparent_75%)]"></div>
        <div class="lp-spin-slow absolute top-24 -right-40 size-[34rem] rounded-full border border-dashed border-brand-600/15"></div>
        <div class="lp-spin-slow absolute -bottom-56 -left-40 size-[30rem] rounded-full border border-white/5 [animation-direction:reverse]"></div>
        @foreach ([[6, 14, 3], [14, 19, 2], [23, 12, 4], [37, 22, 2], [48, 16, 3], [58, 25, 2], [67, 13, 3], [76, 20, 2], [85, 17, 4], [93, 23, 2]] as [$left, $duration, $size])
            <span class="lp-particle" style="left: {{ $left }}%; width: {{ $size }}px; height: {{ $size }}px; animation-duration: {{ $duration }}s; animation-delay: -{{ $duration * 0.6 }}s"></span>
        @endforeach
    </div>

    <div class="relative mx-auto grid max-w-7xl items-center gap-16 px-4 sm:px-6 lg:grid-cols-[1.05fr_1fr] lg:px-8">
        <div class="text-center lg:text-left">
            <a href="#members" class="lp-reveal group mx-auto inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.04] py-1 pr-3 pl-1 text-xs font-medium text-zinc-300 backdrop-blur transition hover:border-brand-500/50 hover:bg-brand-600/10 lg:mx-0">
                <span class="flex items-center gap-1 rounded-full bg-gradient-to-r from-brand-600 to-orange-500 px-2 py-0.5 text-[10px] font-bold tracking-wider text-white uppercase shadow-[0_0_16px_-2px_rgb(238_68_68/0.8)]">
                    <x-ui.icon name="sparkles" class="size-3" /> New
                </span>
                AI Body Transformation for your members
                <x-ui.icon name="arrow-right" class="size-3.5 text-brand-400 transition group-hover:translate-x-0.5" />
            </a>
            {{-- One copy block per showcase slide, stacked in the same grid cell; landing.js swaps them with the screens. --}}
            <h1 class="lp-copy-stage mt-6 text-4xl leading-[1.05] font-bold tracking-tight sm:text-5xl lg:text-6xl xl:text-[4.25rem]">
                @foreach ($heroSlides as $i => $slide)
                    <span data-hero-copy class="lp-hero-copy {{ $i === 0 ? 'is-active' : '' }}" @if ($i > 0) aria-hidden="true" @endif>
                        <span class="lp-copy-line" style="--i: 0"><span @if ($i === 0) data-split @endif class="block">{{ $slide[3] }}</span></span>
                        <span class="lp-copy-line" style="--i: 1"><span @if ($i === 0) data-lp-hero-shine @endif class="lp-text-shine block">{{ $slide[4] }}</span></span>
                    </span>
                @endforeach
            </h1>
            <p class="lp-reveal lp-copy-stage mx-auto mt-6 max-w-xl text-base leading-relaxed text-zinc-400 sm:text-lg lg:mx-0" style="--d: 160ms">
                @foreach ($heroSlides as $i => $slide)
                    <span data-hero-copy class="lp-hero-copy {{ $i === 0 ? 'is-active' : '' }}" @if ($i > 0) aria-hidden="true" @endif>
                        <span class="lp-copy-line" style="--i: 2"><span class="block">{{ $slide[5] }}</span></span>
                    </span>
                @endforeach
            </p>
            <div class="lp-reveal mt-9 flex flex-col justify-center gap-3 sm:flex-row lg:justify-start" style="--d: 240ms">
                <a href="{{ $ctaUrl }}" data-magnetic class="btn-primary lp-shine-btn group px-7 py-3.5 text-base">
                    {{ $ctaLabel }}
                    <x-ui.icon name="arrow-right" class="size-4 transition group-hover:translate-x-1" />
                </a>
                <a href="#how-it-works" data-magnetic class="btn group border border-ink-700 bg-white/[0.03] py-3.5 pr-7 pl-3.5 text-base text-zinc-200 backdrop-blur hover:border-brand-600/50 hover:bg-white/[0.06]">
                    <span class="relative flex size-8 items-center justify-center rounded-full bg-white/10 transition group-hover:bg-brand-600">
                        <span class="absolute inset-0 animate-ping rounded-full bg-brand-500/30 [animation-duration:2.4s]"></span>
                        <svg viewBox="0 0 24 24" class="relative size-3.5 translate-x-px fill-current" aria-hidden="true"><path d="M7 4.5v15l13-7.5z" /></svg>
                    </span>
                    See how it works
                </a>
            </div>
            <ul class="lp-reveal mt-6 flex flex-wrap justify-center gap-x-5 gap-y-2 text-sm text-zinc-400 lg:justify-start" style="--d: 280ms">
                @foreach (['No setup fee', 'All modules included', 'Member app included'] as $point)
                    <li class="flex items-center gap-1.5"><span class="flex size-4 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-400"><x-ui.icon name="check" class="size-3" /></span>{{ $point }}</li>
                @endforeach
            </ul>
            <dl class="lp-reveal mt-10 grid grid-cols-3 gap-2.5 text-left sm:gap-3" style="--d: 340ms">
                @foreach ([['cash', 'Per active member', $priceLabel, '/mo', null], ['bolt', 'Setup fee', '₹', null, 0], ['sparkles', 'Modules included', null, '+', 14]] as [$icon, $label, $prefix, $suffix, $count])
                    <div class="lp-stat group relative overflow-hidden rounded-2xl border border-white/[0.07] bg-gradient-to-b from-white/[0.06] to-white/[0.01] p-3 backdrop-blur transition duration-300 hover:-translate-y-1 hover:border-brand-500/40 sm:p-4">
                        <span aria-hidden="true" class="absolute -top-8 -right-8 size-20 rounded-full bg-brand-600/0 blur-2xl transition duration-500 group-hover:bg-brand-600/40"></span>
                        <span class="flex size-7 items-center justify-center rounded-lg bg-brand-600/15 text-brand-400"><x-ui.icon :name="$icon" class="size-4" /></span>
                        <dt class="mt-2.5 text-[11px] text-zinc-500 sm:text-xs">{{ $label }}</dt>
                        <dd class="mt-0.5 text-xl font-bold sm:text-2xl">{{ $prefix }}@if (! is_null($count))<span data-count="{{ $count }}">{{ $count }}</span>@endif<span class="text-sm font-medium text-zinc-500">{{ $suffix }}</span></dd>
                    </div>
                @endforeach
            </dl>
        </div>

        <div data-lp-hero-slider class="relative mx-auto w-full max-w-xl lg:max-w-none">
            {{-- 3D product showcase --}}
            <div data-lp-tilt data-lp-hero-mockup class="relative" aria-hidden="true">
                <div class="absolute -inset-10 rounded-[3rem] bg-brand-600/20 blur-3xl"></div>
                {{-- Stage: light beam from above, an orbit ring and a glowing perspective floor. --}}
                <div class="lp-beam pointer-events-none absolute -top-40 left-1/2 h-72 w-[130%] -translate-x-1/2"></div>
                <div class="pointer-events-none absolute top-1/2 left-1/2 size-[135%] -translate-x-1/2 -translate-y-1/2 [perspective:900px]">
                    <div class="lp-orbit absolute inset-0 rounded-full border border-brand-500/20">
                        <span class="absolute top-1/2 -left-1.5 size-3 -translate-y-1/2 rounded-full bg-brand-400 shadow-[0_0_18px_4px_rgb(238_68_68/0.8)]"></span>
                        <span class="absolute top-1/2 -right-1 size-2 -translate-y-1/2 rounded-full bg-orange-300 shadow-[0_0_14px_3px_rgb(251_146_60/0.7)]"></span>
                    </div>
                </div>
                <div class="lp-floor pointer-events-none absolute -bottom-28 left-1/2 h-56 w-[140%] -translate-x-1/2"></div>
                <div class="pointer-events-none absolute -bottom-12 left-1/2 h-16 w-3/4 -translate-x-1/2 rounded-[100%] bg-brand-600/40 blur-2xl"></div>
                <div class="lp-tilt relative">
                    <div class="lp-hero-stage">
                        {{-- Slide 1: owner dashboard --}}
                        <div data-hero-slide class="lp-hero-slide is-active">
                            <div class="h-full overflow-hidden rounded-2xl border border-white/10 bg-ink-900 shadow-[0_40px_120px_-30px_rgb(238_68_68/0.45)]">
                                @include('landing-v1.partials.hero-chrome', ['url' => $heroSlides[0][2]])
                                <div class="flex">
                                    <div class="hidden w-32 shrink-0 space-y-1 border-r border-ink-700 p-3 sm:block">
                                        <div class="mb-3 flex items-center gap-1.5"><span class="size-5 rounded-md bg-brand-600"></span><span class="h-2 w-12 rounded bg-white/20"></span></div>
                                        @foreach (['Dashboard', 'Members', 'Attendance', 'Payments', 'Plans', 'Reports'] as $i => $item)
                                            <div class="rounded-md px-2 py-1.5 text-[10px] {{ $i === 0 ? 'bg-brand-600/15 text-white' : 'text-zinc-500' }}">{{ $item }}</div>
                                        @endforeach
                                    </div>
                                    <div class="min-w-0 flex-1 space-y-3 p-4">
                                        <div>
                                            <p class="text-xs font-semibold">Good afternoon, Arjun 👋</p>
                                            <p class="text-[10px] text-zinc-500">Here's how your gym is doing today</p>
                                        </div>
                                        <div class="grid grid-cols-3 gap-2">
                                            @foreach ([['Members', '248', 'text-sky-400'], ['Revenue', '₹1.8L', 'text-emerald-400'], ['Check-ins', '87', 'text-brand-400']] as $i => [$label, $value, $tone])
                                                <div class="lp-pop rounded-lg border border-ink-700 bg-ink-800 p-2.5" style="--d: {{ 150 + $i * 90 }}ms">
                                                    <p class="text-[9px] tracking-wide text-zinc-500 uppercase">{{ $label }}</p>
                                                    <p class="mt-1 text-base font-bold {{ $tone }}">{{ $value }}</p>
                                                </div>
                                            @endforeach
                                        </div>
                                        <div class="rounded-lg border border-ink-700 bg-ink-800 p-3">
                                            <div class="flex items-center justify-between"><p class="text-[10px] font-semibold">Revenue overview</p><p class="text-[9px] text-emerald-400">+18.4%</p></div>
                                            <svg viewBox="0 0 300 90" class="mt-2 h-20 w-full" preserveAspectRatio="none">
                                                <defs><linearGradient id="hero-fill" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#ee4444" stop-opacity=".35" /><stop offset="1" stop-color="#ee4444" stop-opacity="0" /></linearGradient></defs>
                                                <path d="M0 75 L30 68 L60 72 L90 55 L120 60 L150 42 L180 48 L210 30 L240 36 L270 18 L300 12 L300 90 L0 90 Z" fill="url(#hero-fill)" />
                                                <path class="lp-line" d="M0 75 L30 68 L60 72 L90 55 L120 60 L150 42 L180 48 L210 30 L240 36 L270 18 L300 12" fill="none" stroke="#ee4444" stroke-width="2.5" stroke-linejoin="round" />
                                            </svg>
                                        </div>
                                        <div class="grid grid-cols-2 gap-2">
                                            <div class="rounded-lg border border-ink-700 bg-ink-800 p-3">
                                                <p class="text-[10px] font-semibold">Today's attendance</p>
                                                <div class="mt-2 flex items-end gap-1">
                                                    @foreach ([40, 65, 50, 80, 55, 90, 70] as $i => $h)
                                                        <span class="lp-bar w-full rounded-sm bg-brand-600/80" style="height: {{ $h * 0.4 }}px; --d: {{ 400 + $i * 70 }}ms"></span>
                                                    @endforeach
                                                </div>
                                            </div>
                                            <div class="rounded-lg border border-ink-700 bg-ink-800 p-3">
                                                <p class="text-[10px] font-semibold">Expiring soon</p>
                                                <div class="mt-2 space-y-1.5">
                                                    @foreach (['Riya B.', 'Kabir S.', 'Isha J.'] as $i => $name)
                                                        <div class="lp-pop flex items-center justify-between text-[9px]" style="--d: {{ 500 + $i * 90 }}ms"><span class="text-zinc-400">{{ $name }}</span><span class="rounded-full bg-amber-500/15 px-1.5 text-amber-400">3 days</span></div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Slide 2: AI body transformation --}}
                        <div data-hero-slide class="lp-hero-slide">
                            <div class="flex h-full flex-col overflow-hidden rounded-2xl border border-white/10 bg-ink-900 shadow-[0_40px_120px_-30px_rgb(238_68_68/0.45)]">
                                @include('landing-v1.partials.hero-chrome', ['url' => $heroSlides[1][2]])
                                <div class="flex flex-1 flex-col gap-3 p-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-xs font-semibold">AI Body Transformation</p>
                                            <p class="text-[10px] text-zinc-500">Personal goal preview for Rahul M.</p>
                                        </div>
                                        <span class="flex items-center gap-1.5 rounded-full border border-brand-500/30 bg-brand-600/15 px-2 py-0.5 text-[9px] font-semibold text-brand-300">
                                            <span class="size-1.5 animate-pulse rounded-full bg-brand-400"></span> AI analysing
                                        </span>
                                    </div>
                                    <div class="grid flex-1 grid-cols-[1.1fr_1fr] gap-3">
                                        {{-- Now vs goal silhouettes with a scanning beam --}}
                                        <div class="relative overflow-hidden rounded-xl border border-ink-700 bg-[radial-gradient(circle_at_50%_30%,rgb(238_68_68/0.18),transparent_65%)] bg-ink-800 p-3">
                                            <div class="absolute inset-0 bg-[linear-gradient(rgb(255_255_255/0.04)_1px,transparent_1px),linear-gradient(90deg,rgb(255_255_255/0.04)_1px,transparent_1px)] bg-[size:14px_14px]"></div>
                                            <span class="lp-scan absolute inset-x-0 top-0 h-10 bg-gradient-to-b from-transparent via-brand-500/30 to-transparent"></span>
                                            <div class="relative flex h-full items-end justify-around gap-2">
                                                <figure class="flex flex-col items-center gap-1">
                                                    <svg viewBox="0 0 60 115" class="h-36 w-auto" fill="none">
                                                        <circle cx="30" cy="13" r="8" fill="rgb(255 255 255 / 0.12)" stroke="rgb(255 255 255 / 0.35)" />
                                                        <path d="M17 26 Q30 22 43 26 L47 52 Q48 63 44 72 L40 74 L38 110 L33 110 L31 78 L29 78 L27 110 L22 110 L20 74 L16 72 Q12 63 13 52 Z" fill="rgb(255 255 255 / 0.10)" stroke="rgb(255 255 255 / 0.35)" />
                                                        <path d="M17 28 L10 54 L12 68 M43 28 L50 54 L48 68" stroke="rgb(255 255 255 / 0.35)" stroke-width="4" stroke-linecap="round" />
                                                    </svg>
                                                    <figcaption class="text-[9px] text-zinc-400">Now</figcaption>
                                                </figure>
                                                <span class="mb-12 flex flex-col items-center text-[8px] font-semibold text-brand-300">12 wks<x-ui.icon name="arrow-right" class="size-3.5" /></span>
                                                <figure class="flex flex-col items-center gap-1">
                                                    <svg viewBox="0 0 60 115" class="h-36 w-auto drop-shadow-[0_0_14px_rgb(238_68_68/0.7)]" fill="none">
                                                        <defs><linearGradient id="hero-goal" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#f87171" /><stop offset="1" stop-color="#b91c1c" /></linearGradient></defs>
                                                        <circle cx="30" cy="13" r="8" fill="url(#hero-goal)" />
                                                        <path d="M12 26 Q30 20 48 26 L45 50 Q41 62 41 72 L39 74 L37 110 L32 110 L31 78 L29 78 L28 110 L23 110 L21 74 L19 72 Q19 62 15 50 Z" fill="url(#hero-goal)" />
                                                        <path d="M12 28 L6 52 L9 68 M48 28 L54 52 L51 68" stroke="url(#hero-goal)" stroke-width="5" stroke-linecap="round" />
                                                    </svg>
                                                    <figcaption class="text-[9px] font-semibold text-brand-300">Goal</figcaption>
                                                </figure>
                                            </div>
                                        </div>
                                        <div class="flex flex-col gap-2">
                                            @foreach ([['Body fat', '24% → 16%', 'w-[67%]'], ['Muscle mass', '+4.2 kg', 'w-[58%]'], ['Weight', '82 → 74 kg', 'w-[72%]']] as $i => [$label, $value, $width])
                                                <div class="lp-pop rounded-lg border border-ink-700 bg-ink-800 p-2" style="--d: {{ 200 + $i * 120 }}ms">
                                                    <div class="flex items-center justify-between text-[9px]"><span class="text-zinc-400">{{ $label }}</span><span class="font-semibold">{{ $value }}</span></div>
                                                    <div class="mt-1.5 h-1 overflow-hidden rounded-full bg-white/10"><div class="lp-hbar h-full {{ $width }} rounded-full bg-gradient-to-r from-brand-600 to-orange-400" style="--d: {{ 350 + $i * 120 }}ms"></div></div>
                                                </div>
                                            @endforeach
                                            <div class="lp-pop mt-auto flex flex-wrap gap-1" style="--d: 700ms">
                                                @foreach (['AI diet plan', 'Workout v2', 'Weekly check-in'] as $chip)
                                                    <span class="rounded-full bg-brand-600/15 px-2 py-0.5 text-[8px] font-medium text-brand-300">{{ $chip }}</span>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Slide 3: attendance streaks --}}
                        <div data-hero-slide class="lp-hero-slide">
                            <div class="flex h-full flex-col overflow-hidden rounded-2xl border border-white/10 bg-ink-900 shadow-[0_40px_120px_-30px_rgb(238_68_68/0.45)]">
                                @include('landing-v1.partials.hero-chrome', ['url' => $heroSlides[2][2]])
                                <div class="flex flex-1 flex-col gap-3 p-4">
                                    <div class="grid grid-cols-[auto_1fr] gap-3">
                                        <div class="relative flex w-28 flex-col items-center justify-center overflow-hidden rounded-xl border border-orange-500/30 bg-gradient-to-b from-orange-500/20 via-brand-600/10 to-ink-800 p-3">
                                            <span class="lp-flame flex size-11 items-center justify-center rounded-full bg-gradient-to-br from-amber-400 via-orange-500 to-brand-600 text-white shadow-[0_0_30px_-2px_rgb(249_115_22/0.9)]">
                                                <x-ui.icon name="fire" class="size-6" />
                                            </span>
                                            <p class="mt-2 text-2xl leading-none font-extrabold">21</p>
                                            <p class="text-[9px] text-zinc-400">day streak</p>
                                        </div>
                                        <div class="flex flex-col justify-between gap-2 rounded-xl border border-ink-700 bg-ink-800 p-3">
                                            <div class="flex items-center justify-between">
                                                <p class="text-[10px] font-semibold">This week</p>
                                                <p class="text-[9px] text-zinc-500">Best: 34 days</p>
                                            </div>
                                            <div class="grid grid-cols-7 gap-1">
                                                @foreach (['M', 'T', 'W', 'T', 'F', 'S', 'S'] as $i => $day)
                                                    <div class="flex flex-col items-center gap-1">
                                                        @if ($i < 5)
                                                            <span class="lp-pop flex size-6 items-center justify-center rounded-full bg-gradient-to-br from-orange-400 to-brand-600 text-white" style="--d: {{ 150 + $i * 90 }}ms"><x-ui.icon name="check" class="size-3.5" /></span>
                                                        @elseif ($i === 5)
                                                            <span class="lp-pop flex size-6 items-center justify-center rounded-full border-2 border-dashed border-orange-400 text-orange-300 lp-today" style="--d: 620ms"><span class="size-1.5 rounded-full bg-orange-400"></span></span>
                                                        @else
                                                            <span class="size-6 rounded-full border border-ink-700"></span>
                                                        @endif
                                                        <span class="text-[8px] text-zinc-500">{{ $day }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                    <div class="rounded-xl border border-ink-700 bg-ink-800 p-3">
                                        <div class="flex items-center justify-between"><p class="text-[10px] font-semibold">November check-ins</p><p class="text-[9px] text-emerald-400">22 / 24 days</p></div>
                                        <div class="mt-2 grid grid-cols-12 gap-1">
                                            @foreach ([3, 3, 2, 3, 0, 3, 3, 1, 3, 3, 3, 2, 3, 0, 3, 3, 3, 3, 2, 3, 3, 3, 3, 1] as $i => $level)
                                                <span class="lp-pop h-3.5 rounded-[3px] {{ ['bg-white/5', 'bg-brand-600/30', 'bg-brand-600/60', 'bg-brand-500'][$level] }}" style="--d: {{ 250 + $i * 25 }}ms"></span>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 rounded-xl border border-ink-700 bg-ink-800 p-3">
                                        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-amber-400/15 text-amber-300"><x-ui.icon name="trophy" class="size-4" /></span>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center justify-between text-[9px]"><span class="font-semibold">Next badge · 30-day Warrior</span><span class="text-zinc-400">21/30</span></div>
                                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-white/10"><div class="lp-hbar h-full w-[70%] rounded-full bg-gradient-to-r from-amber-400 to-orange-500" style="--d: 700ms"></div></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Floating glass cards per slide: the outer layer follows the pointer (data-depth), the inner one floats. --}}
                <div data-hero-floats class="lp-hero-floats is-active">
                    <div data-depth="0.9" class="absolute -top-6 -left-4 sm:-left-10">
                        <div class="lp-float w-44 rounded-xl border border-white/10 bg-ink-800/70 p-3 shadow-2xl backdrop-blur-xl">
                            <div class="flex items-center gap-2">
                                <span class="flex size-8 items-center justify-center rounded-lg bg-emerald-500/15 text-emerald-400"><x-ui.icon name="card" class="size-4" /></span>
                                <div><p class="text-[10px] text-zinc-500">Membership</p><p class="text-xs font-semibold">Quarterly · Active</p></div>
                            </div>
                            <div class="mt-2 h-1.5 rounded-full bg-ink-700"><div class="h-full w-2/3 rounded-full bg-emerald-400"></div></div>
                        </div>
                    </div>
                    <div data-depth="1.3" class="absolute top-1/3 -right-3 sm:-right-8">
                        <div class="lp-float-slow w-48 rounded-xl border border-white/10 bg-ink-800/70 p-3 shadow-2xl backdrop-blur-xl">
                            <div class="flex items-center gap-2.5">
                                <span class="flex size-9 items-center justify-center rounded-full bg-gradient-to-br from-brand-500 to-rose-700 text-xs font-bold">PS</span>
                                <div class="min-w-0"><p class="truncate text-xs font-semibold">Priya joined</p><p class="text-[10px] text-zinc-500">via QR registration</p></div>
                            </div>
                        </div>
                    </div>
                    <div data-depth="0.6" class="absolute -bottom-8 left-8">
                        <div class="lp-float flex items-center gap-2.5 rounded-xl border border-white/10 bg-ink-800/70 px-3 py-2.5 shadow-2xl backdrop-blur-xl [animation-delay:-2s]">
                            <span class="flex size-8 items-center justify-center rounded-lg bg-brand-600/20 text-brand-400"><x-ui.icon name="trending" class="size-4" /></span>
                            <div><p class="text-[10px] text-zinc-500">This month</p><p class="text-sm font-bold">₹1,84,500</p></div>
                        </div>
                    </div>
                    <div data-depth="1.6" class="absolute -top-10 right-10 hidden sm:block">
                        <div class="lp-float-slow flex items-center gap-2 rounded-full border border-white/10 bg-ink-800/70 py-1.5 pr-3 pl-1.5 shadow-2xl backdrop-blur-xl [animation-delay:-5s]">
                            <span class="flex size-6 items-center justify-center rounded-full bg-brand-600 text-white"><x-ui.icon name="qr" class="size-3.5" /></span>
                            <p class="text-[11px] font-semibold">Checked in · 6:42 AM</p>
                        </div>
                    </div>
                </div>

                <div data-hero-floats class="lp-hero-floats">
                    <div data-depth="0.9" class="absolute -top-6 -left-4 sm:-left-10">
                        <div class="lp-float w-48 rounded-xl border border-white/10 bg-ink-800/70 p-3 shadow-2xl backdrop-blur-xl">
                            <div class="flex items-center gap-2">
                                <span class="flex size-8 items-center justify-center rounded-lg bg-brand-600/20 text-brand-400"><x-ui.icon name="sparkles" class="size-4" /></span>
                                <div><p class="text-[10px] text-zinc-500">AI prediction</p><p class="text-xs font-semibold">−8% body fat · 12 wks</p></div>
                            </div>
                        </div>
                    </div>
                    <div data-depth="1.3" class="absolute top-1/3 -right-3 sm:-right-8">
                        <div class="lp-float-slow flex items-center gap-2 rounded-full border border-emerald-400/30 bg-ink-800/70 py-1.5 pr-3 pl-1.5 shadow-2xl backdrop-blur-xl">
                            <span class="flex size-6 items-center justify-center rounded-full bg-emerald-500 text-white"><x-ui.icon name="check" class="size-3.5" /></span>
                            <p class="text-[11px] font-semibold">AI plan ready</p>
                        </div>
                    </div>
                    <div data-depth="0.6" class="absolute -bottom-8 left-8">
                        <div class="lp-float flex items-center gap-2.5 rounded-xl border border-white/10 bg-ink-800/70 px-3 py-2.5 shadow-2xl backdrop-blur-xl [animation-delay:-2s]">
                            <span class="flex size-8 items-center justify-center rounded-lg bg-orange-500/15 text-orange-400"><x-ui.icon name="trending" class="size-4" /></span>
                            <div><p class="text-[10px] text-zinc-500">Progress to goal</p><p class="text-sm font-bold">72%</p></div>
                        </div>
                    </div>
                </div>

                <div data-hero-floats class="lp-hero-floats">
                    <div data-depth="0.9" class="absolute -top-6 -left-4 sm:-left-10">
                        <div class="lp-float flex items-center gap-2.5 rounded-xl border border-amber-400/30 bg-ink-800/70 p-3 shadow-2xl backdrop-blur-xl">
                            <span class="flex size-8 items-center justify-center rounded-lg bg-amber-400/15 text-amber-300"><x-ui.icon name="trophy" class="size-4" /></span>
                            <div><p class="text-[10px] text-zinc-500">Badge unlocked</p><p class="text-xs font-semibold">21-day Streak</p></div>
                        </div>
                    </div>
                    <div data-depth="1.3" class="absolute top-1/3 -right-3 sm:-right-8">
                        <div class="lp-float-slow flex items-center gap-2 rounded-full border border-white/10 bg-ink-800/70 py-1.5 pr-3 pl-1.5 shadow-2xl backdrop-blur-xl">
                            <span class="flex size-6 items-center justify-center rounded-full bg-gradient-to-br from-orange-400 to-brand-600 text-white"><x-ui.icon name="fire" class="size-3.5" /></span>
                            <p class="text-[11px] font-semibold">Streak +1 · 6:42 AM</p>
                        </div>
                    </div>
                    <div data-depth="0.6" class="absolute -bottom-8 left-8">
                        <div class="lp-float flex items-center gap-2.5 rounded-xl border border-white/10 bg-ink-800/70 px-3 py-2.5 shadow-2xl backdrop-blur-xl [animation-delay:-2s]">
                            <span class="flex size-8 items-center justify-center rounded-lg bg-brand-600/20 text-brand-400"><x-ui.icon name="users" class="size-4" /></span>
                            <div><p class="text-[10px] text-zinc-500">Members on a streak</p><p class="text-sm font-bold">64%</p></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Slide switcher: fills while a slide is shown; hover pauses, click jumps. --}}
            <div class="relative mt-14 flex justify-center" role="group" aria-label="Product preview">
                <div data-hero-tabs class="relative flex gap-1 rounded-2xl border border-white/10 bg-ink-900/70 p-1 shadow-xl backdrop-blur-xl">
                    <span data-hero-indicator class="lp-hero-indicator" aria-hidden="true"></span>
                    @foreach ($heroSlides as $i => [$icon, $label])
                        <button type="button" data-hero-tab class="lp-hero-tab group relative flex items-center gap-1.5 overflow-hidden rounded-xl px-3 py-2 text-xs font-medium text-zinc-400 transition hover:text-white {{ $i === 0 ? 'is-active' : '' }}" aria-pressed="{{ $i === 0 ? 'true' : 'false' }}">
                            <x-ui.icon :name="$icon" class="size-4 transition group-hover:scale-110" />
                            {{ $label }}
                            <span data-hero-progress class="lp-hero-progress absolute inset-x-2 bottom-0.5 h-0.5 origin-left rounded-full bg-brand-500"></span>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
