{{--
    Pricing comes from the live billing model: one plan billed per active member per month
    (PricingRate::rateOn(), editable by the Super Admin). There are no tiers, member/staff
    limits or yearly billing in the platform, so none are shown.
--}}
<section id="pricing" data-lp-section class="relative overflow-hidden border-y border-ink-700 bg-ink-900 py-24 sm:py-32">
    <div aria-hidden="true" class="pointer-events-none absolute -bottom-40 left-1/2 h-96 w-[56rem] -translate-x-1/2 rounded-full bg-brand-600/15 blur-3xl"></div>
    <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center">
            <p class="lp-reveal text-sm font-semibold tracking-[0.2em] text-brand-500 uppercase">Pricing</p>
            <h2 class="lp-reveal mt-3 text-3xl font-bold tracking-tight sm:text-5xl" style="--d: 80ms">One simple plan. Pay as you grow.</h2>
            <p class="lp-reveal mt-5 text-zinc-400" style="--d: 160ms">No setup fee, no registration fee and no locked features. You only pay for members who hold a valid membership.</p>
        </div>

        <div class="mx-auto mt-16 grid max-w-5xl gap-6 lg:grid-cols-[1.1fr_1fr]">
            {{-- Plan card --}}
            <div class="lp-reveal relative rounded-3xl bg-gradient-to-b from-brand-600/70 via-brand-600/20 to-ink-700 p-px" style="--d: 120ms">
                <div class="relative h-full rounded-[calc(1.5rem-1px)] bg-ink-850 p-8 sm:p-10">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-lg font-semibold">Gymai Complete</h3>
                        <span class="rounded-full bg-brand-600 px-3 py-1 text-xs font-semibold">All features</span>
                    </div>
                    <p class="mt-6 flex items-end gap-2">
                        <span class="text-5xl font-bold tracking-tight sm:text-6xl">{{ $priceLabel }}</span>
                        <span class="pb-2 text-sm text-zinc-400">per active member / month</span>
                    </p>
                    <p class="mt-2 text-sm text-zinc-500">Billed monthly. Enquiries, expired and inactive members are free.</p>

                    <dl class="mt-8 grid grid-cols-3 gap-3 rounded-2xl border border-ink-700 bg-ink-900/60 p-4 text-center">
                        <div><dt class="text-[11px] tracking-wide text-zinc-500 uppercase">Members</dt><dd class="mt-1 font-semibold">Unlimited</dd></div>
                        <div><dt class="text-[11px] tracking-wide text-zinc-500 uppercase">Trainers</dt><dd class="mt-1 font-semibold">Unlimited</dd></div>
                        <div><dt class="text-[11px] tracking-wide text-zinc-500 uppercase">Modules</dt><dd class="mt-1 font-semibold">All 12</dd></div>
                    </dl>

                    <ul class="mt-8 grid gap-3 text-sm text-zinc-300 sm:grid-cols-2">
                        @foreach (['Member & membership management', 'QR attendance & reports', 'Payments, receipts & invoices', 'Diet charts & workout plans', 'Trainer logins & access control', 'Enquiries & follow-ups', 'Member portal & mobile app', 'Your own branded gym page'] as $line)
                            <li class="flex gap-2.5"><x-ui.icon name="check" class="size-5 shrink-0 text-brand-500" /> {{ $line }}</li>
                        @endforeach
                    </ul>

                    <a href="{{ $ctaUrl }}" class="btn-primary group mt-10 w-full py-3.5 text-base">
                        Choose Plan
                        <x-ui.icon name="arrow-right" class="size-4 transition group-hover:translate-x-0.5" />
                    </a>
                    @unless ($registrationsOpen)
                        <p class="mt-3 text-center text-xs text-zinc-500">New registrations are currently paused — contact us to get started.</p>
                    @endunless
                </div>
            </div>

            {{-- Live cost calculator --}}
            <div data-lp-calc data-rate="{{ $price }}" class="lp-reveal flex flex-col rounded-3xl border border-ink-700 bg-ink-800/60 p-8 sm:p-10" style="--d: 220ms">
                <h3 class="text-lg font-semibold">Estimate your monthly bill</h3>
                <p class="mt-1 text-sm text-zinc-500">Drag to the number of members with an active membership.</p>

                <label for="lp-members" class="mt-10 flex items-baseline justify-between">
                    <span class="text-sm text-zinc-400">Active members</span>
                    <output data-lp-calc-members for="lp-members" class="text-3xl font-bold">150</output>
                </label>
                <input id="lp-members" data-lp-calc-input type="range" min="10" max="1000" step="10" value="150" class="lp-range mt-4 w-full cursor-pointer">
                <div class="mt-2 flex justify-between text-xs text-zinc-600"><span>10</span><span>1,000</span></div>

                <div class="mt-auto pt-10">
                    <div class="rounded-2xl border border-brand-600/30 bg-brand-600/10 p-5">
                        <p class="text-sm text-zinc-400">Estimated monthly cost</p>
                        <p class="mt-1 text-4xl font-bold text-white" data-lp-calc-total>{{ money($price * 150, false) }}</p>
                        <p class="mt-2 text-xs text-zinc-500"><span data-lp-calc-formula>150</span> × {{ $priceLabel }} · no setup fee</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
