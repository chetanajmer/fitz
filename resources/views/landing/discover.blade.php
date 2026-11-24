@php
    // Demo locality: your gym (the raised block) and nearby gyms. [key, name, distance, detail, x, y on the 900×700 map].
    $yourGym = ['yours', 'Iron Temple Fitness', 'Your gym', 'Profile, photos, plans and online joining', 450, 350];
    $nearby = [
        ['fitzone', 'FitZone', '1.1 km', 'Open 5 AM – 11 PM', 655, 175],
        ['pulse', 'Pulse Gym', '1.8 km', 'CrossFit · Zumba', 720, 480],
        ['core', 'Core Studio', '2.3 km', 'Yoga · Pilates', 225, 500],
        ['beast', 'Beast Mode', '3.0 km', 'Powerlifting', 175, 215],
    ];
    $places = array_merge([$yourGym], $nearby);
    $searches = ['Gyms in Pune · Baner', 'Gyms in Indore · Vijay Nagar', 'Gyms in Jaipur · Malviya Nagar', 'Gyms in Kochi · Kakkanad'];
    // Streets of the stylised map (SVG paths in the 900×700 plane). [path, width].
    $streets = [
        ['M-50 120 L950 40', 14], ['M-50 610 L950 520', 14], ['M120 -50 L330 760', 12], ['M600 -50 L860 760', 12],
        ['M-50 330 C200 300 420 420 950 300', 10], ['M380 -50 C420 200 360 480 470 760', 8],
        ['M-50 450 L950 400', 4], ['M-50 230 L950 170', 4], ['M240 -50 L520 760', 4], ['M720 -50 L620 760', 4],
        ['M-50 50 L380 760', 3], ['M480 -50 L950 260', 3], ['M50 760 L560 -50', 3], ['M700 760 L950 560', 3],
        ['M-50 700 L300 560', 3], ['M820 -50 L950 120', 3],
    ];
@endphp
<section id="discover" data-lp-section data-lp-discover class="relative overflow-hidden border-y-2 border-forest bg-volt py-16 sm:py-24">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="grid items-end gap-6 lg:grid-cols-[1fr_auto]">
            <div>
                <p class="lp-mono">Gym discovery &amp; online joining</p>
                <h2 data-lp-slam class="lp-display mt-3 text-6xl sm:text-8xl">Get found. <br class="hidden sm:block"><span class="lp-outline">Get joined.</span></h2>
            </div>
            <div class="flex flex-wrap gap-4">
                <a href="{{ route('gyms.browse') }}" class="lp-btn lp-btn-solid">Browse gyms <x-ui.icon name="arrow-right" class="size-4" /></a>
                <a href="{{ route('member.join') }}" class="lp-btn lp-btn-ghost">Register as Member</a>
            </div>
        </div>
        <p class="mt-6 max-w-2xl text-lg font-medium">Every gym on Gymai gets a public profile with photos, facilities, timings and plans. People find you by city and locality, then join online — their details land straight in your panel.</p>

        <div class="mt-10 grid gap-8 lg:grid-cols-[22rem_1fr] lg:items-center">
            {{-- Gyms in this locality; hovering one lights up its pin on the map (and the other way round). --}}
            <ul class="order-2 border-t-2 border-forest lg:order-1">
                @foreach ($places as [$key, $name, $distance, $detail])
                    <li>
                        <button type="button" data-lp-map-key="{{ $key }}" class="lp-map-item group flex w-full items-baseline gap-4 border-b-2 border-forest px-2 py-3.5 text-left">
                            <span class="lp-display flex-1 text-3xl sm:text-4xl">{{ $name }}</span>
                            <span class="lp-mono shrink-0 {{ $key === 'yours' ? 'bg-forest px-1.5 py-0.5 text-volt' : '' }}">{{ $distance }}</span>
                        </button>
                    </li>
                @endforeach
            </ul>

            {{-- Tilted 3D street map (stylised SVG, not a real map). --}}
            <div data-lp-map class="lp-map-stage relative order-1 h-[24rem] select-none sm:h-[32rem] lg:order-2 lg:h-[36rem]">
                <div class="lp-map-fit absolute inset-0">
                    <div data-lp-map-plane class="lp-map-plane">
                        <svg viewBox="0 0 900 700" class="absolute inset-0 size-full" aria-hidden="true">
                            <rect x="520" y="560" width="200" height="120" rx="18" fill="var(--color-forest)" opacity="0.07" />
                            <path d="M-50 560 C150 520 250 640 420 600" stroke="var(--color-clay)" stroke-opacity="0.45" stroke-width="18" fill="none" />
                            @foreach ($streets as [$path, $width])
                                <path d="{{ $path }}" stroke="var(--color-forest)" stroke-opacity="{{ $width > 6 ? 0.55 : 0.3 }}" stroke-width="{{ $width }}" stroke-linecap="round" fill="none" />
                            @endforeach
                        </svg>

                        {{-- Your gym: an extruded block --}}
                        <div data-lp-map-building data-lp-map-key="yours" class="lp-map-building" style="left: {{ $yourGym[4] - 75 }}px; top: {{ $yourGym[5] - 25 }}px">
                            <span class="lp-face is-top"></span><span class="lp-face is-front"></span><span class="lp-face is-back"></span><span class="lp-face is-left"></span><span class="lp-face is-right"></span>
                        </div>

                        @foreach ($places as [$key, $name, $distance, $detail, $x, $y])
                            {{-- Pins counter-rotate so they always stand upright facing the viewer. --}}
                            <div class="lp-map-pin" style="left: {{ $x }}px; top: {{ $y }}px; {{ $key === 'yours' ? '--lift: var(--h);' : '' }}">
                                <div data-lp-map-key="{{ $key }}" data-lp-map-pin class="lp-map-pin-body {{ $key === 'yours' ? 'is-yours' : '' }}">
                                    <span class="lp-map-label">
                                        <span class="lp-display block text-lg leading-none">{{ $name }}</span>
                                        <span class="lp-map-detail lp-mono block text-[0.6rem]">{{ $detail }}</span>
                                    </span>
                                    <span class="lp-map-stem"></span>
                                    <span class="lp-map-dot"></span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <canvas data-lp-map-trail class="pointer-events-none absolute inset-0 size-full" aria-hidden="true"></canvas>

                <div class="absolute top-3 left-3 flex items-center gap-3 border-2 border-forest bg-chalk px-4 py-2.5 text-sm font-bold sm:top-4 sm:left-4" aria-hidden="true">
                    <x-ui.icon name="search" class="size-4 shrink-0" />
                    <span data-lp-search data-lp-search-items='@json($searches)'>{{ $searches[0] }}</span><span class="lp-caret -ml-2 inline-block h-4 w-0.5 bg-forest"></span>
                </div>
            </div>
        </div>
    </div>
</section>
