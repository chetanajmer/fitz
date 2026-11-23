@php
    $reasons = [
        ['bolt', 'Easy Gym Management', 'A clean dashboard your front desk learns in minutes — no training manuals needed.'],
        ['clock', 'Save Time', 'Check-ins, renewals and receipts take seconds instead of pages of registers.'],
        ['sparkles', 'Smart Alerts', 'Expiring memberships, follow-ups due and at-risk streaks surface on your dashboard every day.'],
        ['device', 'Better Member Experience', 'Members get their own portal and app with QR check-in, plans, streaks and receipts.'],
        ['trending', 'Business Growth', 'Convert more enquiries, renew on time and keep members coming back with streaks.'],
        ['chart', 'Real-Time Analytics', 'Revenue, attendance and member growth update live as your gym runs.'],
        ['shield', 'Secure Data Management', 'Every gym\'s data is isolated, and staff only see what their role allows.'],
    ];
@endphp
<section id="about" data-lp-section class="relative overflow-hidden py-14 sm:py-20">
    {{-- Section identity: drifting aurora blobs with depth parallax. --}}
    <div aria-hidden="true" class="pointer-events-none absolute inset-0">
        <div data-parallax="0.25" class="absolute top-20 -right-32 size-96 rounded-full bg-brand-600/15 blur-3xl"></div>
        <div data-parallax="-0.2" class="absolute bottom-0 left-1/3 size-80 rounded-full bg-rose-900/25 blur-3xl"></div>
    </div>
    <div class="relative mx-auto grid max-w-7xl gap-14 px-4 sm:px-6 lg:grid-cols-[0.9fr_1.1fr] lg:px-8">
        <div class="lg:sticky lg:top-32 lg:self-start">
            <p class="lp-reveal text-sm font-semibold tracking-[0.2em] text-brand-500 uppercase">Why Gymai</p>
            <h2 data-split class="lp-reveal mt-3 text-3xl font-bold tracking-tight sm:text-5xl" style="--d: 80ms">Built for gym owners who'd rather coach than do paperwork</h2>
            <p class="lp-reveal mt-6 leading-relaxed text-zinc-400" style="--d: 160ms">
                Gymai is made for independent gyms and fitness studios. We took the daily jobs of running a gym — admissions, fees, attendance, renewals and follow-ups —
                and turned them into a few clicks, so you spend your time on members, not registers.
            </p>
            <div class="lp-reveal mt-8" style="--d: 240ms"><a href="{{ $ctaUrl }}" data-magnetic class="btn-primary lp-shine-btn px-6 py-3">{{ $ctaLabel }} <x-ui.icon name="arrow-right" class="size-4" /></a></div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($reasons as $i => [$icon, $title, $text])
                <div data-lp-glow class="lp-glow-card lp-reveal rounded-2xl border border-ink-700 bg-ink-800/50 p-6 transition hover:border-brand-600/40 {{ $i === 0 ? 'sm:col-span-2' : '' }}" style="--d: {{ ($i % 2) * 90 }}ms">
                    <span class="flex size-10 items-center justify-center rounded-xl bg-brand-600/15 text-brand-400"><x-ui.icon :name="$icon" class="size-5" /></span>
                    <h3 class="mt-4 font-semibold">{{ $title }}</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-zinc-400">{{ $text }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
