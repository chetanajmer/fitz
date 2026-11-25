{{--
    Left half of the split sign-up cards (register gym, join a gym): brand, a photo and a short story per step.
    The photos cross-fade as `current` changes. `position` frames each photo (the short banner on phones, then the tall
    panel from lg up); `tone` evens out their exposure. On desktop a photo starts below the brand and its top fades into
    black, so the athlete's head never sits behind the logo. Steps that share a photo keep it on screen between them.
--}}
@props([
    /** @var array<int|string, array{image: string, position: string, tone: string, eyebrow: string, title: string, accent: string, text: string}> */
    'panels',
    'current',
    /** @var list<array{0: string, 1: string}> [icon, label] */
    'perks' => [],
    'tagline' => 'Powering smarter fitness businesses',
])
@php($currentImage = $panels[$current]['image'])
<aside {{ $attributes->merge(['class' => 'relative isolate flex min-h-72 flex-col justify-between gap-10 overflow-hidden p-6 sm:p-8 lg:min-h-[46rem] lg:p-10']) }}>
    @foreach (collect($panels)->unique('image') as $key => $panel)
        <img wire:key="panel-photo-{{ $key }}" src="{{ asset($panel['image']).'?v='.@filemtime(public_path($panel['image'])) }}" alt="" aria-hidden="true"
             @if ($loop->first) fetchpriority="high" @endif
             @class([
                 'absolute inset-0 -z-20 size-full object-cover grayscale transition duration-700 ease-out lg:top-24 lg:h-[calc(100%-6rem)]',
                 $panel['position'],
                 $panel['tone'],
                 'scale-100 opacity-100' => $panel['image'] === $currentImage,
                 'scale-105 opacity-0' => $panel['image'] !== $currentImage,
             ])>
    @endforeach
    {{-- One look for every photo: black-and-white with a warm orange rim, then darkened where the text sits. --}}
    <div aria-hidden="true" class="absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_90%_10%,rgb(255_107_0/0.55),transparent_55%),radial-gradient(ellipse_at_0%_100%,rgb(255_107_0/0.45),transparent_50%)] mix-blend-soft-light"></div>
    <div aria-hidden="true" class="absolute inset-0 -z-10 bg-gradient-to-t from-gym-black from-10% via-gym-black/60 via-45% to-transparent"></div>
    {{-- Phones: a soft shade behind the logo. Desktop: fades the photo's top edge into the black behind the brand. --}}
    <div aria-hidden="true" class="absolute inset-x-0 top-0 -z-10 h-32 bg-gradient-to-b from-gym-black/85 to-transparent lg:hidden"></div>
    <div aria-hidden="true" class="absolute inset-x-0 top-0 -z-10 hidden h-48 bg-gradient-to-b from-gym-black from-50% to-transparent lg:block"></div>
    <div aria-hidden="true" class="absolute inset-0 -z-10 hidden bg-gradient-to-r from-transparent via-transparent to-gym-black/60 lg:block"></div>

    <a href="{{ route('home') }}" class="flex items-center gap-3 self-start">
        <span class="flex size-12 items-center justify-center rounded-xl bg-gym-orange text-white shadow-[0_0_28px_-4px_rgb(255_107_0/0.8)]">
            <x-ui.icon name="dumbbell" class="size-7" />
        </span>
        <span>
            <span class="block text-2xl leading-tight font-semibold text-white">Gymai</span>
            <span class="block text-xs text-gym-light/80 sm:text-sm">{{ $tagline }}</span>
        </span>
    </a>

    <div>
        <span class="block h-1 w-16 rounded-full bg-gym-orange"></span>
        @foreach ($panels as $key => $panel)
            <div wire:key="panel-story-{{ $key }}" @class(['mt-5 motion-safe:animate-[lp-faq-in_0.5s_cubic-bezier(0.2,0.7,0.2,1)]', 'hidden' => $key !== $current])>
                <p class="text-xs font-semibold tracking-[0.2em] text-gym-orange uppercase">{{ $panel['eyebrow'] }}</p>
                <h2 class="mt-2 text-4xl leading-[1.05] font-bold tracking-tight text-white sm:text-5xl">
                    {{ $panel['title'] }}<span class="block text-gym-orange">{{ $panel['accent'] }}</span>
                </h2>
                <p class="mt-3 max-w-sm text-sm text-gym-light/80 sm:text-base">{{ $panel['text'] }}</p>
            </div>
        @endforeach

        @if ($perks)
            <ul class="mt-8 hidden divide-x divide-white/15 sm:flex">
                @foreach ($perks as [$icon, $label])
                    <li class="flex items-center gap-2.5 px-4 text-xs font-medium text-gym-light/90 first:pl-0 last:pr-0">
                        <x-ui.icon :name="$icon" class="size-5 shrink-0 text-gym-orange" /> {{ $label }}
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</aside>
