@php
    // Every card describes a module that exists in the Gymai panel today.
    $features = [
        ['users', 'Gym Member Management', 'Profiles, goals, emergency contacts, trainer assignment and full history — plus bulk CSV/Excel import.'],
        ['card', 'Membership Plans', 'Monthly, quarterly, half-yearly, yearly or custom plans with discounts, renewals and expiry tracking.'],
        ['qr', 'QR Code Attendance', 'Members check in by QR or staff check them in manually. Duplicate-safe, with live "inside now" counts.'],
        ['cash', 'Fee & Payment Management', 'Record cash, UPI, card or bank payments — full or partial — and print receipts and invoices.'],
        ['whistle', 'Workout Plans', 'Build workout plans section by section, or start from a ready library and assign them to members.'],
        ['tag', 'Diet Management', 'Create diet charts with meals, timings and calories. Members see them in their own portal and app.'],
        ['user', 'Trainer Management', 'Give trainers their own login and assign members. Trainers only see the members assigned to them.'],
        ['phone', 'WhatsApp Quick Messages', 'Reach leads and at-risk members on WhatsApp in one tap, with the message already written for you.'],
        ['chart', 'Reports & Analytics', 'Member growth, revenue, daily and monthly attendance, streaks and pending fees at a glance.'],
        ['inbox', 'Enquiry Management', 'Capture walk-ins, calls and QR sign-ups, schedule follow-ups and convert leads into members in a click.'],
        ['shield', 'Staff Roles & Access', 'Role-based access for owners and trainers, so every person sees exactly what they should.'],
        ['palette', 'Gym Branding & Customization', 'Your logo, colours, photos, facilities and opening hours on your own public gym page and QR codes.'],
    ];
@endphp
<section id="features" data-lp-section class="relative py-24 sm:py-32">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center">
            <p class="lp-reveal text-sm font-semibold tracking-[0.2em] text-brand-500 uppercase">Features</p>
            <h2 class="lp-reveal mt-3 text-3xl font-bold tracking-tight sm:text-5xl" style="--d: 80ms">Everything your gym needs. <span class="text-zinc-500">Nothing it doesn't.</span></h2>
            <p class="lp-reveal mt-5 text-zinc-400" style="--d: 160ms">Twelve connected modules that replace registers, spreadsheets and scattered apps with one fast dashboard.</p>
        </div>

        <div class="mt-16 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($features as $i => [$icon, $title, $text])
                <article data-lp-glow class="lp-glow-card lp-reveal group rounded-2xl border border-ink-700 bg-ink-800/60 p-6 transition duration-300 hover:-translate-y-1 hover:border-brand-600/40" style="--d: {{ ($i % 4) * 70 }}ms">
                    <div class="flex size-12 items-center justify-center rounded-xl border border-brand-600/25 bg-gradient-to-br from-brand-600/25 to-transparent text-brand-400 transition duration-300 group-hover:scale-110 group-hover:shadow-[0_0_28px_-4px_rgb(238_68_68/0.8)]">
                        <x-ui.icon :name="$icon" class="size-6" />
                    </div>
                    <h3 class="mt-5 font-semibold">{{ $title }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-zinc-400">{{ $text }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>
