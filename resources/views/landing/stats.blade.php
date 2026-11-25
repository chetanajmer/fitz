{{-- Product facts (not customer metrics) on a split-flap scoreboard; landing.js rolls the digits in. --}}
<section id="stats" class="relative py-16 sm:py-24">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <p class="lp-mono">Scoreboard</p>
        <div data-lp-flaps class="mt-6 grid grid-cols-2 border-2 border-forest lg:grid-cols-4">
            @foreach ([
                ['14', 'Connected modules', 'From admissions to analytics.'],
                [$priceLabel, 'Per active member', 'Billed monthly. Expired and inactive members are free.'],
                ['₹0', 'Setup fee', 'No registration fee, no lock-in.'],
                ['3', 'Logins in one', 'Owners, trainers and members.'],
            ] as $i => [$value, $label, $text])
                <div class="lp-reveal border-forest p-5 sm:p-8 {{ $i % 2 === 0 ? 'border-r-2' : '' }} {{ $i < 2 ? 'border-b-2 lg:border-b-0' : '' }} {{ $i === 1 ? 'lg:border-r-2' : '' }}" style="--d: {{ $i * 90 }}ms">
                    <p class="lp-display flex gap-1 text-5xl sm:text-7xl" aria-label="{{ $value }}">
                        @foreach (mb_str_split($value) as $char)
                            <span class="lp-flap" aria-hidden="true" @if (ctype_digit($char)) data-lp-flap="{{ $char }}" @endif>{{ $char }}</span>
                        @endforeach
                    </p>
                    <p class="mt-5 font-bold">{{ $label }}</p>
                    <p class="mt-1 text-sm text-forest/70">{{ $text }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
