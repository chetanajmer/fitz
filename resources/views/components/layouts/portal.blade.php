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
<x-layouts.base :title="$title" :body-class="'panel-theme bg-gray-50'.(panel_theme() === 'dark' ? ' theme-dark' : '')">
    <header class="sticky top-0 z-30 border-b border-gray-200 bg-white/85 backdrop-blur dark:border-ink-700 dark:bg-ink-850/85 no-print">
        <div class="mx-auto flex h-16 max-w-5xl items-center justify-between gap-4 px-4">
            <a href="{{ route('portal.dashboard') }}" class="flex min-w-0 items-center gap-3">
                @if ($gym?->logoUrl())
                    <img src="{{ $gym->logoUrl() }}" alt="" class="size-9 rounded-lg bg-white object-contain p-0.5">
                @else
                    <div class="flex size-9 items-center justify-center rounded-lg bg-brand-600 text-sm font-bold text-white shadow-[0_0_18px_-2px_rgb(238_68_68/0.7)]">{{ $gym?->initials() }}</div>
                @endif
                <span class="truncate font-semibold text-gray-900 dark:text-white">{{ $gym?->name }}</span>
            </a>
            <div class="flex items-center gap-1">
                <nav class="hidden items-center gap-1 sm:flex">
                    @foreach ($links as $link)
                        <a href="{{ route($link['route'], $link['params']) }}" class="rounded-lg px-3 py-1.5 text-sm font-medium transition {{ $link['active'] ? 'bg-brand-600/10 text-brand-700 dark:bg-brand-600/15 dark:text-brand-400' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-zinc-400 dark:hover:bg-white/5 dark:hover:text-white' }}">{{ $link['label'] }}</a>
                    @endforeach
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="rounded-lg px-3 py-1.5 text-sm font-medium text-gray-600 transition hover:bg-gray-100 hover:text-brand-600 dark:text-zinc-400 dark:hover:bg-white/5 dark:hover:text-brand-400">Logout</button></form>
                </nav>
                <x-ui.theme-toggle class="ml-1" />
                <form method="POST" action="{{ route('logout') }}" class="sm:hidden">@csrf<button class="rounded-lg p-1.5 text-gray-500 hover:text-brand-600 dark:text-zinc-400 dark:hover:text-brand-400" aria-label="Logout"><x-ui.icon name="logout" /></button></form>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 pt-6 pb-24 sm:pb-8">{{ $slot }}</main>

    {{-- Mobile bottom navigation --}}
    <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-gray-200 bg-white/95 backdrop-blur sm:hidden dark:border-ink-700 dark:bg-ink-900/95 no-print">
        <div class="mx-auto grid max-w-md grid-cols-4">
            @foreach ($links as $link)
                <a href="{{ route($link['route'], $link['params']) }}" class="flex flex-col items-center gap-1 py-2.5 text-[11px] font-medium {{ $link['active'] ? 'text-brand-600 dark:text-brand-500' : 'text-gray-500 dark:text-zinc-500' }}">
                    <x-ui.icon :name="$link['icon']" class="size-5" />
                    {{ $link['label'] }}
                </a>
            @endforeach
        </div>
    </nav>
</x-layouts.base>
