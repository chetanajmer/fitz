{{--
    Pricing comes from the live billing model: one plan billed per active member per month
    (PricingRate::rateOn(), editable by the Super Admin). There are no tiers, member/staff
    limits or yearly billing in the platform, so none are shown. The section frames the rate as a
    small add-on to each member's fee, so the gym can pass it on and members cover Gymai.
--}}
@php
    $included = ['Members & memberships', 'QR attendance & streaks', 'Fees, receipts & invoices', 'Diet & workout programs', 'Trainer logins & access', 'Enquiries & follow-ups', 'Member portal & app', 'Push notifications', 'Your public gym page', 'AI Coach reports'];
    $sampleFee = 1000;
@endphp
<section id="pricing" data-lp-section class="relative py-16 sm:py-24">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <p class="lp-mono">Pricing</p>
        <h2 data-lp-slam class="lp-display mt-3 text-5xl sm:text-7xl">One plan. <span class="lp-hl">Your members cover it.</span></h2>

        <div class="mt-10 grid gap-8 lg:grid-cols-[0.9fr_1.1fr]">
            <div class="lp-reveal flex flex-col border-2 border-forest p-6 sm:p-8">
                <p class="lp-mono">Gymai Complete</p>
                <p class="mt-4 flex flex-wrap items-end gap-x-3">
                    <span class="lp-display text-8xl sm:text-9xl">{{ $priceLabel }}</span>
                    <span class="pb-3 font-bold">per member<br>per month</span>
                </p>
                <p class="mt-4 text-lg leading-snug font-bold">Add it to your membership fee and your members pay for Gymai, not you.</p>
                <p class="mt-2 text-sm font-medium text-forest/70">No setup fee, no registration fee, every feature. Only members with an active membership count — enquiries, expired and inactive members are free.</p>
                <ul class="mt-6 grid gap-x-4 gap-y-2.5 text-sm font-semibold sm:grid-cols-2">
                    @foreach ($included as $line)
                        <li class="flex items-center gap-2.5"><span class="flex size-5 shrink-0 items-center justify-center bg-forest text-volt"><x-ui.icon name="check" class="size-3.5" /></span>{{ $line }}</li>
                    @endforeach
                </ul>
                <div class="mt-auto pt-8">
                    <a href="{{ $ctaUrl }}" class="lp-btn lp-btn-solid w-full">{{ $ctaLabel }} <x-ui.icon name="arrow-right" class="size-4" /></a>
                    @unless ($registrationsOpen)
                        <p class="mt-3 text-center text-sm font-medium">New registrations are currently paused — contact us to get started.</p>
                    @endunless
                </div>
            </div>

            {{-- Calculator: the rate shown as a line on each member's fee. --}}
            <div data-lp-calc data-rate="{{ $price }}" class="lp-reveal flex flex-col bg-forest p-6 text-chalk sm:p-8" style="--d: 120ms">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <p class="lp-display text-3xl">Who pays? <span class="text-volt">Your members.</span></p>
                    {{-- Billing is monthly; the yearly view is simply 12 × the monthly figures (no yearly plan or discount exists). --}}
                    <div class="grid grid-cols-2 border-2 border-chalk/40 text-xs font-bold" role="group" aria-label="Show figures per">
                        <button type="button" data-lp-period="1" aria-pressed="true" class="px-4 py-2 aria-pressed:bg-volt aria-pressed:text-forest">Monthly</button>
                        <button type="button" data-lp-period="12" aria-pressed="false" class="px-4 py-2 aria-pressed:bg-volt aria-pressed:text-forest">Yearly</button>
                    </div>
                </div>

                <div class="mt-8">
                    <label for="lp-fee" class="flex items-baseline justify-between gap-4">
                        <span class="lp-mono">What do you charge per month?</span>
                        <output data-lp-calc-fee-value for="lp-fee" class="lp-display text-4xl text-volt">₹{{ number_format($sampleFee) }}</output>
                    </label>
                    <input id="lp-fee" data-lp-calc-fee type="range" min="300" max="5000" step="100" value="{{ $sampleFee }}" class="lp-range mt-3 w-full cursor-pointer">
                    <div class="lp-mono mt-2 flex justify-between text-chalk/50"><span>₹300</span><span>₹5,000</span></div>
                </div>

                {{-- One member's receipt --}}
                <div class="mt-8 mb-8 -rotate-1 bg-chalk p-5 text-forest shadow-[8px_8px_0_0_var(--color-volt)] sm:mx-6">
                    <p class="lp-mono flex justify-between"><span>A member's receipt</span><span data-lp-calc-per>/ month</span></p>
                    <dl class="mt-4 space-y-2 text-sm font-semibold">
                        <div class="flex justify-between"><dt>Membership fee</dt><dd data-lp-calc-fee-line>₹{{ number_format($sampleFee) }}</dd></div>
                        <div class="flex justify-between text-clay"><dt>+ Gymai app, QR check-in &amp; streaks</dt><dd data-lp-calc-rate-line>{{ $priceLabel }}</dd></div>
                        <div class="flex items-end justify-between border-t-2 border-dashed border-forest/40 pt-3"><dt class="font-bold">Member pays</dt><dd class="lp-display text-4xl" data-lp-calc-member-pays>₹{{ number_format($sampleFee + $price) }}</dd></div>
                    </dl>
                    <p class="mt-2 text-xs font-medium text-forest/70">Just <span data-lp-calc-share>{{ round($price / $sampleFee * 100, 1) }}%</span> more for them — about the price of a cup of chai.</p>
                </div>

                <div class="mt-auto flex flex-wrap items-end justify-between gap-4 border-t-2 border-chalk/20 pt-6">
                    <div>
                        <p class="lp-mono text-chalk/70">Gymai costs you</p>
                        <p class="lp-display mt-2 text-7xl text-volt">₹0</p>
                        <p class="mt-2 text-sm font-medium text-chalk/75">when you add {{ $priceLabel }} to each member's fee</p>
                    </div>
                    <p class="lp-mono text-chalk/60">No setup fee</p>
                </div>
            </div>
        </div>
    </div>
</section>
