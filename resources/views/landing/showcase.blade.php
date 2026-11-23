{{-- Product showcase: a mockup of the real gym dashboard. Numbers are illustrative demo data. --}}
<section id="showcase" data-lp-section class="relative py-24 sm:py-32">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center">
            <p class="lp-reveal text-sm font-semibold tracking-[0.2em] text-brand-500 uppercase">Dashboard</p>
            <h2 class="lp-reveal mt-3 text-3xl font-bold tracking-tight sm:text-5xl" style="--d: 80ms">Your whole gym on one screen</h2>
            <p class="lp-reveal mt-5 text-zinc-400" style="--d: 160ms">Members, money and attendance update the moment something happens at your front desk.</p>
        </div>

        <div class="lp-reveal relative mt-16" style="--d: 200ms" aria-hidden="true">
            <div class="absolute -inset-x-10 -inset-y-6 rounded-[3rem] bg-gradient-to-b from-brand-600/15 to-transparent blur-2xl"></div>
            <div class="relative overflow-hidden rounded-3xl border border-white/10 bg-ink-900 p-4 shadow-[0_50px_140px_-40px_rgb(238_68_68/0.4)] sm:p-6">
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
                        <p class="text-sm font-semibold">Today</p>
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

                <div class="mt-3 grid gap-3 md:grid-cols-2">
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
