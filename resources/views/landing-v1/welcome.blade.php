{{-- BACKUP of the original landing page (commit 5ddec03), kept for reference. Not routed: the live page is resources/views/welcome.blade.php. --}}
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
<x-layouts.base title="Gym management software" body-class="landing bg-ink-850 text-white">
    <x-slot:head>
        <meta name="description" content="Gymai is all-in-one gym management software: members, memberships, payments, QR attendance, trainers, diet & workout plans and reports. {{ $priceLabel }} per active member per month, no setup fee.">
        <meta property="og:title" content="Gymai — Run your gym smarter">
        <meta property="og:description" content="All-in-one gym management software for memberships, payments, attendance, staff and growth.">
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ url('/') }}">
        <meta name="theme-color" content="#18181A">
        <link rel="canonical" href="{{ url('/') }}">
        <script>document.documentElement.classList.add('js');</script>
        {{-- Backup: original script is resources/js/landing-v1.js (not in the Vite build) --}}
    </x-slot:head>

    {{-- Loader (JS-only; removed as soon as the page is ready, never longer than ~1.2s) --}}
    <div data-lp-loader class="lp-loader fixed inset-0 z-[80] flex-col items-center justify-center gap-6 bg-ink-850" aria-hidden="true">
        <span class="lp-loader-mark flex size-16 items-center justify-center rounded-2xl bg-brand-600 text-2xl font-bold text-white">G</span>
        <span class="h-1 w-40 overflow-hidden rounded-full bg-ink-700"><span data-lp-loader-bar class="lp-loader-bar block h-full w-full rounded-full bg-gradient-to-r from-brand-700 to-brand-400"></span></span>
    </div>
    <div data-lp-progress class="lp-progress fixed inset-x-0 top-0 z-[60] h-0.5 bg-gradient-to-r from-brand-700 via-brand-500 to-brand-400" aria-hidden="true"></div>

    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[70] focus:rounded-lg focus:bg-brand-600 focus:px-4 focus:py-2 focus:text-sm focus:font-semibold">Skip to content</a>

    @include('landing-v1.nav')

    <main id="main">
        @include('landing-v1.hero')
        @include('landing-v1.features')
        @include('landing-v1.member-spotlight')
        @include('landing-v1.how-it-works')
        @include('landing-v1.showcase')
        @include('landing-v1.pricing')
        @include('landing-v1.why')
        @include('landing-v1.stats')
        @include('landing-v1.testimonials')
        @include('landing-v1.faq')
        @include('landing-v1.cta')
    </main>

    @include('landing-v1.footer')
</x-layouts.base>
