@php
    use App\Models\PlatformSetting;
    use App\Models\PricingRate;

    $price = PricingRate::rateOn();
    $priceLabel = money($price, fmod($price, 1) != 0.0);
    $registrationsOpen = PlatformSetting::get('allow_registrations', '1') === '1';
    $contact = [
        'email' => PlatformSetting::get('support_email'),
        'phone' => PlatformSetting::get('support_phone'),
        'address' => PlatformSetting::get('company_address'),
    ];
    $ctaUrl = $registrationsOpen ? route('register.gym') : 'mailto:'.$contact['email'];
    $ctaLabel = $registrationsOpen ? 'Register Your Gym' : 'Contact Sales';
@endphp
<x-layouts.base title="Gym management software" body-class="landing">
    <x-slot:head>
        <meta name="description" content="Gymai is all-in-one gym management software: members, memberships, payments, QR attendance, trainers, diet & workout plans, a member app and an AI Coach. {{ $priceLabel }} per active member per month, no setup fee.">
        <meta property="og:title" content="Gymai — Lift your gym">
        <meta property="og:description" content="All-in-one gym management software for memberships, payments, attendance, trainers, a member app and growth.">
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ url('/') }}">
        <meta name="theme-color" content="#EEE9DC">
        {{-- The palette is designed light-only; stop browsers from auto-darkening it. --}}
        <meta name="color-scheme" content="only light">
        <link rel="canonical" href="{{ url('/') }}">
        <script>document.documentElement.classList.add('js');</script>
        @vite('resources/js/landing.js')
    </x-slot:head>

    @include('landing.loader')

    <div data-lp-progressclass="lp-progress fixed inset-x-0 top-0 z-[60] h-1 bg-clay" aria-hidden="true"></div>

    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[70] focus:bg-forest focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-volt">Skip to content</a>

    @include('landing.nav')

    <main id="main" class="relative">
        {{-- Heartbeat "spine": landing.js draws a pulse line down the left gutter on wide screens. --}}
        <svg data-lp-spine class="lp-spine absolute top-0 left-[max(0.5rem,calc((100%-80rem)/2-3.5rem))] z-30 hidden w-10 min-[1440px]:block" aria-hidden="true" preserveAspectRatio="none">
            <path pathLength="1" fill="none" stroke="var(--color-clay)" stroke-width="3" stroke-linejoin="round" />
        </svg>

        @include('landing.hero')
        @include('landing.day')
        @include('landing.features')
        @include('landing.stats')
        @include('landing.members')
        @include('landing.ai-coach')
        @include('landing.how-it-works')
        @include('landing.discover')
        @include('landing.pricing')
        @include('landing.about')
        @include('landing.faq')
    </main>

    @include('landing.footer')
</x-layouts.base>
