@php
    $reasons = [
        ['UPI, cash, card, bank', 'Take fees the way your members pay — full or in parts — and print receipts.'],
        ['WhatsApp in one tap', 'Pre-written messages for leads, renewals and members who stopped coming.'],
        ['Code sign-in', 'Members and staff sign in with a one-time code by SMS or email.'],
        ['Learn it in minutes', 'A clean panel your front desk understands without a manual.'],
        ['Your data, locked', 'Every gym\'s data is kept separate, and staff only see what their role allows.'],
        ['Simple monthly bill', 'One rupee price per active member. See every invoice in your panel.'],
    ];
@endphp
<section id="about" data-lp-section class="relative bg-forest py-16 text-chalk sm:py-24">
    <div class="mx-auto grid max-w-7xl gap-12 px-4 sm:px-6 lg:grid-cols-[0.8fr_1.2fr] lg:px-8">
        <div class="lg:sticky lg:top-32 lg:self-start">
            <p class="lp-mono text-volt">Why Fitnix</p>
            <h2 data-lp-slam class="lp-display mt-3 text-5xl sm:text-7xl">Built for Indian gyms. <span class="text-volt">Not adapted.</span></h2>
            <p class="mt-6 max-w-md font-medium text-chalk/75">Fitnix is made for independent gyms and fitness studios. We took the daily jobs — admissions, fees, attendance, renewals and follow-ups — and made them a few taps, so you spend your time on members, not registers.</p>
            <a href="{{ $ctaUrl }}" class="lp-btn lp-btn-volt mt-8">{{ $ctaLabel }} <x-ui.icon name="arrow-right" class="size-4" /></a>
        </div>

        <ul class="border-t-2 border-chalk/25">
            @foreach ($reasons as $i => [$title, $copy])
                <li class="lp-row lp-reveal grid gap-1 border-b-2 border-chalk/25 px-3 py-6 sm:grid-cols-[3rem_1fr_1fr] sm:items-center sm:gap-6" style="--d: {{ ($i % 3) * 80 }}ms">
                    <span class="lp-mono">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                    <span class="lp-display text-3xl sm:text-4xl">{{ $title }}</span>
                    <span class="text-sm font-medium opacity-80">{{ $copy }}</span>
                </li>
            @endforeach
        </ul>
    </div>
</section>
