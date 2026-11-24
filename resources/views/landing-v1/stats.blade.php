{{-- Product facts (not customer metrics), counted up on scroll. --}}
<section id="stats" class="lp-noise relative overflow-hidden border-y border-ink-700 bg-ink-900 py-12 sm:py-16">
    <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_center,rgb(238_68_68/0.14),transparent_65%)]"></div>
    <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-[linear-gradient(rgb(255_255_255/0.03)_1px,transparent_1px),linear-gradient(90deg,rgb(255_255_255/0.03)_1px,transparent_1px)] bg-[size:72px_72px] [mask-image:radial-gradient(ellipse_at_center,black,transparent_75%)]"></div>
    <div class="relative mx-auto grid max-w-6xl grid-cols-2 gap-4 px-4 sm:px-6 lg:grid-cols-4 lg:px-8">
        @foreach ([
            ['14', '', 'Connected modules', 'Everything from admissions to analytics.'],
            [(string) (int) $price, '₹', 'Per active member', 'Billed monthly. Pay only for active members.'],
            ['0', '₹', 'Setup & registration fee', 'Start without any upfront cost.'],
            ['3', '', 'Dedicated portals', 'For owners, trainers and members.'],
        ] as $i => [$value, $prefix, $label, $text])
            <div class="lp-reveal group rounded-3xl border border-white/10 bg-white/[0.03] p-6 text-center backdrop-blur-xl transition hover:border-brand-600/40 sm:p-8" style="--d: {{ $i * 90 }}ms">
                <p class="text-4xl font-bold tracking-tight text-white drop-shadow-[0_0_24px_rgb(238_68_68/0.55)] sm:text-6xl">{{ $prefix }}<span data-count="{{ $value }}">{{ $value }}</span></p>
                <p class="mt-3 text-sm font-semibold">{{ $label }}</p>
                <p class="mt-1 text-xs leading-relaxed text-zinc-500">{{ $text }}</p>
            </div>
        @endforeach
    </div>
</section>
