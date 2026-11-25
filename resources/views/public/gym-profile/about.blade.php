{{-- About the gym, then its facilities as a bento grid. --}}
<section id="about" class="gp-section relative">
    <div class="mx-auto grid max-w-7xl gap-12 px-4 sm:px-6 lg:grid-cols-[0.9fr_1.1fr] lg:items-center lg:px-8">
        <div>
            <p class="gp-reveal gp-eyebrow">About the gym</p>
            <h2 class="gp-reveal gp-display mt-4 text-5xl text-white sm:text-6xl" style="--d: 80ms">Built for <span class="text-gym-orange">results.</span></h2>
            <p class="gp-reveal mt-6 text-lg leading-relaxed whitespace-pre-line text-gym-light/80" style="--d: 160ms">{{ $gym->description ?: 'Welcome to '.$gym->name.($location ? ' in '.$location : '').'. Visit us to start your fitness journey!' }}</p>
            <div class="gp-reveal mt-8 flex flex-wrap gap-3" style="--d: 240ms">
                <a href="{{ $joinUrl }}" class="gp-btn gp-btn-solid">Become a member <x-ui.icon name="arrow-right" class="size-5" /></a>
                <a href="{{ $gym->directionsUrl() }}" target="_blank" rel="noopener noreferrer" class="gp-btn gp-btn-ghost"><x-ui.icon name="map-pin" class="size-4" /> Get directions</a>
            </div>
        </div>

        {{-- Fact tiles about this gym --}}
        <div class="grid grid-cols-2 gap-4">
            <div class="gp-reveal gp-card col-span-2 flex items-center gap-5 p-6 sm:p-7">
                <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-gym-orange text-white shadow-[0_0_30px_-6px_rgb(255_107_0/0.8)]"><x-ui.icon name="clock" class="size-7" /></span>
                <div>
                    <p class="text-sm text-gym-muted">Open {{ $daysOpen }} {{ \Illuminate\Support\Str::plural('day', $daysOpen) }} a week</p>
                    <p class="mt-0.5 text-xl font-semibold text-white">{{ $today ? 'Today: '.$today['text'] : 'See our timings' }}</p>
                </div>
            </div>
            <div class="gp-reveal gp-card p-6" style="--d: 90ms">
                <x-ui.icon name="map-pin" class="size-7 text-gym-orange" />
                <p class="mt-4 text-sm text-gym-muted">Location</p>
                <p class="mt-0.5 font-semibold text-white">{{ $location ?: 'See map' }}</p>
            </div>
            <div class="gp-reveal gp-card p-6" style="--d: 180ms">
                <x-ui.icon name="trophy" class="size-7 text-gym-orange" />
                <p class="mt-4 text-sm text-gym-muted">Memberships</p>
                <p class="mt-0.5 font-semibold text-white">{{ $plans->isNotEmpty() ? $plans->count().' '.\Illuminate\Support\Str::plural('plan', $plans->count()) : 'Ask at the desk' }}</p>
            </div>
        </div>
    </div>
</section>

@if ($facilities->isNotEmpty())
    <section id="facilities" class="gp-section gp-surface relative overflow-hidden">
        <div aria-hidden="true" class="pointer-events-none absolute top-0 left-1/2 h-px w-2/3 -translate-x-1/2 bg-gradient-to-r from-transparent via-gym-orange/60 to-transparent"></div>
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-end justify-between gap-6">
                <div>
                    <p class="gp-reveal gp-eyebrow">Facilities</p>
                    <h2 class="gp-reveal gp-display mt-4 text-5xl text-white sm:text-6xl" style="--d: 80ms">Everything you <span class="text-gym-orange">need.</span></h2>
                </div>
                <p class="gp-reveal max-w-sm text-gym-muted" style="--d: 160ms">What you get at {{ $gym->name }}.</p>
            </div>

            <ul class="mt-12 grid grid-flow-dense auto-rows-[minmax(9rem,auto)] gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($facilities as $i => $f)
                    {{-- Every sixth tile (the 1st and 6th) is wide, so rows of four close evenly on large screens. --}}
                    <li @class([
                            'gp-reveal gp-card group relative flex flex-col justify-between overflow-hidden p-6',
                            'lg:col-span-2' => in_array($i % 6, [0, 5], true),
                        ]) style="--d: {{ ($i % 4) * 80 }}ms">
                        <div aria-hidden="true" class="pointer-events-none absolute -top-16 -right-16 size-40 rounded-full bg-gym-orange/0 blur-3xl transition duration-500 group-hover:bg-gym-orange/25"></div>
                        <span class="flex size-12 items-center justify-center rounded-xl bg-gym-orange/12 text-gym-orange ring-1 ring-gym-orange/30 transition duration-300 group-hover:scale-110 group-hover:bg-gym-orange group-hover:text-white">
                            <x-ui.icon :name="$f['icon']" class="size-6" />
                        </span>
                        <div class="mt-6">
                            <p class="text-xs font-medium text-gym-muted">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</p>
                            <p class="mt-1 text-lg font-semibold text-white">{{ $f['name'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@endif
