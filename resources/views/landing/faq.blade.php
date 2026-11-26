@php
    $faqs = [
        ['What is Fitnix?', 'Fitnix is all-in-one gym management software. It handles members, membership plans, fees and receipts, QR attendance, trainers, diet charts, workout plans, enquiries and reports from one dashboard, with a portal and app for your members.'],
        ['How can I register my gym?', 'Click "'.$ctaLabel.'", enter your details and your gym\'s name, city and address. Your dashboard, public gym page and QR codes are ready immediately.'],
        ['Do you offer a free trial?', 'There is no separate trial period, but there is also no setup or registration fee. You are billed '.$priceLabel.' per active member per month, so you can set up your plans, trainers and members and only pay once members hold a valid membership.'],
        ['Is there a limit on members or trainers?', 'No. Add members one by one, through the QR self-registration form or online joining, or import them in bulk from a CSV or Excel file. Add as many trainers as you need.'],
        ['Can new members register themselves?', 'Yes. Members can find your gym on Fitnix and join online, or scan your gym\'s registration QR code. They fill a short form and get a member ID instantly — you then assign a plan and collect the fee.'],
        ['What does AI Coach do?', 'AI Coach writes progress reports for members from their check-ins, weight logs and progress photos, including photo comparisons. Members opt in first, and the gym can read every report.'],
        ['Does Fitnix support QR attendance?', 'Yes. Every member gets a personal QR code. Staff can scan it to check members in and out, members can check themselves in from the app, and you can still check in manually.'],
        ['How does billing work?', 'Your subscription renews automatically each month. You receive a monthly invoice for your active members and can see your full billing history under "Fitnix Billing" in your panel.'],
        ['Can I manage fees and payments?', 'Yes. Record full or partial payments by cash, UPI, card or bank transfer, see pending dues for every member, and print receipts and invoices.'],
        ['Can I customize my gym branding?', 'Yes. Upload your logo, set your brand colours, add photos, facilities and opening hours. They appear on your public gym page, registration forms and in the member app.'],
    ];
@endphp
<section id="faq" data-lp-section class="relative py-16 sm:py-24">
    <div class="mx-auto grid max-w-7xl gap-10 px-4 sm:px-6 lg:grid-cols-[0.7fr_1.3fr] lg:px-8">
        <div class="lg:sticky lg:top-32 lg:self-start">
            <p class="lp-mono">FAQ</p>
            <h2 data-lp-slam class="lp-display mt-3 text-5xl sm:text-7xl">Questions, <span class="lp-hl">answered.</span></h2>
            <p class="mt-5 max-w-sm font-medium text-forest/75">Everything gym owners usually ask before getting started.</p>
            @if ($contact['email'])
                <a href="mailto:{{ $contact['email'] }}" class="mt-6 inline-block font-bold underline decoration-2 underline-offset-4 hover:text-clay">Still stuck? Email us →</a>
            @endif
        </div>

        <div class="border-t-2 border-forest">
            @foreach ($faqs as $i => [$question, $answer])
                <details name="lp-faq" class="group border-b-2 border-forest" @if ($i === 0) open @endif>
                    <summary class="flex cursor-pointer items-center gap-4 py-5 transition-colors hover:text-clay">
                        <span class="lp-mono w-7 shrink-0 text-clay">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                        <span class="flex-1 text-lg font-bold sm:text-xl">{{ $question }}</span>
                        <span class="lp-faq-icon flex size-9 shrink-0 items-center justify-center border-2 border-forest text-xl font-bold group-open:bg-volt">+</span>
                    </summary>
                    <p class="lp-faq-body max-w-2xl pb-6 pl-11 leading-relaxed font-medium text-forest/80">{{ $answer }}</p>
                </details>
            @endforeach
        </div>
    </div>
</section>
