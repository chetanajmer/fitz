{{--
    Pricing comes from the live billing model: one plan billed per active member per month
    (PricingRate::rateOn(), editable by the Super Admin). There are no tiers, member/staff
    limits or yearly billing in the platform, so none are shown.
--}}
<section id="pricing" data-lp-section class="relative overflow-hidden border-y border-ink-700 bg-ink-900 py-14 sm:py-20">
    <div aria-hidden="true" class="pointer-events-none absolute -bottom-40 left-1/2 h-96 w-[56rem] -translate-x-1/2 rounded-full bg-brand-600/15 blur-3xl"></div>
    <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center">
            <p class="lp-reveal text-sm font-semibold tracking-[0.2em] text-brand-500 uppercase">Pricing</p>
            <h2 data-split class="lp-reveal mt-3 text-3xl font-bold tracking-tight sm:text-5xl" style="--d: 80ms">One simple plan. Pay as you grow.</h2>
            <p class="lp-reveal mt-5 text-zinc-400" style="--d: 160ms">No setup fee, no registration fee and no locked features. You only pay for members who hold a valid membership.</p>
        </div>

        <div class="mx-auto mt-10 grid max-w-5xl gap-6 lg:grid-cols-[1.1fr_1fr]">
            {{-- Plan card --}}
            <div class="lp-reveal" style="--d: 120ms">
            <div data-tilt-card class="lp-border-glow h-full rounded-3xl bg-ink-700/60 p-px shadow-[0_30px_90px_-30px_rgb(238_68_68/0.5)]">
                <div class="relative h-full rounded-[calc(1.5rem-1px)] bg-ink-850 p-8 sm:p-10">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-lg font-semibold">Gymai Complete</h3>
                        <span class="flex items-center gap-1.5 rounded-full bg-brand-600 px-3 py-1 text-xs font-semibold"><x-ui.icon name="star" class="size-3.5 fill-current" /> Recommended</span>
                    </div>
                    <p class="mt-6 flex items-end gap-2">
                        <span class="text-5xl font-bold tracking-tight sm:text-6xl">{{ $priceLabel }}</span>
                        <span class="pb-2 text-sm text-zinc-400">per active member / month</span>
                    </p>
                    <p class="mt-2 text-sm text-zinc-500">Billed monthly. Enquiries, expired and inactive members are free.</p>

                    <dl class="mt-8 grid grid-cols-3 gap-3 rounded-2xl border border-ink-700 bg-ink-900/60 p-4 text-center">
                        <div><dt class="text-[11px] tracking-wide text-zinc-500 uppercase">Members</dt><dd class="mt-1 font-semibold">Unlimited</dd></div>
                        <div><dt class="text-[11px] tracking-wide text-zinc-500 uppercase">Trainers</dt><dd class="mt-1 font-semibold">Unlimited</dd></div>
                        <div><dt class="text-[11px] tracking-wide text-zinc-500 uppercase">Modules</dt><dd class="mt-1 font-semibold">All 14</dd></div>
                    </dl>

                    <ul class="mt-8 grid gap-3 text-sm text-zinc-300 sm:grid-cols-2">
                        @foreach (['Member & membership management', 'QR attendance & reports', 'Payments, receipts & invoices', 'Diet charts & workout plans', 'Trainer logins & access control', 'Enquiries & follow-ups', 'Member portal & mobile app', 'Your own branded gym page', 'AI body transformation', 'QR self-registration'] as $i => $line)
                            <li class="lp-reveal flex gap-2.5" style="--d: {{ 200 + $i * 60 }}ms"><span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-brand-600/20 text-brand-400"><x-ui.icon name="check" class="size-3.5" /></span> {{ $line }}</li>
                        @endforeach
                    </ul>

                    <a href="{{ $ctaUrl }}" class="btn-primary lp-shine-btn group mt-10 w-full py-3.5 text-base shadow-[0_10px_40px_-8px_rgb(238_68_68/0.8)]">
                        Choose Plan
                        <x-ui.icon name="arrow-right" class="size-4 transition group-hover:translate-x-1" />
                    </a>
                    @unless ($registrationsOpen)
                        <p class="mt-3 text-center text-xs text-zinc-500">New registrations are currently paused — contact us to get started.</p>
                    @endunless
                </div>
            </div>
            </div>

            {{-- Live cost calculator --}}
            <div data-lp-calc data-rate="{{ $price }}" class="lp-reveal flex flex-col rounded-3xl border border-white/10 bg-white/[0.03] p-8 backdrop-blur-xl sm:p-10" style="--d: 220ms">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <h3 class="text-lg font-semibold">Estimate your bill</h3>
                    {{-- Billing is monthly; the yearly view is simply 12 × the monthly estimate (no yearly plan or discount exists). --}}
                    <div class="relative grid grid-cols-2 rounded-full border border-ink-700 bg-ink-900 p-1 text-xs font-semibold" role="group" aria-label="Estimate period">
                        <span data-lp-period-indicator aria-hidden="true" class="absolute inset-y-1 left-1 w-[calc(50%-0.25rem)] rounded-full bg-brand-600 shadow-[0_0_20px_-4px_rgb(238_68_68/0.9)]"></span>
                        <button type="button" data-lp-period="1" aria-pressed="true" class="relative z-10 rounded-full px-4 py-1.5 text-zinc-400 transition-colors aria-pressed:text-white">Monthly</button>
                        <button type="button" data-lp-period="12" aria-pressed="false" class="relative z-10 rounded-full px-4 py-1.5 text-zinc-400 transition-colors aria-pressed:text-white">Yearly</button>
                    </div>
                </div>
                <p class="mt-1 text-sm text-zinc-500">Drag to the number of members with an active membership.</p>

                <label for="lp-members" class="mt-10 flex items-baseline justify-between">
                    <span class="text-sm text-zinc-400">Active members</span>
                    <output data-lp-calc-members for="lp-members" class="text-3xl font-bold">150</output>
                </label>
                <input id="lp-members" data-lp-calc-input type="range" min="10" max="1000" step="10" value="150" class="lp-range mt-4 w-full cursor-pointer">
                <div class="mt-2 flex justify-between text-xs text-zinc-600"><span>10</span><span>1,000</span></div>

                <div class="mt-auto pt-10">
                    <div class="rounded-2xl border border-brand-600/30 bg-brand-600/10 p-5">
                        <p class="text-sm text-zinc-400" data-lp-calc-period-label>Estimated monthly cost</p>
                        <p class="mt-1 text-4xl font-bold text-white" data-lp-calc-total>{{ money($price * 150, false) }}</p>
                        <p class="mt-2 text-xs text-zinc-500"><span data-lp-calc-formula>150</span> × {{ $priceLabel }} · no setup fee</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
