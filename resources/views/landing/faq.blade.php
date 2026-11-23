@php
    $faqs = [
        ['What is Gymai?', 'Gymai is all-in-one gym management software. It handles members, membership plans, fees and receipts, QR attendance, trainers, diet charts, workout plans, enquiries and reports from one dashboard, with a portal and app for your members.'],
        ['How can I register my gym?', 'Click "'.$ctaLabel.'", enter your details and your gym\'s name, city and address. Your dashboard, public gym page and QR codes are ready immediately.'],
        ['Do you offer a free trial?', 'There is no separate trial period, but there is also no setup or registration fee. You are billed '.$priceLabel.' per active member per month, so you can set up your plans, trainers and members and only pay once members hold a valid membership.'],
        ['Can I manage multiple gym members?', 'Yes — there is no member limit. Add members one by one, through the QR self-registration form, or import them in bulk from a CSV or Excel file.'],
        ['Can new members register themselves?', 'Yes. Print your gym\'s registration QR code from the panel. New members scan it with their phone, fill a short form and get a member ID instantly — you then assign a plan and collect the fee.'],
        ['What is AI Body Transformation?', 'It gives members an AI-powered preview of their goal physique and tracks their progress towards it, alongside the diet charts and workout plans your trainers assign.'],
        ['Does Gymai support QR attendance?', 'Yes. Every member gets a personal QR code. Staff can scan it to check members in and out, members can check themselves in from the app, and you can still check in manually.'],
        ['How can I renew my subscription?', 'Your subscription renews automatically each month. You receive a monthly invoice for your active members, and you can see your full billing history under "Gymai Billing" in your panel.'],
        ['Can I manage fees and payments?', 'Yes. Record full or partial payments by cash, UPI, card or bank transfer, see pending dues for every member, and print receipts and invoices.'],
        ['Can I customize my gym branding?', 'Yes. Upload your logo, set your brand colours, add photos, facilities and opening hours. They appear on your public gym page, registration forms and in the member app.'],
    ];
@endphp
<section id="faq" data-lp-section class="relative py-14 sm:py-20">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
        <div class="text-center">
            <p class="lp-reveal text-sm font-semibold tracking-[0.2em] text-brand-500 uppercase">FAQ</p>
            <h2 data-split class="lp-reveal mt-3 text-3xl font-bold tracking-tight sm:text-5xl" style="--d: 80ms">Questions, answered</h2>
        </div>
        <div class="mt-10 space-y-3">
            @foreach ($faqs as $i => [$question, $answer])
                <details class="lp-reveal group rounded-2xl border border-ink-700 bg-ink-800/50 transition open:border-brand-600/40 open:bg-ink-800" style="--d: {{ $i * 50 }}ms" @if ($i === 0) open @endif>
                    <summary class="flex cursor-pointer items-center justify-between gap-4 px-6 py-5 text-left font-medium">
                        {{ $question }}
                        <span class="lp-faq-icon flex size-7 shrink-0 items-center justify-center rounded-full bg-ink-700 text-lg leading-none text-brand-400 transition">+</span>
                    </summary>
                    <p class="px-6 pb-6 text-sm leading-relaxed text-zinc-400">{{ $answer }}</p>
                </details>
            @endforeach
        </div>
    </div>
</section>
