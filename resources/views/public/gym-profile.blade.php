@use('Illuminate\Support\Carbon')
@use('Illuminate\Support\Str')
@php
    /*
     * Everything on this page comes from the gym's own profile (settings, plans, photos). Sections without data
     * are left out, so a new gym gets a short, complete page rather than empty boxes.
     */
    $social = array_filter($gym->setting('social', []));
    $whatsapp = ! empty($social['whatsapp']) ? 'https://wa.me/'.preg_replace('/\D/', '', $social['whatsapp']) : null;
    $socialLinks = collect(['website' => 'Website', 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'youtube' => 'YouTube'])
        ->filter(fn ($label, $key) => ! empty($social[$key]) && filter_var($social[$key], FILTER_VALIDATE_URL))
        ->map(fn ($label, $key) => ['label' => $label, 'url' => $social[$key]]);

    $tagline = $gym->setting('tagline');
    $location = collect([$gym->locality?->name, $gym->city])->filter()->unique()->implode(', ');
    $fullAddress = collect([$gym->address, $gym->locality?->name, $gym->city])->filter()
        ->reject(fn ($part, $i) => $i > 0 && Str::contains((string) $gym->address, $part, true))->implode(', ');

    // Opening hours, today's status (in the app's timezone) and a short summary.
    $now = now();
    $todayKey = strtolower($now->format('l'));
    $time = fn (string $hhmm) => Carbon::createFromFormat('H:i', $hhmm)->format('g:i A');
    $hours = collect($gym->setting('opening_hours', []))->map(fn (array $h, string $day) => [
        'day' => ucfirst($day),
        'short' => Str::substr(ucfirst($day), 0, 3),
        'closed' => (bool) $h['closed'],
        'text' => $h['closed'] ? 'Closed' : $time($h['open']).' – '.$time($h['close']),
        'open' => $h['open'] ?? null,
        'close' => $h['close'] ?? null,
        'today' => $day === $todayKey,
    ]);
    $today = $hours->get($todayKey);
    $openNow = $today && ! $today['closed'] && $now->format('H:i') >= $today['open'] && $now->format('H:i') < $today['close'];
    $daysOpen = $hours->reject(fn ($h) => $h['closed'])->count();
    $status = match (true) {
        ! $today => null,
        $openNow => ['label' => 'Open now', 'detail' => 'Closes at '.$time($today['close']), 'live' => true],
        ! $today['closed'] && $now->format('H:i') < $today['open'] => ['label' => 'Opens today', 'detail' => 'at '.$time($today['open']), 'live' => false],
        default => ['label' => 'Closed now', 'detail' => 'See opening hours', 'live' => false],
    };

    // Plans, their monthly equivalent and the best-value one (lowest cost per month, when plans differ).
    $planRows = $plans->map(function ($plan) {
        $months = $plan->duration_type->months() ?? ($plan->duration_days ? $plan->duration_days / 30.4 : null);

        return ['plan' => $plan, 'months' => $months, 'perMonth' => $months ? (float) $plan->price / $months : null];
    });
    $priced = $planRows->filter(fn ($r) => $r['perMonth'] !== null);
    $bestValueId = $priced->count() > 1 && $priced->min('perMonth') < $priced->max('perMonth')
        ? $priced->sortBy('perMonth')->first()['plan']->id : null;
    $fromPrice = $plans->min('price');

    // Facilities with an icon picked from their wording.
    $facilityIcon = fn (string $name) => match (true) {
        Str::contains($name, ['weight', 'dumbbell', 'strength', 'rack', 'power'], true) => 'dumbbell',
        Str::contains($name, ['cardio', 'treadmill', 'cycle', 'hiit', 'crossfit'], true) => 'fire',
        Str::contains($name, ['zumba', 'aerobic', 'dance', 'class'], true) => 'bolt',
        Str::contains($name, ['personal', 'trainer', 'coach'], true) => 'whistle',
        Str::contains($name, ['diet', 'nutrition', 'supplement', 'cafe'], true) => 'meal',
        Str::contains($name, ['yoga', 'meditation', 'stretch'], true) => 'moon',
        Str::contains($name, ['steam', 'sauna', 'spa', 'shower'], true) => 'sun',
        Str::contains($name, ['locker', 'cctv', 'security'], true) => 'shield',
        Str::contains($name, ['parking'], true) => 'map-pin',
        Str::contains($name, ['ac', 'air'], true) => 'sparkles',
        default => 'star',
    };
    $facilities = collect($gym->setting('facilities', []))->filter()->values()->map(fn ($f) => ['name' => $f, 'icon' => $facilityIcon($f)]);

    // The hero photo: the gym's banner, else its first gallery photo, else a neutral training shot.
    $heroImage = $gym->bannerUrl() ?? $photos->first()?->url() ?? asset('images/gym-page/hero.webp');
    $heroIsOwn = $gym->bannerUrl() || $photos->isNotEmpty();

    $joinUrl = route('member.join');
    $sections = collect([
        'about' => 'About',
        'facilities' => $facilities->isNotEmpty() ? 'Facilities' : null,
        'plans' => $plans->isNotEmpty() ? 'Plans' : null,
        'gallery' => $photos->isNotEmpty() ? 'Gallery' : null,
        'visit' => 'Timings',
        'faq' => 'FAQ',
        'enquire' => 'Contact',
    ])->filter();

    $highlights = collect([
        $plans->isNotEmpty() ? ['value' => $plans->count(), 'label' => Str::plural('Membership plan', $plans->count())] : null,
        $facilities->isNotEmpty() ? ['value' => $facilities->count(), 'label' => Str::plural('Facility', $facilities->count())] : null,
        $hours->isNotEmpty() ? ['value' => $daysOpen, 'label' => 'Days open a week'] : null,
        $fromPrice !== null ? ['value' => (int) $fromPrice, 'display' => money($fromPrice, false), 'label' => 'Plans starting from'] : null,
        $photos->isNotEmpty() ? ['value' => $photos->count(), 'label' => Str::plural('Photo', $photos->count())] : null,
    ])->filter()->values();
@endphp
<x-layouts.base :title="$gym->name" body-class="theme-dark gym-page">
    <x-slot:head>
        <meta name="description" content="{{ Str::limit($gym->description ?: $gym->name.($location ? ' — gym in '.$location : '').'. Timings, membership plans, facilities and contact.', 155) }}">
        <meta property="og:title" content="{{ $gym->name }}{{ $location ? ' · '.$location : '' }}">
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ $gym->publicUrl() }}">
        <meta property="og:image" content="{{ $heroImage }}">
        <meta name="theme-color" content="#0D0D0D">
        <meta name="color-scheme" content="only dark">
        <link rel="canonical" href="{{ $gym->publicUrl() }}">
        <link rel="preload" as="image" href="{{ $heroImage }}" fetchpriority="high">
        <script>document.documentElement.classList.add('js');</script>
        @vite('resources/js/gym-page.js')
    </x-slot:head>

    <div data-gp-progress class="gp-progress fixed inset-x-0 top-0 z-[60] h-[3px] bg-gradient-to-r from-gym-orange to-gym-orange-light" aria-hidden="true"></div>
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[70] focus:rounded-full focus:bg-gym-orange focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white">Skip to content</a>

    @include('public.gym-profile.nav')

    <main id="main">
        @include('public.gym-profile.hero')
        @include('public.gym-profile.highlights')
        @include('public.gym-profile.about')
        @include('public.gym-profile.plans')
        @include('public.gym-profile.gallery')
        @include('public.gym-profile.member-app')
        @include('public.gym-profile.visit')
        @include('public.gym-profile.faq')
        @include('public.gym-profile.enquire')
        @include('public.gym-profile.cta')
    </main>

    @include('public.gym-profile.footer')
</x-layouts.base>
