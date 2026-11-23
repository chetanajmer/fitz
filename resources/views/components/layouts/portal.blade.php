@props(['title' => null])
@php
    $gym = current_gym();
    $links = [
        ['route' => 'portal.dashboard', 'params' => [], 'active' => request()->routeIs('portal.dashboard'), 'icon' => 'home', 'label' => 'Home'],
        ['route' => 'portal.plans', 'params' => ['diet'], 'active' => request()->routeIs('portal.plans') && request()->route('type') === 'diet', 'icon' => 'tag', 'label' => 'Diet'],
        ['route' => 'portal.plans', 'params' => ['workout'], 'active' => request()->routeIs('portal.plans') && request()->route('type') === 'workout', 'icon' => 'whistle', 'label' => 'Workouts'],
        ['route' => 'portal.account', 'params' => [], 'active' => request()->routeIs('portal.account'), 'icon' => 'user', 'label' => 'Account'],
    ];
@endphp
<x-layouts.base :title="$title" body-class="theme-dark">
    <header class="sticky top-0 z-30 border-b border-ink-700 bg-ink-850/85 backdrop-blur no-print">
        <div class="mx-auto flex h-16 max-w-5xl items-center justify-between gap-4 px-4">
            <a href="{{ route('portal.dashboard') }}" class="flex min-w-0 items-center gap-3">
                @if ($gym?->logoUrl())
                    <img src="{{ $gym->logoUrl() }}" alt="" class="size-9 rounded-lg bg-white object-contain p-0.5">
                @else
                    <div class="flex size-9 items-center justify-center rounded-lg bg-brand-600 text-sm font-bold text-white shadow-[0_0_18px_-2px_rgb(238_68_68/0.7)]">{{ $gym?->initials() }}</div>
                @endif
                <span class="truncate font-semibold text-white">{{ $gym?->name }}</span>
            </a>
            <nav class="hidden items-center gap-1 sm:flex">
                @foreach ($links as $link)
                    <a href="{{ route($link['route'], $link['params']) }}" class="rounded-lg px-3 py-1.5 text-sm font-medium transition {{ $link['active'] ? 'bg-brand-600/15 text-brand-400' : 'text-zinc-400 hover:bg-white/5 hover:text-white' }}">{{ $link['label'] }}</a>
                @endforeach
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="rounded-lg px-3 py-1.5 text-sm font-medium text-zinc-400 transition hover:bg-white/5 hover:text-brand-400">Logout</button></form>
            </nav>
            <form method="POST" action="{{ route('logout') }}" class="sm:hidden">@csrf<button class="rounded-lg p-1.5 text-zinc-400 hover:text-brand-400" aria-label="Logout"><x-ui.icon name="logout" /></button></form>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 pt-6 pb-24 sm:pb-8">{{ $slot }}</main>

    {{-- Mobile bottom navigation --}}
    <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-ink-700 bg-ink-900/95 backdrop-blur sm:hidden no-print">
        <div class="mx-auto grid max-w-md grid-cols-4">
            @foreach ($links as $link)
                <a href="{{ route($link['route'], $link['params']) }}" class="flex flex-col items-center gap-1 py-2.5 text-[11px] font-medium {{ $link['active'] ? 'text-brand-500' : 'text-zinc-500' }}">
                    <x-ui.icon :name="$link['icon']" class="size-5" />
                    {{ $link['label'] }}
                </a>
            @endforeach
        </div>
    </nav>
</x-layouts.base>
