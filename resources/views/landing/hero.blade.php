{{-- Hero: headline, CTAs and a CSS-3D dashboard mockup with floating cards. Figures in the mockup are illustrative demo data. --}}
<section id="home" data-lp-section class="lp-noise relative overflow-hidden pt-28 pb-10 sm:pt-32 lg:pt-36 lg:pb-14">
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
            <p class="lp-reveal mx-auto inline-flex items-center gap-2 rounded-full border border-brand-600/30 bg-brand-600/10 px-3.5 py-1.5 text-xs font-medium text-brand-400 lg:mx-0">
                <span class="size-1.5 animate-pulse rounded-full bg-brand-500"></span>
                No setup fee · All features included
            </p>
            <h1 class="mt-6 text-4xl leading-[1.05] font-bold tracking-tight sm:text-5xl lg:text-6xl xl:text-[4.25rem]">
                <span data-split class="block">Run Your Gym Smarter.</span>
                <span data-lp-hero-shine class="lp-text-shine block">Grow Your Fitness Business Faster.</span>
            </h1>
            <p class="lp-reveal mx-auto mt-6 max-w-xl text-base leading-relaxed text-zinc-400 sm:text-lg lg:mx-0" style="--d: 160ms">
                All-in-one gym management software designed to simplify memberships, payments, attendance, staff management and business growth.
            </p>
            <div class="lp-reveal mt-9 flex flex-col justify-center gap-3 sm:flex-row lg:justify-start" style="--d: 240ms">
                <a href="{{ $ctaUrl }}" data-magnetic class="btn-primary lp-shine-btn group px-7 py-3.5 text-base">
                    {{ $ctaLabel }}
                    <x-ui.icon name="arrow-right" class="size-4 transition group-hover:translate-x-1" />
                </a>
                <a href="#features" data-magnetic class="btn border border-ink-700 bg-white/[0.03] px-7 py-3.5 text-base text-zinc-200 backdrop-blur hover:border-brand-600/50 hover:bg-white/[0.06]">Explore Features</a>
            </div>
            <dl class="lp-reveal mt-12 grid grid-cols-3 gap-4 border-t border-ink-700 pt-8 text-left" style="--d: 320ms">
                <div>
                    <dt class="text-xs text-zinc-500">Per active member</dt>
                    <dd class="mt-1 text-2xl font-bold">{{ $priceLabel }}<span class="text-sm font-medium text-zinc-500">/mo</span></dd>
                </div>
                <div>
                    <dt class="text-xs text-zinc-500">Setup fee</dt>
                    <dd class="mt-1 text-2xl font-bold">₹<span data-count="0">0</span></dd>
                </div>
                <div>
                    <dt class="text-xs text-zinc-500">Modules included</dt>
                    <dd class="mt-1 text-2xl font-bold"><span data-count="14">14</span>+</dd>
                </div>
            </dl>
        </div>

        {{-- 3D dashboard mockup --}}
        <div data-lp-tilt data-lp-hero-mockup class="relative mx-auto w-full max-w-xl lg:max-w-none" aria-hidden="true">
            <div class="absolute -inset-10 rounded-[3rem] bg-brand-600/20 blur-3xl"></div>
            <div class="lp-tilt relative">
                <div class="overflow-hidden rounded-2xl border border-white/10 bg-ink-900 shadow-[0_40px_120px_-30px_rgb(238_68_68/0.45)]">
                    <div class="flex items-center gap-1.5 border-b border-ink-700 px-4 py-3">
                        <span class="size-2.5 rounded-full bg-red-500/80"></span><span class="size-2.5 rounded-full bg-amber-400/80"></span><span class="size-2.5 rounded-full bg-emerald-400/80"></span>
                        <span class="ml-3 rounded-md bg-ink-800 px-3 py-0.5 text-[10px] text-zinc-500">gymai.app/dashboard</span>
                    </div>
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
                                @foreach ([['Members', '248', 'text-sky-400'], ['Revenue', '₹1.8L', 'text-emerald-400'], ['Check-ins', '87', 'text-brand-400']] as [$label, $value, $tone])
                                    <div class="rounded-lg border border-ink-700 bg-ink-800 p-2.5">
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
                                        @foreach (['Riya B.', 'Kabir S.', 'Isha J.'] as $name)
                                            <div class="flex items-center justify-between text-[9px]"><span class="text-zinc-400">{{ $name }}</span><span class="rounded-full bg-amber-500/15 px-1.5 text-amber-400">3 days</span></div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Floating cards --}}
            {{-- Floating glass cards: the outer layer follows the pointer (data-depth), the inner one floats. --}}
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
    </div>
</section>
