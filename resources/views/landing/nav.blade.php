@php
    $navLinks = ['home' => 'Home', 'features' => 'Features', 'how-it-works' => 'How It Works', 'pricing' => 'Pricing', 'about' => 'About Us', 'contact' => 'Contact'];
@endphp
<header data-lp-nav class="lp-nav fixed inset-x-0 top-0 z-50 border-b border-transparent">
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:h-20 lg:px-8">
        <a href="#home" class="flex items-center gap-2.5" aria-label="Gymai home">
            <span class="flex size-9 items-center justify-center rounded-xl bg-brand-600 font-bold text-white shadow-[0_0_24px_-4px_rgb(238_68_68/0.9)]">G</span>
            <span class="text-lg font-semibold tracking-tight">Gymai</span>
        </a>

        <nav class="hidden items-center gap-1 lg:flex" aria-label="Primary">
            @foreach ($navLinks as $id => $label)
                <a href="#{{ $id }}" data-lp-navlink="{{ $id }}" class="lp-navlink rounded-lg px-3 py-2 text-sm font-medium text-zinc-400 transition hover:text-white [&.is-active]:text-white">{{ $label }}</a>
            @endforeach
        </nav>

        <div class="hidden items-center gap-2 lg:flex">
            @auth
                <a href="{{ auth()->user()->homeRoute() }}" class="btn-primary px-5">Open dashboard</a>
            @else
                <a href="{{ route('login') }}" class="rounded-lg px-4 py-2 text-sm font-medium text-zinc-300 transition hover:text-white">Login</a>
                <a href="{{ route('member.join') }}" class="rounded-lg border border-ink-700 px-4 py-2 text-sm font-medium text-zinc-200 transition hover:bg-white/5 hover:text-white">Join as member</a>
                <a href="{{ $ctaUrl }}" data-magnetic class="btn-primary lp-shine-btn px-5">Get Started</a>
            @endauth
        </div>

        <button type="button" data-lp-menu-toggle aria-controls="lp-mobile-menu" aria-expanded="false" class="lp-burger flex size-10 flex-col items-center justify-center gap-[5px] rounded-lg text-zinc-300 hover:bg-white/5 hover:text-white lg:hidden">
            <span class="sr-only">Toggle menu</span>
            <span class="block h-0.5 w-5 rounded-full bg-current"></span>
            <span class="block h-0.5 w-5 rounded-full bg-current"></span>
            <span class="block h-0.5 w-5 rounded-full bg-current"></span>
        </button>
    </div>

    <div id="lp-mobile-menu" data-lp-menu hidden class="border-t border-ink-700 bg-ink-900/95 backdrop-blur lg:hidden">
        <nav class="mx-auto flex max-w-7xl flex-col px-4 py-3" aria-label="Mobile">
            @foreach ($navLinks as $id => $label)
                <a href="#{{ $id }}" class="rounded-lg px-3 py-2.5 text-sm font-medium text-zinc-300 hover:bg-white/5 hover:text-white">{{ $label }}</a>
            @endforeach
            <div class="mt-3 grid grid-cols-2 gap-2 border-t border-ink-700 pt-3">
                @auth
                    <a href="{{ auth()->user()->homeRoute() }}" class="btn-primary col-span-2">Open dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="btn border border-ink-700 text-zinc-200 hover:bg-white/5">Login</a>
                    <a href="{{ $ctaUrl }}" class="btn-primary">Get Started</a>
                    <a href="{{ route('member.join') }}" class="btn col-span-2 border border-ink-700 text-zinc-200 hover:bg-white/5">Join as member</a>
                @endauth
            </div>
        </nav>
    </div>
</header>
