@php
    // Every card describes a Gymai module. NOTE: "AI Body Transformation" is not built in this codebase yet —
    // it is advertised as live at the product owner's request (see also views/landing/member-spotlight.blade.php).
    $features = [
        ['sparkles', 'AI Body Transformation', 'Members see an AI-powered preview of their transformation and track progress towards it, keeping them motivated.'],
        ['users', 'Gym Member Management', 'Profiles, goals, emergency contacts, trainer assignment and full history — plus bulk CSV/Excel import.'],
        ['card', 'Membership Plans', 'Monthly, quarterly, half-yearly, yearly or custom plans with discounts, renewals and expiry tracking.'],
        ['device', 'QR Self-Registration', 'New members scan your desk QR and register themselves from their phone — no forms for your staff to type.'],
        ['qr', 'QR Code Attendance','Members check in by QR or staff check them in manually. Duplicate-safe, with live "inside now" counts.'],
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
<section id="features" data-lp-section data-lp-spotlight class="lp-spotlight relative py-14 sm:py-20">
    <div aria-hidden="true" class="lp-dots pointer-events-none absolute inset-0 [mask-image:radial-gradient(ellipse_at_center,black_20%,transparent_70%)]"></div>
    <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center">
            <p class="lp-reveal text-sm font-semibold tracking-[0.2em] text-brand-500 uppercase">Features</p>
            <h2 data-split class="lp-reveal mt-3 text-3xl font-bold tracking-tight sm:text-5xl" style="--d: 80ms">Everything your gym needs. <span class="text-zinc-500">Nothing it doesn't.</span></h2>
            <p class="lp-reveal mt-5 text-zinc-400" style="--d: 160ms">Fourteen connected modules that replace registers, spreadsheets and scattered apps with one fast dashboard.</p>
        </div>

        <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($features as $i => [$icon, $title, $text])
                <article class="lp-reveal" style="--d: {{ ($i % 4) * 70 }}ms">
                    <div data-tilt-card data-lp-glow class="lp-glow-card group h-full rounded-2xl border border-ink-700 bg-ink-800/60 p-6 backdrop-blur-sm transition-[border-color,box-shadow] duration-300 hover:border-brand-600/50 hover:shadow-[0_20px_50px_-20px_rgb(238_68_68/0.45)]">
                        <div class="flex size-12 items-center justify-center rounded-xl border border-brand-600/25 bg-gradient-to-br from-brand-600/25 to-transparent text-brand-400 transition duration-300 group-hover:border-brand-500/60 group-hover:shadow-[0_0_28px_-4px_rgb(238_68_68/0.8)]">
                            <x-ui.icon :name="$icon" class="lp-icon-bounce size-6" />
                        </div>
                        <h3 class="mt-5 font-semibold">{{ $title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-zinc-400">{{ $text }}</p>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
