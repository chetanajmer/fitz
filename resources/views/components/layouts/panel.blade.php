{{-- Shared sidebar shell for the gym panel and the platform panel. --}}
@props(['title' => null, 'brandName' => 'Gymai', 'brandSub' => null, 'brandLogo' => null, 'accountRoute' => null])
@php
    $user = auth()->user();
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
@endphp
<x-layouts.base :title="$title" :body-class="'panel-theme bg-gray-50'.(panel_theme() === 'dark' ? ' theme-dark' : '')">
    <div x-data="{ sidebar: false }" class="min-h-full">
        {{-- Mobile overlay --}}
        <div x-cloak x-show="sidebar" x-transition.opacity class="fixed inset-0 z-40 bg-black/70 backdrop-blur-sm lg:hidden" @click="sidebar = false"></div>

        <aside :class="sidebar ? 'translate-x-0' : '-translate-x-full'"
               class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-ink-700 bg-ink-900 transition-transform duration-200 lg:translate-x-0 no-print">
            <div class="flex h-16 shrink-0 items-center gap-3 border-b border-ink-700 px-5">
                @if ($brandLogo)
                    <img src="{{ $brandLogo }}" alt="" class="size-9 rounded-lg bg-white object-contain p-0.5">
                @else
                    <div class="flex size-9 items-center justify-center rounded-lg bg-brand-600 text-sm font-bold text-white shadow-[0_0_18px_-2px_rgb(255_107_0/0.6)]">{{ \Illuminate\Support\Str::of($brandName)->substr(0, 2)->upper() }}</div>
                @endif
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-white">{{ $brandName }}</p>
                    @if ($brandSub)<p class="truncate text-xs text-zinc-500">{{ $brandSub }}</p>@endif
                </div>
            </div>
            <nav class="flex-1 space-y-0.5 overflow-y-auto px-3 py-4">
                <p class="px-3 pb-2 text-[10px] font-semibold tracking-[0.18em] text-zinc-600 uppercase">Menu</p>
                {{ $nav }}
            </nav>
            <div class="border-t border-ink-700 p-3">
                <div class="flex items-center gap-3 rounded-xl bg-ink-800 px-3 py-2.5">
                    <img src="{{ $user->photoUrl() }}" alt="" class="size-9 rounded-full object-cover ring-2 ring-brand-600/40">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-white">{{ $user->name }}</p>
                        <p class="truncate text-xs text-zinc-500 capitalize">{{ $user->getRoleNames()->map(fn ($r) => str_replace('_', ' ', $r))->implode(', ') }}</p>
                    </div>
                </div>
                <div class="mt-2 grid grid-cols-2 gap-1">
                    @if ($accountRoute)
                        <a href="{{ route($accountRoute) }}" class="nav-link justify-center text-xs"><x-ui.icon name="user" class="size-4" /> Account</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}" class="{{ $accountRoute ? '' : 'col-span-2' }}">
                        @csrf
                        <button class="nav-link w-full justify-center text-xs hover:text-brand-400"><x-ui.icon name="logout" class="size-4" /> Logout</button>
                    </form>
                </div>
            </div>
        </aside>

        <div class="lg:pl-64">
            <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-gray-200 bg-white/85 px-4 backdrop-blur sm:px-6 lg:px-8 dark:border-ink-700 dark:bg-ink-850/85 no-print">
                <button type="button" class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-900 lg:hidden dark:text-zinc-400 dark:hover:bg-white/5 dark:hover:text-white" @click="sidebar = true" aria-label="Open menu">
                    <x-ui.icon name="menu" class="size-6" />
                </button>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $title ?? $greeting.', '.\Illuminate\Support\Str::of($user->name)->before(' ') }}</p>
                    <p class="truncate text-xs text-gray-500 dark:text-zinc-500">{{ $greeting }}, {{ \Illuminate\Support\Str::of($user->name)->before(' ') }} · {{ now()->format('D, d M Y') }}</p>
                </div>
                <x-ui.theme-toggle />
                @if ($accountRoute)
                    <a href="{{ route($accountRoute) }}" class="flex items-center gap-2 rounded-full border border-gray-200 bg-white py-1 pr-3 pl-1 text-sm text-gray-700 transition hover:border-brand-600/60 hover:text-gray-900 dark:border-ink-700 dark:bg-ink-800 dark:text-zinc-300 dark:hover:text-white">
                        <img src="{{ $user->photoUrl() }}" alt="" class="size-7 rounded-full object-cover">
                        <span class="hidden max-w-32 truncate sm:inline">{{ $user->name }}</span>
                    </a>
                @endif
            </header>
            <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                {{ $slot }}
            </main>
        </div>
    </div>
</x-layouts.base>
