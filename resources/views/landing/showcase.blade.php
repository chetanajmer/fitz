{{-- Product showcase: a mockup of the real gym dashboard. Numbers are illustrative demo data. --}}
<section id="showcase" data-lp-section class="lp-noise relative overflow-hidden py-14 sm:py-20">
    <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-brand-600/50 to-transparent"></div>
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center">
            <p class="lp-reveal text-sm font-semibold tracking-[0.2em] text-brand-500 uppercase">Dashboard</p>
            <h2 data-split class="lp-reveal mt-3 text-3xl font-bold tracking-tight sm:text-5xl" style="--d: 80ms">Your whole gym on one screen</h2>
            <p class="lp-reveal mt-5 text-zinc-400" style="--d: 160ms">Members, money and attendance update the moment something happens at your front desk.</p>
        </div>

        <div class="lp-reveal relative mt-10" style="--d: 200ms" aria-hidden="true">
            <div class="absolute -inset-x-10 -inset-y-6 rounded-[3rem] bg-gradient-to-b from-brand-600/15 to-transparent blur-2xl"></div>

            {{-- Floating member notifications (illustrative). --}}
            @foreach ([
                ['-top-6 -left-6', 'check', 'text-emerald-400 bg-emerald-500/15', 'Payment received', 'Kabir S. · ₹4,000 · UPI'],
                ['-bottom-6 right-12', 'clock', 'text-amber-400 bg-amber-500/15', 'Membership expiring', 'Riya B. · in 2 days'],
                ['-bottom-6 left-1/4', 'fire', 'text-brand-400 bg-brand-600/15', '7-day streak!', 'Aarav S. hit a milestone'],
            ] as [$position, $icon, $tone, $title, $text])
                <div data-lp-notification class="absolute {{ $position }} z-10 hidden lg:block">
                    <div class="flex items-center gap-3 rounded-2xl border border-white/10 bg-ink-800/80 py-2.5 pr-4 pl-2.5 shadow-2xl backdrop-blur-xl">
                        <span class="flex size-9 items-center justify-center rounded-xl {{ $tone }}"><x-ui.icon :name="$icon" class="size-5" /></span>
                        <span><span class="block text-xs font-semibold">{{ $title }}</span><span class="block text-[11px] text-zinc-500">{{ $text }}</span></span>
                    </div>
                </div>
            @endforeach

            <div data-lp-showcase class="relative overflow-hidden rounded-3xl border border-white/10 bg-ink-900 p-4 shadow-[0_50px_140px_-40px_rgb(238_68_68/0.4)] sm:p-6">
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    @foreach ([
                        ['users', 'Total Members', 248, '', '+12 this month', 'text-sky-400 bg-sky-500/10'],
                        ['card', 'Active Memberships', 214, '', '86% of members', 'text-emerald-400 bg-emerald-500/10'],
                        ['cash', 'Pending Fees', 22100, '₹', '9 members', 'text-amber-400 bg-amber-500/10'],
                        ['trending', 'Monthly Revenue', 184500, '₹', '+18.4% vs last month', 'text-brand-400 bg-brand-600/10'],
                    ] as [$icon, $label, $value, $prefix, $hint, $tone])
                        <div class="rounded-2xl border border-ink-700 bg-ink-800 p-4">
                            <div class="flex items-center gap-3">
                                <span class="flex size-9 items-center justify-center rounded-lg {{ $tone }}"><x-ui.icon :name="$icon" class="size-5" /></span>
                                <p class="text-[11px] font-medium tracking-wide text-zinc-500 uppercase">{{ $label }}</p>
                            </div>
                            <p class="mt-3 text-2xl font-bold sm:text-3xl">{{ $prefix }}<span data-count="{{ $value }}">{{ number_format($value) }}</span></p>
                            <p class="mt-1 text-xs text-zinc-500">{{ $hint }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="mt-3 grid gap-3 lg:grid-cols-3">
                    <div class="rounded-2xl border border-ink-700 bg-ink-800 p-5 lg:col-span-2">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold">Attendance analytics</p>
                            <p class="text-xs text-zinc-500">Last 14 days</p>
                        </div>
                        <div class="mt-5 flex h-40 items-end gap-1.5 sm:gap-2.5">
                            @foreach ([52, 61, 48, 70, 66, 80, 38, 58, 72, 64, 85, 78, 90, 42] as $i => $h)
                                <div class="flex flex-1 flex-col items-center gap-1">
                                    <span class="lp-bar w-full rounded-t-md bg-gradient-to-t from-brand-700 to-brand-500" style="height: {{ $h * 1.5 }}px; --d: {{ $i * 50 }}ms"></span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="rounded-2xl border border-ink-700 bg-ink-800 p-5">
                        <div class="flex items-center justify-between"><p class="text-sm font-semibold">QR attendance</p><span class="flex items-center gap-1 rounded-full bg-brand-600/15 px-2 py-0.5 text-[11px] text-brand-400"><span class="size-1.5 animate-pulse rounded-full bg-brand-500"></span> Live</span></div>
                        <div class="mt-4 flex items-center gap-5">
                            <svg viewBox="0 0 100 100" class="size-28 -rotate-90">
                                <circle cx="50" cy="50" r="40" fill="none" stroke="#2e2e33" stroke-width="10" />
                                <circle cx="50" cy="50" r="40" fill="none" stroke="#ee4444" stroke-width="10" stroke-linecap="round" stroke-dasharray="251" stroke-dashoffset="75" />
                            </svg>
                            <div>
                                <p class="text-3xl font-bold"><span data-count="87">87</span></p>
                                <p class="text-xs text-zinc-500">check-ins · 23 inside now</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <div class="rounded-2xl border border-ink-700 bg-ink-800 p-5 md:col-span-2 xl:col-span-1">
                        <div class="flex items-center justify-between"><p class="text-sm font-semibold">Revenue analytics</p><span class="text-xs text-emerald-400">+18.4%</span></div>
                        <svg viewBox="0 0 300 120" class="mt-4 h-36 w-full" preserveAspectRatio="none">
                            <defs><linearGradient id="showcase-rev" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#ee4444" stop-opacity=".35" /><stop offset="1" stop-color="#ee4444" stop-opacity="0" /></linearGradient></defs>
                            @foreach ([30, 60, 90] as $y)<line x1="0" x2="300" y1="{{ $y }}" y2="{{ $y }}" stroke="#2e2e33" stroke-dasharray="3 4" />@endforeach
                            <path d="M0 100 L40 92 L80 96 L120 74 L160 80 L200 52 L240 58 L270 30 L300 22 L300 120 L0 120 Z" fill="url(#showcase-rev)" />
                            <path class="lp-line" d="M0 100 L40 92 L80 96 L120 74 L160 80 L200 52 L240 58 L270 30 L300 22" fill="none" stroke="#ee4444" stroke-width="2.5" stroke-linejoin="round" />
                            <circle cx="300" cy="22" r="4" fill="#ee4444" />
                        </svg>
                        <div class="mt-2 flex justify-between text-[10px] text-zinc-600">@foreach (['Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov'] as $m)<span>{{ $m }}</span>@endforeach</div>
                    </div>
                    <div class="rounded-2xl border border-ink-700 bg-ink-800 p-5">
                        <div class="flex items-center justify-between"><p class="text-sm font-semibold">Membership expiry</p><span class="rounded-full bg-amber-500/15 px-2 py-0.5 text-[11px] text-amber-400">Next 15 days</span></div>
                        <ul class="mt-4 space-y-3 text-sm">
                            @foreach ([['Riya Bhatt', 'Monthly', '2 days'], ['Kabir Singh', 'Quarterly', '5 days'], ['Isha Joshi', 'Yearly', '9 days']] as [$name, $plan, $left])
                                <li class="flex items-center justify-between">
                                    <span class="flex items-center gap-3"><span class="flex size-8 items-center justify-center rounded-full bg-ink-700 text-xs font-semibold">{{ \Illuminate\Support\Str::of($name)->explode(' ')->map(fn ($p) => $p[0])->implode('') }}</span><span><span class="block font-medium">{{ $name }}</span><span class="text-xs text-zinc-500">{{ $plan }}</span></span></span>
                                    <span class="text-xs text-amber-400">{{ $left }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="rounded-2xl border border-ink-700 bg-ink-800 p-5">
                        <div class="flex items-center justify-between"><p class="text-sm font-semibold">Enquiry statistics</p><span class="text-xs text-zinc-500">This month</span></div>
                        <div class="mt-4 space-y-3">
                            @foreach ([['New', 18, 'bg-sky-400'], ['Contacted', 12, 'bg-amber-400'], ['Interested', 9, 'bg-purple-400'], ['Converted', 7, 'bg-emerald-400']] as [$label, $count, $bar])
                                <div>
                                    <div class="flex justify-between text-xs"><span class="text-zinc-400">{{ $label }}</span><span class="font-semibold">{{ $count }}</span></div>
                                    <div class="mt-1.5 h-2 rounded-full bg-ink-700"><div class="h-full rounded-full {{ $bar }}" style="width: {{ $count / 18 * 100 }}%"></div></div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
