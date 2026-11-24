@php
    $faqs = [
        ['What is Gymai?', 'Gymai is all-in-one gym management software. It handles members, membership plans, fees and receipts, QR attendance, trainers, diet charts, workout plans, enquiries and reports from one dashboard, with a portal and app for your members.'],
        ['How can I register my gym?', 'Click "'.$ctaLabel.'", enter your details and your gym\'s name, city and address. Your dashboard, public gym page and QR codes are ready immediately.'],
        ['Do you offer a free trial?', 'There is no separate trial period, but there is also no setup or registration fee. You are billed '.$priceLabel.' per active member per month, so you can set up your plans, trainers and members and only pay once members hold a valid membership.'],
        ['Can I manage multiple gym members?', 'Yes â€” there is no member limit. Add members one by one, through the QR self-registration form, or import them in bulk from a CSV or Excel file.'],
        ['Can new members register themselves?', 'Yes. Print your gym\'s registration QR code from the panel. New members scan it with their phone, fill a short form and get a member ID instantly â€” you then assign a plan and collect the fee.'],
        ['What is AI Body Transformation?', 'It gives members an AI-powered preview of their goal physique and tracks their progress towards it, alongside the diet charts and workout plans your trainers assign.'],
        ['Does Gymai support QR attendance?', 'Yes. Every member gets a personal QR code. Staff can scan it to check members in and out, members can check themselves in from the app, and you can still check in manually.'],
        ['How can I renew my subscription?', 'Your subscription renews automatically each month. You receive a monthly invoice for your active members, and you can see your full billing history under "Gymai Billing" in your panel.'],
        ['Can I manage fees and payments?', 'Yes. Record full or partial payments by cash, UPI, card or bank transfer, see pending dues for every member, and print receipts and invoices.'],
        ['Can I customize my gym branding?', 'Yes. Upload your logo, set your brand colours, add photos, facilities and opening hours. They appear on your public gym page, registration forms and in the member app.'],
    ];
@endphp
<section id="faq" data-lp-section class="relative overflow-hidden py-12 sm:py-16">
    <div class="pointer-events-none absolute top-1/3 left-1/2 -z-10 size-[36rem] -translate-x-1/2 rounded-full bg-brand-600/10 blur-[120px]"></div>
    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col items-center gap-3 text-center">
            <p class="lp-reveal inline-flex items-center gap-2 rounded-full border border-brand-600/30 bg-brand-600/10 px-3 py-1 text-xs font-semibold tracking-[0.2em] text-brand-400 uppercase">
                <x-ui.icon name="sparkles" class="size-3.5" /> FAQ
            </p>
            <h2 data-split class="lp-reveal text-3xl font-bold tracking-tight sm:text-4xl" style="--d: 80ms">Questions, <span class="text-brand-500">answered</span></h2>
            <p class="lp-reveal max-w-xl text-sm text-zinc-400 sm:text-base" style="--d: 140ms">Everything gym owners usually ask before getting started with Gymai.</p>
        </div>

        <div class="mt-8 grid gap-2.5 md:grid-cols-2 md:gap-3">
            @foreach (array_chunk($faqs, (int) ceil(count($faqs) / 2), true) as $column)
                <div class="space-y-2.5 md:space-y-3">
                    @foreach ($column as $i => [$question, $answer])
                        <details name="lp-faq" class="lp-reveal group relative overflow-hidden rounded-xl border border-ink-700 bg-ink-800/40 transition-colors duration-300 hover:border-zinc-700 open:border-brand-600/40 open:bg-gradient-to-br open:from-brand-600/10 open:to-ink-800/80" style="--d: {{ ($i % 5) * 60 }}ms" @if ($i === 0) open @endif>
                            <span class="absolute inset-y-3 left-0 w-0.5 rounded-full bg-brand-500 opacity-0 transition group-open:opacity-100"></span>
                            <summary class="flex cursor-pointer items-center gap-3 px-4 py-3.5 text-left sm:px-5">
                                <span class="w-6 shrink-0 font-mono text-xs font-semibold text-zinc-600 transition group-open:text-brand-500">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                                <span class="flex-1 text-sm font-medium text-zinc-200 transition group-hover:text-white group-open:text-white sm:text-[15px]">{{ $question }}</span>
                                <span class="lp-faq-icon flex size-6 shrink-0 items-center justify-center rounded-full bg-ink-700 text-base leading-none text-brand-400 transition duration-300 group-open:bg-brand-600 group-open:text-white">+</span>
                            </summary>
                            <p class="lp-faq-body pr-5 pb-4 pl-13 text-sm leading-relaxed text-zinc-400 sm:pl-14">{{ $answer }}</p>
                        </details>
                    @endforeach
                </div>
            @endforeach
        </div>

        <div class="lp-reveal mt-6 flex flex-col items-center justify-between gap-4 rounded-2xl border border-ink-700 bg-ink-800/40 px-5 py-4 sm:flex-row" style="--d: 120ms">
            <div class="flex items-center gap-3 text-center sm:text-left">
                <span class="hidden size-10 shrink-0 items-center justify-center rounded-xl bg-brand-600/15 text-brand-400 sm:flex"><x-ui.icon name="mail" class="size-5" /></span>
                <div>
                    <p class="text-sm font-semibold text-white">Still have questions?</p>
                    <p class="text-xs text-zinc-400">Our team is happy to help you set up your gym.</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center justify-center gap-2">
                @if ($contact['email'])
                    <a href="mailto:{{ $contact['email'] }}" class="rounded-lg border border-zinc-700 px-4 py-2 text-sm font-medium text-zinc-200 transition hover:border-brand-600/60 hover:text-white">Email us</a>
                @endif
                <a href="{{ $ctaUrl }}" class="lp-shine-btn relative overflow-hidden rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-[0_0_20px_-4px_rgb(238_68_68/0.7)] transition hover:bg-brand-500">{{ $ctaLabel }}</a>
            </div>
        </div>
    </div>
</section>
