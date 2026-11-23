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
<section id="about" data-lp-section class="relative py-24 sm:py-32">
    <div class="mx-auto grid max-w-7xl gap-14 px-4 sm:px-6 lg:grid-cols-[0.9fr_1.1fr] lg:px-8">
        <div class="lg:sticky lg:top-32 lg:self-start">
            <p class="lp-reveal text-sm font-semibold tracking-[0.2em] text-brand-500 uppercase">Why Gymai</p>
            <h2 class="lp-reveal mt-3 text-3xl font-bold tracking-tight sm:text-5xl" style="--d: 80ms">Built for gym owners who'd rather coach than do paperwork</h2>
            <p class="lp-reveal mt-6 leading-relaxed text-zinc-400" style="--d: 160ms">
                Gymai is made for independent gyms and fitness studios. We took the daily jobs of running a gym — admissions, fees, attendance, renewals and follow-ups —
                and turned them into a few clicks, so you spend your time on members, not registers.
            </p>
            <a href="{{ $ctaUrl }}" class="lp-reveal btn-primary mt-8 px-6 py-3" style="--d: 240ms">{{ $ctaLabel }} <x-ui.icon name="arrow-right" class="size-4" /></a>
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
