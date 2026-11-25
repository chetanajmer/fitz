@php
    $steps = [
        ['Register your gym', 'Add your gym name, city and address. Your dashboard, gym page and QR codes are ready at once.'],
        ['Add plans & trainers', 'Set up membership plans, invite trainers and import your members from Excel or CSV.'],
        ['Members join & check in', 'Members sign up online or by QR, get the app and check in from day one.'],
        ['Grow', 'Collect fees, convert enquiries and keep members coming back with streaks and AI Coach.'],
    ];
@endphp
<section id="how-it-works" data-lp-section class="relative py-16 sm:py-24">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <p class="lp-mono">How it works</p>
        <h2 data-lp-slam class="lp-display mt-3 text-5xl sm:text-7xl">Four sets. <span class="lp-hl">Done.</span></h2>

        <svg data-lp-ecg data-lp-ecg-scrub class="lp-ecg mt-10 hidden h-16 w-full lg:block" viewBox="0 0 1200 64" preserveAspectRatio="none" aria-hidden="true">
            <path pathLength="1" d="M0 40 H140 l14 -30 l16 50 l14 -36 l10 16 H440 l14 -30 l16 50 l14 -36 l10 16 H740 l14 -30 l16 50 l14 -36 l10 16 H1040 l14 -30 l16 50 l14 -36 l10 16 H1200" fill="none" stroke="var(--color-clay)" stroke-width="4" stroke-linejoin="round" />
        </svg>

        <ol class="mt-8 grid gap-0 border-t-2 border-forest sm:grid-cols-2 lg:mt-2 lg:grid-cols-4 lg:border-t-0">
            @foreach ($steps as $i => [$title, $copy])
                <li class="lp-reveal border-b-2 border-forest py-6 sm:px-5 lg:border-b-0 lg:border-l-2 {{ $i % 2 === 1 ? 'sm:border-l-2' : '' }}" style="--d: {{ $i * 160 }}ms">
                    <span class="lp-set-no lp-display block text-8xl sm:text-9xl" style="--d: {{ $i * 160 + 200 }}ms">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                    <h3 class="lp-display mt-4 text-3xl">{{ $title }}</h3>
                    <p class="mt-2 font-medium text-forest/75">{{ $copy }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>
