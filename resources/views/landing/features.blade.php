@php
    // Every card describes a Gymai module. NOTE: "AI Body Transformation" is not built in this codebase yet —
    // it is advertised as live at the product owner's request (see also views/landing/member-spotlight.blade.php).
    $features = [
        ['users', 'Gym Member Management', 'Profiles, goals, trainer assignment and full history, plus bulk CSV/Excel import.'],
        ['card', 'Membership Plans', 'Monthly to yearly or custom plans with discounts, renewals and expiry tracking.'],
        ['qr', 'QR Code Attendance', 'QR or manual check-ins, duplicate-safe, with a live "inside now" count.'],
        ['cash', 'Fee & Payment Management', 'Cash, UPI, card or bank, full or partial, with receipts and invoices.'],
        ['whistle', 'Workout Plans', 'Build plans section by section or start from a ready-made library.'],
        ['tag', 'Diet Management', 'Diet charts with meals, timings and calories, right in the member app.'],
        ['user', 'Trainer Management', 'Trainer logins that only show the members assigned to them.'],
        ['phone', 'WhatsApp Quick Messages', 'Reach leads and at-risk members in one tap, message pre-written.'],
        ['chart', 'Reports & Analytics', 'Growth, revenue, attendance, streaks and pending fees at a glance.'],
        ['inbox', 'Enquiry Management', 'Capture walk-ins and calls, schedule follow-ups, convert in a click.'],
        ['shield', 'Staff Roles & Access', 'Role-based access so every person sees exactly what they should.'],
        ['palette', 'Gym Branding & Customization', 'Your logo, colours, photos and hours on your own gym page.'],
    ];
    $extras = ['Member portal & app', 'Push notifications', 'Weight tracking', 'Fitness programs', 'Printable invoices'];
@endphp
<section id="features" data-lp-section data-lp-spotlight class="lp-spotlight relative py-12 sm:py-16">
    <div aria-hidden="true" class="lp-dots pointer-events-none absolute inset-0 [mask-image:radial-gradient(ellipse_at_center,black_20%,transparent_70%)]"></div>
    <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-2xl">
                <p class="lp-reveal inline-flex items-center gap-2 rounded-full border border-brand-600/30 bg-brand-600/10 px-3 py-1 text-xs font-semibold tracking-[0.2em] text-brand-400 uppercase">
                    <x-ui.icon name="bolt" class="size-3.5" /> Features
                </p>
                <h2 data-split class="lp-reveal mt-3 text-3xl font-bold tracking-tight sm:text-4xl" style="--d: 80ms">Everything your gym needs. <span class="text-zinc-500">Nothing it doesn't.</span></h2>
            </div>
            <p class="lp-reveal max-w-sm text-sm text-zinc-400 lg:text-right" style="--d: 160ms">Fourteen connected modules that replace registers, spreadsheets and scattered apps with one fast dashboard.</p>
        </div>

        <div class="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {{-- Featured: AI Body Transformation (illustrative preview) --}}
            <article class="lp-reveal sm:col-span-2 lg:row-span-2">
                <div data-tilt-card data-lp-glow class="lp-glow-card group relative flex h-full flex-col overflow-hidden rounded-2xl border border-brand-600/30 bg-gradient-to-br from-brand-600/20 via-ink-800/80 to-ink-800/60 p-6 transition-[border-color,box-shadow] duration-300 hover:border-brand-500/60 hover:shadow-[0_24px_60px_-24px_rgb(238_68_68/0.6)]">
                    <div aria-hidden="true" class="pointer-events-none absolute -top-16 -right-16 size-56 rounded-full bg-brand-600/25 blur-3xl"></div>
                    <div class="relative flex items-center justify-between gap-3">
                        <div class="flex size-11 items-center justify-center rounded-xl bg-brand-600 text-white shadow-[0_0_28px_-4px_rgb(238_68_68/0.9)]">
                            <x-ui.icon name="sparkles" class="lp-icon-bounce size-6" />
                        </div>
                        <span class="rounded-full border border-brand-500/40 bg-brand-600/15 px-2.5 py-1 text-[11px] font-semibold tracking-wider text-brand-300 uppercase">New · AI</span>
                    </div>
                    <h3 class="relative mt-5 text-xl font-semibold sm:text-2xl">AI Body Transformation</h3>
                    <p class="relative mt-2 max-w-md text-sm leading-relaxed text-zinc-300">Members see an AI-powered preview of their goal physique and track progress towards it, keeping them motivated week after week.</p>

                    <div class="relative mt-auto grid grid-cols-[auto_1fr] items-center gap-5 pt-6">
                        <div class="relative size-24">
                            <svg viewBox="0 0 36 36" class="size-24 -rotate-90" aria-hidden="true">
                                <circle cx="18" cy="18" r="15.5" fill="none" stroke="rgb(255 255 255 / 0.08)" stroke-width="3" />
                                <circle cx="18" cy="18" r="15.5" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-dasharray="97.4" stroke-dashoffset="27" class="text-brand-500" />
                            </svg>
                            <div class="absolute inset-0 flex flex-col items-center justify-center">
                                <span class="text-lg font-bold">72%</span>
                                <span class="text-[10px] text-zinc-400">to goal</span>
                            </div>
                        </div>
                        <div class="space-y-2.5">
                            @foreach (['Week 1' => 'w-1/4', 'Week 6' => 'w-1/2', 'Week 12' => 'w-[72%]'] as $week => $width)
                                <div class="flex items-center gap-3 text-xs">
                                    <span class="w-14 shrink-0 text-zinc-400">{{ $week }}</span>
                                    <span class="h-1.5 flex-1 overflow-hidden rounded-full bg-white/10"><span class="block h-full {{ $width }} rounded-full bg-gradient-to-r from-brand-600 to-orange-400"></span></span>
                                </div>
                            @endforeach
                            <p class="pt-1 text-[11px] text-zinc-500">Illustrative preview</p>
                        </div>
                    </div>
                </div>
            </article>

            {{-- Wide: QR self-registration --}}
            <article class="lp-reveal sm:col-span-2" style="--d: 70ms">
                <div data-tilt-card data-lp-glow class="lp-glow-card group h-full rounded-2xl border border-ink-700 bg-ink-800/60 p-5 backdrop-blur-sm transition-[border-color,box-shadow] duration-300 hover:border-brand-600/50 hover:shadow-[0_20px_50px_-20px_rgb(238_68_68/0.45)]">
                    <div class="flex items-start gap-4">
                        <div class="flex size-11 shrink-0 items-center justify-center rounded-xl border border-brand-600/25 bg-gradient-to-br from-brand-600/25 to-transparent text-brand-400 transition duration-300 group-hover:border-brand-500/60 group-hover:shadow-[0_0_28px_-4px_rgb(238_68_68/0.8)]">
                            <x-ui.icon name="device" class="lp-icon-bounce size-6" />
                        </div>
                        <div class="min-w-0">
                            <h3 class="font-semibold">QR Self-Registration</h3>
                            <p class="mt-1 text-sm leading-relaxed text-zinc-400">New members register themselves from their phone. No forms for your staff to type.</p>
                        </div>
                    </div>
                    <ol class="mt-4 flex flex-wrap items-center gap-2 text-xs font-medium">
                        @foreach (['Scan desk QR', 'Fill short form', 'Get member ID'] as $n => $label)
                            <li class="flex items-center gap-2">
                                <span class="flex items-center gap-1.5 rounded-full border border-ink-700 bg-ink-900/70 py-1 pr-3 pl-1 text-zinc-300">
                                    <span class="flex size-5 items-center justify-center rounded-full bg-brand-600/20 text-[10px] text-brand-400">{{ $n + 1 }}</span>{{ $label }}
                                </span>
                                @unless ($loop->last)<x-ui.icon name="arrow-right" class="size-3.5 text-zinc-600" />@endunless
                            </li>
                        @endforeach
                    </ol>
                </div>
            </article>

            {{-- Compact module cards --}}
            @foreach ($features as $i => [$icon, $title, $text])
                <article class="lp-reveal" style="--d: {{ ($i % 4) * 60 }}ms">
                    <div data-tilt-card data-lp-glow class="lp-glow-card group flex h-full gap-3.5 rounded-2xl border border-ink-700 bg-ink-800/60 p-4 backdrop-blur-sm transition-[border-color,box-shadow] duration-300 hover:border-brand-600/50 hover:shadow-[0_20px_50px_-20px_rgb(238_68_68/0.45)]">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-xl border border-brand-600/25 bg-gradient-to-br from-brand-600/25 to-transparent text-brand-400 transition duration-300 group-hover:border-brand-500/60 group-hover:shadow-[0_0_24px_-4px_rgb(238_68_68/0.8)]">
                            <x-ui.icon :name="$icon" class="lp-icon-bounce size-5" />
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold">{{ $title }}</h3>
                            <p class="mt-1 text-[13px] leading-snug text-zinc-400">{{ $text }}</p>
                        </div>
                    </div>
                </article>
            @endforeach

            {{-- Closing tile --}}
            <article class="lp-reveal sm:col-span-2" style="--d: 120ms">
                <div class="lp-border-glow h-full rounded-2xl bg-ink-700/60 p-px">
                <div class="flex h-full flex-col justify-between gap-4 rounded-[15px] bg-ink-900 p-5 sm:flex-row sm:items-center">
                    <div>
                        <p class="text-sm font-semibold">And much more, built in</p>
                        <div class="mt-2.5 flex flex-wrap gap-1.5">
                            @foreach ($extras as $extra)
                                <span class="rounded-full border border-ink-700 bg-ink-800 px-2.5 py-1 text-xs text-zinc-300">{{ $extra }}</span>
                            @endforeach
                        </div>
                    </div>
                    <a href="#pricing" class="inline-flex shrink-0 items-center gap-1.5 text-sm font-semibold text-brand-400 transition hover:text-brand-300">See pricing <x-ui.icon name="arrow-right" class="size-4" /></a>
                </div>
                </div>
            </article>
        </div>
    </div>
</section>
