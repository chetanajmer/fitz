@props(['title' => null])
@php
    $gym = current_gym();
    $member = auth()->user()->memberProfile;
    $unread = $member ? app(\App\Services\PushNotificationService::class)->unreadCount($member) : 0;
    $is = fn (string $route, ?string $type = null) => request()->routeIs($route) && ($type === null || request()->route('type') === $type);
    // Only sections the portal actually has. Help & support lives on the Account page.
    $groups = [
        'Main' => [
            ['portal.dashboard', [], $is('portal.dashboard'), 'home', 'Overview'],
            ['portal.membership', [], $is('portal.membership'), 'card', 'My membership'],
            ['portal.check-in', [], $is('portal.check-in'), 'qr', 'QR check-in'],
        ],
        'Training' => [
            ['portal.plans', ['workout'], $is('portal.plans', 'workout'), 'dumbbell', 'Workout plans'],
            ['portal.plans', ['diet'], $is('portal.plans', 'diet'), 'meal', 'Diet plans'],
            ['portal.progress', [], $is('portal.progress'), 'trending', 'Fitness progress'],
        ],
        'Account' => [
            ['portal.notifications', [], $is('portal.notifications'), 'bell', 'Notifications'],
            ['portal.account', [], $is('portal.account'), 'user', 'Profile & settings'],
        ],
    ];
    $bottom = [
        ['portal.dashboard', [], $is('portal.dashboard'), 'home', 'Home'],
        ['portal.check-in', [], $is('portal.check-in'), 'qr', 'Check-in'],
        ['portal.plans', ['workout'], $is('portal.plans', 'workout'), 'dumbbell', 'Workouts'],
        ['portal.progress', [], $is('portal.progress'), 'trending', 'Progress'],
    ];
@endphp
<x-layouts.base :title="$title" :body-class="'panel-theme bg-gray-50'.(panel_theme() === 'dark' ? ' theme-dark' : '')">
    <x-slot:head>
        {{-- Apply the saved sidebar state before first paint, so the layout never jumps. --}}
        <script>try { if (localStorage.getItem('pm-sidebar') === 'collapsed') document.documentElement.classList.add('pm-collapsed'); } catch (e) {}</script>
    </x-slot:head>

    <div class="pm-loadbar no-print" aria-hidden="true"></div>
    <x-ui.dashboard-theme area="member" :gym="$gym" />

    <div x-data="{ drawer: false }" x-on:keydown.escape.window="drawer = false" class="min-h-full">
        {{-- Mobile drawer overlay --}}
        <div x-cloak x-show="drawer" x-transition.opacity class="fixed inset-0 z-40 bg-black/70 backdrop-blur-sm lg:hidden" x-on:click="drawer = false"></div>

        {{-- Sidebar: fixed on desktop (collapsible), a drawer on phones and tablets --}}
        <aside :class="drawer ? 'translate-x-0' : '-translate-x-full'"
               class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-ink-700 bg-ink-900 transition-[transform,width] duration-300 lg:w-64 lg:translate-x-0 in-[.pm-collapsed]:lg:w-20 no-print"
               aria-label="Member portal">
            <div class="flex h-16 shrink-0 items-center gap-3 border-b border-ink-700 px-5 in-[.pm-collapsed]:lg:justify-center in-[.pm-collapsed]:lg:px-0">
                @if ($gym?->logoUrl())
                    <img src="{{ $gym->logoUrl() }}" alt="" class="size-9 shrink-0 rounded-lg bg-white object-contain p-0.5">
                @else
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-600 text-sm font-bold text-white shadow-[0_0_18px_-2px_color-mix(in_srgb,var(--gym-orange)_60%,transparent)]">{{ $gym?->initials() }}</div>
                @endif
                <div class="min-w-0 in-[.pm-collapsed]:lg:hidden">
                    <p class="truncate text-sm font-semibold text-white">{{ $gym?->name }}</p>
                    <p class="truncate text-xs text-zinc-500">Member portal</p>
                </div>
                <button type="button" class="ml-auto rounded-lg p-1.5 text-zinc-400 hover:bg-white/5 hover:text-white lg:hidden" x-on:click="drawer = false" aria-label="Close menu"><x-ui.icon name="x" class="size-5" /></button>
            </div>

            <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5">
                @foreach ($groups as $group => $links)
                    <div>
                        <p class="px-3 pb-2 text-[10px] font-semibold tracking-[0.18em] text-zinc-600 uppercase in-[.pm-collapsed]:lg:sr-only">{{ $group }}</p>
                        <div class="space-y-1">
                            @foreach ($links as [$route, $params, $active, $icon, $label])
                                <a href="{{ route($route, $params) }}" @class(['pm-nav in-[.pm-collapsed]:lg:justify-center', 'is-active' => $active]) @if ($active) aria-current="page" @endif title="{{ $label }}">
                                    <x-ui.icon :name="$icon" class="size-5" />
                                    <span class="flex-1 truncate in-[.pm-collapsed]:lg:sr-only">{{ $label }}</span>
                                    @if ($route === 'portal.notifications' && $unread > 0)
                                        <span class="rounded-full bg-brand-600 px-1.5 py-0.5 text-[10px] leading-none font-bold text-white in-[.pm-collapsed]:lg:absolute in-[.pm-collapsed]:lg:top-1.5 in-[.pm-collapsed]:lg:right-3">{{ $unread > 99 ? '99+' : $unread }}</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </nav>

            <div class="border-t border-ink-700 p-3">
                <a href="{{ route('portal.account') }}" class="flex items-center gap-3 rounded-xl bg-ink-800 px-3 py-2.5 transition hover:bg-ink-750 in-[.pm-collapsed]:lg:justify-center in-[.pm-collapsed]:lg:bg-transparent in-[.pm-collapsed]:lg:px-0">
                    <img src="{{ $member?->photoUrl() ?? auth()->user()->photoUrl() }}" alt="" class="size-9 shrink-0 rounded-full object-cover ring-2 ring-brand-600/50">
                    <div class="min-w-0 flex-1 in-[.pm-collapsed]:lg:hidden">
                        <p class="truncate text-sm font-medium text-white">{{ auth()->user()->name }}</p>
                        <p class="truncate font-mono text-xs text-zinc-500">{{ $member?->member_code }}</p>
                    </div>
                </a>
                <div class="mt-2 flex gap-1">
                    <form method="POST" action="{{ route('logout') }}" class="flex-1">
                        @csrf
                        <button class="pm-nav w-full justify-center text-xs hover:text-brand-400" title="Logout"><x-ui.icon name="logout" class="size-4" /><span class="in-[.pm-collapsed]:lg:sr-only">Logout</span></button>
                    </form>
                    <button type="button" class="pm-nav hidden justify-center lg:flex" title="Collapse sidebar" aria-label="Collapse or expand the sidebar"
                            x-on:click="const c = document.documentElement.classList.toggle('pm-collapsed'); try { localStorage.setItem('pm-sidebar', c ? 'collapsed' : 'open') } catch (e) {}">
                        <x-ui.icon name="arrow-left" class="size-4 in-[.pm-collapsed]:rotate-180" />
                    </button>
                </div>
            </div>
        </aside>

        <div class="transition-[padding] duration-300 lg:pl-64 in-[.pm-collapsed]:lg:pl-20">
            {{-- Top bar --}}
            <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-gray-200 bg-white/85 px-4 backdrop-blur sm:px-6 lg:px-8 dark:border-ink-700 dark:bg-ink-850/85 no-print">
                <button type="button" class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-900 lg:hidden dark:text-zinc-400 dark:hover:bg-white/5 dark:hover:text-white" x-on:click="drawer = true" aria-label="Open menu">
                    <x-ui.icon name="menu" class="size-6" />
                </button>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $title ?? 'Overview' }}</p>
                    <p class="truncate text-xs text-gray-500 dark:text-zinc-500">{{ $gym?->name }} · {{ now()->format('D, d M Y') }}</p>
                </div>
                <a href="{{ route('portal.notifications') }}" class="relative rounded-full p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 dark:text-zinc-400 dark:hover:bg-white/5 dark:hover:text-white" aria-label="Notifications{{ $unread ? ', '.$unread.' unread' : '' }}">
                    <x-ui.icon name="bell" class="size-5" />
                    @if ($unread > 0)
                        <span class="absolute top-1 right-1 flex size-2.5"><span class="absolute inset-0 animate-ping rounded-full bg-brand-500/70 motion-reduce:animate-none"></span><span class="relative size-2.5 rounded-full bg-brand-600 ring-2 ring-white dark:ring-ink-850"></span></span>
                    @endif
                </a>
                <x-ui.theme-toggle />
                <a href="{{ route('portal.account') }}" class="hidden items-center gap-2 rounded-full border border-gray-200 bg-white py-1 pr-3 pl-1 text-sm text-gray-700 transition hover:border-brand-600/60 hover:text-gray-900 sm:flex dark:border-ink-700 dark:bg-ink-800 dark:text-zinc-300 dark:hover:text-white">
                    <img src="{{ $member?->photoUrl() ?? auth()->user()->photoUrl() }}" alt="" class="size-7 rounded-full object-cover">
                    <span class="max-w-32 truncate">{{ \Illuminate\Support\Str::of(auth()->user()->name)->before(' ') }}</span>
                </a>
            </header>

            <main class="pm-page mx-auto max-w-6xl px-4 pt-6 pb-28 sm:px-6 lg:px-8 lg:pb-10">{{ $slot }}</main>
        </div>

        {{-- Phones: bottom navigation, "More" opens the full menu --}}
        <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-gray-200 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden dark:border-ink-700 dark:bg-ink-900/95 no-print" aria-label="Quick navigation">
            <div class="mx-auto grid max-w-md grid-cols-5">
                @foreach ($bottom as [$route, $params, $active, $icon, $label])
                    <a href="{{ route($route, $params) }}" class="relative flex flex-col items-center gap-1 py-2.5 text-[11px] font-medium {{ $active ? 'text-brand-600 dark:text-brand-500' : 'text-gray-500 dark:text-zinc-500' }}" @if ($active) aria-current="page" @endif>
                        @if ($active)<span class="absolute top-0 h-0.5 w-8 rounded-full bg-brand-600"></span>@endif
                        <x-ui.icon :name="$icon" class="size-5" />
                        {{ $label }}
                    </a>
                @endforeach
                <button type="button" x-on:click="drawer = true" class="relative flex flex-col items-center gap-1 py-2.5 text-[11px] font-medium text-gray-500 dark:text-zinc-500">
                    <x-ui.icon name="menu" class="size-5" />
                    More
                    @if ($unread > 0)<span class="absolute top-2 right-[30%] size-2 rounded-full bg-brand-600"></span>@endif
                </button>
            </div>
        </nav>
    </div>
</x-layouts.base>
