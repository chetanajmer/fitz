{{-- Shared sidebar shell for the gym panel and the platform panel. --}}
@props(['title' => null, 'brandName' => 'Gymai', 'brandSub' => null, 'brandLogo' => null, 'accountRoute' => null, 'sidebarClass' => 'bg-gray-900'])
<x-layouts.base :title="$title">
    <div x-data="{ sidebar: false }" class="min-h-full">
        {{-- Mobile overlay --}}
        <div x-cloak x-show="sidebar" x-transition.opacity class="fixed inset-0 z-40 bg-gray-900/60 lg:hidden" @click="sidebar = false"></div>

        <aside :class="sidebar ? 'translate-x-0' : '-translate-x-full'"
               class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col {{ $sidebarClass }} transition-transform duration-200 lg:translate-x-0 no-print">
            <div class="flex h-16 shrink-0 items-center gap-3 border-b border-white/10 px-5">
                @if ($brandLogo)
                    <img src="{{ $brandLogo }}" alt="" class="size-9 rounded-lg bg-white object-contain p-0.5">
                @else
                    <div class="flex size-9 items-center justify-center rounded-lg bg-indigo-500 text-sm font-bold text-white">{{ \Illuminate\Support\Str::of($brandName)->substr(0, 2)->upper() }}</div>
                @endif
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-white">{{ $brandName }}</p>
                    @if ($brandSub)<p class="truncate text-xs text-gray-400">{{ $brandSub }}</p>@endif
                </div>
            </div>
            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                {{ $nav }}
            </nav>
            <div class="border-t border-white/10 p-3">
                <div class="flex items-center gap-3 rounded-lg px-2 py-2">
                    <img src="{{ auth()->user()->photoUrl() }}" alt="" class="size-8 rounded-full object-cover">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-white">{{ auth()->user()->name }}</p>
                        <p class="truncate text-xs text-gray-400">{{ auth()->user()->getRoleNames()->map(fn ($r) => str_replace('_', ' ', $r))->implode(', ') }}</p>
                    </div>
                </div>
                <div class="mt-1 grid grid-cols-2 gap-1">
                    @if ($accountRoute)
                        <a href="{{ route($accountRoute) }}" class="nav-link justify-center text-xs"><x-ui.icon name="user" class="size-4" /> Account</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}" class="{{ $accountRoute ? '' : 'col-span-2' }}">
                        @csrf
                        <button class="nav-link w-full justify-center text-xs"><x-ui.icon name="logout" class="size-4" /> Logout</button>
                    </form>
                </div>
            </div>
        </aside>

        <div class="lg:pl-64">
            <header class="sticky top-0 z-30 flex h-14 items-center gap-3 border-b border-gray-200 bg-white/90 px-4 backdrop-blur lg:hidden no-print">
                <button type="button" class="rounded-md p-1.5 text-gray-600 hover:bg-gray-100" @click="sidebar = true" aria-label="Open menu">
                    <x-ui.icon name="menu" class="size-6" />
                </button>
                <span class="truncate text-sm font-semibold">{{ $brandName }}</span>
            </header>
            <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                {{ $slot }}
            </main>
        </div>
    </div>
</x-layouts.base>
