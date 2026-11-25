{{-- Sticky top bar: the gym's brand, links to the sections this gym has, and the two main actions. --}}
<header data-gp-nav x-data="{ open: false }" x-on:keydown.escape.window="open = false"
        class="gp-nav fixed inset-x-0 top-0 z-50 border-b border-transparent transition-colors duration-300">
    <nav class="mx-auto flex h-18 max-w-7xl items-center gap-6 px-4 sm:px-6 lg:px-8" aria-label="{{ $gym->name }}">
        <a href="#top" class="flex min-w-0 items-center gap-3">
            @if ($gym->logoUrl())
                <img src="{{ $gym->logoUrl() }}" alt="" class="size-10 shrink-0 rounded-xl bg-white object-contain p-0.5">
            @else
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-gym-orange text-sm font-bold text-white shadow-[0_0_24px_-4px_rgb(255_107_0/0.8)]">{{ $gym->initials() }}</span>
            @endif
            <span class="truncate text-lg font-semibold text-white">{{ $gym->name }}</span>
        </a>

        <div class="ml-auto hidden items-center gap-5 lg:flex">
            @foreach ($sections as $id => $label)
                <a href="#{{ $id }}" data-gp-nav-link class="gp-nav-link">{{ $label }}</a>
            @endforeach
        </div>

        <div class="ml-auto flex items-center gap-2 lg:ml-2">
            @if ($gym->phone)
                <a href="tel:{{ $gym->phone }}" class="hidden size-11 items-center justify-center rounded-full border border-white/15 text-white transition hover:border-gym-orange hover:text-gym-orange sm:flex" aria-label="Call {{ $gym->name }}">
                    <x-ui.icon name="phone" class="size-5" />
                </a>
            @endif
            <a href="{{ $joinUrl }}" class="gp-btn gp-btn-solid hidden min-h-11 px-5 py-2 text-sm sm:inline-flex">Join now</a>
            <button type="button" class="flex size-11 items-center justify-center rounded-full border border-white/15 text-white lg:hidden"
                    x-on:click="open = ! open" :aria-expanded="open.toString()" aria-controls="gp-mobile-menu" aria-label="Menu">
                <x-ui.icon name="menu" class="size-5" x-show="! open" />
                <x-ui.icon name="x" class="size-5" x-show="open" x-cloak />
            </button>
        </div>
    </nav>

    {{-- Phones and tablets --}}
    <div id="gp-mobile-menu" x-show="open" x-cloak
         x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="-translate-y-3 opacity-0"
         x-transition:leave="transition duration-200 ease-in" x-transition:leave-end="-translate-y-3 opacity-0"
         class="border-t border-gym-border bg-gym-black-deep/95 backdrop-blur-xl lg:hidden">
        <div class="mx-auto max-w-7xl space-y-1 px-4 py-4 sm:px-6">
            @foreach ($sections as $id => $label)
                <a href="#{{ $id }}" x-on:click="open = false" class="flex items-center justify-between rounded-xl px-4 py-3 text-base font-medium text-gym-light hover:bg-white/5 hover:text-gym-orange">
                    {{ $label }} <x-ui.icon name="arrow-right" class="size-4 opacity-50" />
                </a>
            @endforeach
            <div class="grid grid-cols-2 gap-3 pt-3">
                @if ($gym->phone)<a href="tel:{{ $gym->phone }}" class="gp-btn gp-btn-ghost"><x-ui.icon name="phone" class="size-4" /> Call</a>@endif
                <a href="{{ $joinUrl }}" class="gp-btn gp-btn-solid {{ $gym->phone ? '' : 'col-span-2' }}">Join now</a>
            </div>
        </div>
    </div>
</header>
