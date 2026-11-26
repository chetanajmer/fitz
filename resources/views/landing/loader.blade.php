@php
    // CSS-3D dumbbell: an octagonal bar plus stacked discs for each plate. [offset from centre in px, layers, size class, colour class].
    $plates = [
        [56, 8, 'is-big', 'is-clay'],
        [74, 6, 'is-small', 'is-volt'],
        [88, 3, 'is-collar', 'is-chalk'],
    ];
@endphp
{{-- Page loader: a gym roller shutter with a spinning 3D dumbbell doing reps. Shown only with JS and full motion;
     landing.js rolls the shutter up when the page is ready (never later than ~2.5 s). --}}
<div data-lp-loader class="lp-loader fixed inset-0 z-[90] flex-col items-center justify-center bg-forest text-chalk" aria-hidden="true">
    <div class="lp-db-scene">
        <div class="lp-db-lift">
            <div data-lp-db class="lp-db">
                @for ($face = 0; $face < 8; $face++)
                    <span class="lp-db-bar" style="--i: {{ $face }}"></span>
                @endfor
                @foreach ([-1, 1] as $side)
                    @foreach ($plates as [$offset, $layers, $size, $colour])
                        @for ($layer = 0; $layer < $layers; $layer++)
                            <span class="lp-db-plate {{ $size }} {{ $colour }}" style="--x: {{ $side * ($offset + $layer * 2) }}px"></span>
                        @endfor
                    @endforeach
                @endforeach
            </div>
        </div>
        <span class="lp-db-shadow"></span>
    </div>

    <p class="lp-display relative z-10 mt-8 text-5xl sm:mt-16">Fit<span class="lp-hl ml-0.5 text-forest">nix</span></p>

    <div class="mt-6 w-60">
        <p class="lp-mono flex justify-between text-chalk/70"><span data-lp-loader-status>Chalking up</span><span><span data-lp-loader-count>0</span>%</span></p>
        <div class="mt-2 h-2.5 bg-chalk/15"><div data-lp-loader-bar class="lp-loader-bar h-full origin-left bg-volt"></div></div>
    </div>

    {{-- Shutter handle along the bottom edge --}}
    <span class="absolute inset-x-0 bottom-0 flex h-6 items-center justify-center border-t-2 border-chalk/25 bg-forest-soft">
        <span class="h-2 w-24 rounded-full bg-chalk/40"></span>
    </span>
</div>
