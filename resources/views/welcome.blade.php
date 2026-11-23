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
        @vite('resources/js/landing.js')
    </x-slot:head>

    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[70] focus:rounded-lg focus:bg-brand-600 focus:px-4 focus:py-2 focus:text-sm focus:font-semibold">Skip to content</a>

    @include('landing.nav')

    <main id="main">
        @include('landing.hero')
        @include('landing.features')
        @include('landing.how-it-works')
        @include('landing.showcase')
        @include('landing.pricing')
        @include('landing.why')
        @include('landing.testimonials')
        @include('landing.faq')
        @include('landing.cta')
    </main>

    @include('landing.footer')
</x-layouts.base>
