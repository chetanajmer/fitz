{{-- Hero: who and where the gym is, today's status and the main actions; the photo carries floating fact cards. --}}
<section id="top" data-gp-hero class="relative isolate overflow-hidden pt-28 pb-16 sm:pt-32 lg:flex lg:min-h-[100svh] lg:items-center lg:pb-24">
    {{-- Backdrop: soft orange lighting over a faint grid. --}}
    <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10">
        <div class="gp-grid-bg absolute inset-0 [mask-image:radial-gradient(ellipse_at_center,black_30%,transparent_75%)]"></div>
        <div class="absolute -top-40 -left-40 size-[36rem] rounded-full bg-gym-orange/20 blur-[120px]"></div>
        <div class="absolute right-0 bottom-0 size-[28rem] rounded-full bg-gym-orange/10 blur-[110px]"></div>
    </div>

    <div class="mx-auto grid w-full max-w-7xl items-center gap-14 px-4 sm:px-6 lg:grid-cols-[1.1fr_0.9fr] lg:px-8">
        <div class="gp-hero-in">
            <div class="flex flex-wrap items-center gap-2">
                @if ($status)
                    <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-semibold text-white">
                        <span class="relative flex size-2.5">
                            @if ($status['live'])<span class="gp-ping absolute inset-0 rounded-full bg-emerald-400"></span>@endif
                            <span class="relative size-2.5 rounded-full {{ $status['live'] ? 'bg-emerald-400' : 'bg-gym-orange' }}"></span>
                        </span>
                        {{ $status['label'] }} <span class="font-normal text-gym-muted">· {{ $status['detail'] }}</span>
                    </span>
                @endif
                @if ($location)
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-medium text-gym-light">
                        <x-ui.icon name="map-pin" class="size-3.5 text-gym-orange" /> {{ $location }}
                    </span>
                @endif
            </div>

            <h1 class="gp-display mt-6 text-[clamp(3.25rem,9vw,7.5rem)] text-white">
                {{ $gym->name }}<span class="text-gym-orange">.</span>
            </h1>

            <p class="mt-5 max-w-xl text-lg text-gym-light/85 sm:text-xl">
                @if ($tagline)
                    {{ $tagline }}
                @else
                    Train harder. Get stronger. <span class="text-gym-orange">Start today.</span>
                @endif
            </p>
            @if ($gym->description)
                <p class="mt-3 max-w-xl text-gym-muted">{{ \Illuminate\Support\Str::limit($gym->description, 180) }}</p>
            @endif

            <div class="mt-9 flex flex-wrap gap-3">
                <a href="{{ $joinUrl }}" class="gp-btn gp-btn-solid px-7">Join this gym <x-ui.icon name="arrow-right" class="size-5" /></a>
                <a href="#enquire" class="gp-btn gp-btn-ghost px-7">Enquire now</a>
            </div>

            <div class="mt-9 flex flex-wrap items-center gap-x-8 gap-y-4 border-t border-white/10 pt-6 text-sm">
                @if ($fromPrice !== null)
                    <div>
                        <p class="text-gym-muted">Plans from</p>
                        <p class="mt-0.5 text-2xl font-bold text-white">{{ money($fromPrice, false) }}</p>
                    </div>
                @endif
                @if ($today)
                    <div>
                        <p class="text-gym-muted">Today</p>
                        <p class="mt-0.5 text-2xl font-bold text-white">{{ $today['text'] }}</p>
                    </div>
                @endif
                @if ($gym->phone)
                    <a href="tel:{{ $gym->phone }}" class="group">
                        <p class="text-gym-muted">Call us</p>
                        <p class="mt-0.5 text-2xl font-bold text-white transition group-hover:text-gym-orange">{{ $gym->phone }}</p>
                    </a>
                @endif
            </div>
        </div>

        {{-- Photo with floating fact cards --}}
        <div class="relative mx-auto w-full max-w-md lg:max-w-none">
            <div data-gp-parallax="0.08" class="relative">
                <div aria-hidden="true" class="absolute -inset-6 -z-10 rounded-[2.5rem] bg-gradient-to-br from-gym-orange/40 via-gym-orange/5 to-transparent blur-2xl"></div>
                <div class="relative aspect-[4/5] overflow-hidden rounded-[2rem] border border-white/10 bg-gym-card">
                    <img src="{{ $heroImage }}" alt="{{ $heroIsOwn ? $gym->name : '' }}" fetchpriority="high" decoding="async" width="640" height="800"
                         class="size-full object-cover {{ $heroIsOwn ? '' : 'object-[50%_20%] grayscale-[35%]' }}">
                    <div aria-hidden="true" class="absolute inset-0 bg-gradient-to-t from-gym-black via-gym-black/10 to-transparent"></div>
                    <div aria-hidden="true" class="absolute inset-0 bg-[radial-gradient(ellipse_at_100%_0%,rgb(255_107_0/0.35),transparent_55%)] mix-blend-soft-light"></div>
                    <div class="absolute right-5 bottom-5">
                        <a href="{{ $joinUrl }}" class="flex size-12 shrink-0 items-center justify-center rounded-full bg-gym-orange text-white transition hover:scale-105 hover:bg-gym-orange-hover" aria-label="Join {{ $gym->name }} online">
                            <x-ui.icon name="arrow-right" class="size-5 -rotate-45" />
                        </a>
                    </div>
                </div>
            </div>

            @if ($status)
                <div data-gp-tilt="18" class="absolute top-8 -left-4 sm:-left-10">
                    <div class="gp-float gp-glass flex items-center gap-3 rounded-2xl px-4 py-3 shadow-2xl shadow-black/50" style="--d: -1s">
                        <span class="flex size-10 items-center justify-center rounded-xl {{ $status['live'] ? 'bg-emerald-400/15 text-emerald-400' : 'bg-gym-orange/15 text-gym-orange' }}"><x-ui.icon name="clock" class="size-5" /></span>
                        <div>
                            <p class="text-sm font-semibold text-white">{{ $status['label'] }}</p>
                            <p class="text-xs text-gym-muted">{{ $status['detail'] }}</p>
                        </div>
                    </div>
                </div>
            @endif

            @if ($fromPrice !== null)
                <div data-gp-tilt="-14" class="absolute top-1/2 -right-3 sm:-right-8">
                    <div class="gp-float gp-glass rounded-2xl px-4 py-3 shadow-2xl shadow-black/50" style="--d: -3s">
                        <p class="text-xs text-gym-muted">Memberships from</p>
                        <p class="text-xl font-bold text-white">{{ money($fromPrice, false) }}</p>
                        <p class="mt-1 text-xs font-medium text-gym-orange">{{ $plans->count() }} {{ \Illuminate\Support\Str::plural('plan', $plans->count()) }} available</p>
                    </div>
                </div>
            @elseif ($facilities->isNotEmpty())
                <div data-gp-tilt="-14" class="absolute top-1/2 -right-3 sm:-right-8">
                    <div class="gp-float gp-glass rounded-2xl px-4 py-3 shadow-2xl shadow-black/50" style="--d: -3s">
                        <p class="text-xs text-gym-muted">Facilities</p>
                        <p class="text-xl font-bold text-white">{{ $facilities->count() }}+</p>
                    </div>
                </div>
            @endif

            <div data-gp-tilt="10" class="absolute -bottom-6 left-6 hidden sm:block">
                <div class="gp-float gp-glass flex items-center gap-3 rounded-2xl px-4 py-3 shadow-2xl shadow-black/50" style="--d: -2s">
                    <span class="flex size-10 items-center justify-center rounded-xl bg-gym-orange text-white"><x-ui.icon name="qr" class="size-5" /></span>
                    <div>
                        <p class="text-sm font-semibold text-white">Member app included</p>
                        <p class="text-xs text-gym-muted">QR check-in · workouts · diet</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
