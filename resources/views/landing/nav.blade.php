@php
    $navLinks = ['features' => 'Features', 'members' => 'Member app', 'ai-coach' => 'AI Coach', 'pricing' => 'Pricing', 'about' => 'About', 'contact' => 'Contact'];
@endphp
<header data-lp-nav class="lp-nav fixed inset-x-0 top-0 z-50">
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:h-20 lg:px-8">
        <a href="#home" class="lp-display flex items-center text-3xl" aria-label="Gymai home">
            Gym<span class="lp-hl ml-0.5">ai</span>
        </a>

        <nav class="hidden items-center xl:flex" aria-label="Primary">
            @foreach ($navLinks as $id => $label)
                <a href="#{{ $id }}" data-lp-navlink="{{ $id }}" class="lp-navlink px-3 py-2 text-sm font-semibold">{{ $label }}</a>
            @endforeach
            <a href="{{ route('gyms.browse') }}" class="lp-navlink px-3 py-2 text-sm font-semibold">Browse gyms</a>
        </nav>

        <div class="hidden items-center gap-3 lg:flex">
            @auth
                <a href="{{ auth()->user()->homeRoute() }}" class="lp-btn lp-btn-solid py-2.5">Open dashboard</a>
            @else
                <a href="{{ route('login') }}" class="px-2 text-sm font-semibold underline-offset-4 hover:underline">Login</a>
                <a href="{{ route('member.join') }}" class="lp-btn lp-btn-ghost py-2.5 text-sm">Register as Member</a>
                <a href="{{ $ctaUrl }}" class="lp-btn lp-btn-solid py-2.5 text-sm">{{ $ctaLabel }}</a>
            @endauth
        </div>

        <button type="button" data-lp-menu-toggle aria-controls="lp-mobile-menu" aria-expanded="false" class="lp-burger flex size-11 flex-col items-center justify-center gap-[5px] border-2 border-forest lg:hidden">
            <span class="sr-only">Toggle menu</span>
            <span class="block h-0.5 w-5 bg-current"></span>
            <span class="block h-0.5 w-5 bg-current"></span>
            <span class="block h-0.5 w-5 bg-current"></span>
        </button>
    </div>

    <div id="lp-mobile-menu" data-lp-menu hidden class="border-t-2 border-forest bg-chalk lg:hidden">
        <nav class="mx-auto flex max-w-7xl flex-col px-4 py-4" aria-label="Mobile">
            @foreach ($navLinks as $id => $label)
                <a href="#{{ $id }}" class="lp-display border-b border-forest/15 py-3 text-3xl">{{ $label }}</a>
            @endforeach
            <a href="{{ route('gyms.browse') }}" class="lp-display border-b border-forest/15 py-3 text-3xl">Browse gyms</a>
            <div class="mt-5 grid grid-cols-2 gap-3">
                @auth
                    <a href="{{ auth()->user()->homeRoute() }}" class="lp-btn lp-btn-solid col-span-2">Open dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="lp-btn lp-btn-ghost">Login</a>
                    <a href="{{ route('member.join') }}" class="lp-btn lp-btn-ghost">Register as Member</a>
                    <a href="{{ $ctaUrl }}" class="lp-btn lp-btn-solid col-span-2">{{ $ctaLabel }}</a>
                @endauth
            </div>
        </nav>
    </div>
</header>
