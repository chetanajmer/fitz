@props(['title' => null])
@php($gym = current_gym())
<x-layouts.base :title="$title">
    <header class="border-b border-gray-200 bg-white no-print">
        <div class="mx-auto flex h-16 max-w-5xl items-center justify-between gap-4 px-4">
            <a href="{{ route('portal.dashboard') }}" class="flex min-w-0 items-center gap-3">
                @if ($gym?->logoUrl())
                    <img src="{{ $gym->logoUrl() }}" alt="" class="size-9 rounded-lg object-contain">
                @else
                    <div class="flex size-9 items-center justify-center rounded-lg text-sm font-bold text-white" style="background: {{ $gym?->primaryColor() ?? '#4f46e5' }}">{{ $gym?->initials() }}</div>
                @endif
                <span class="truncate font-semibold">{{ $gym?->name }}</span>
            </a>
            <div class="flex items-center gap-1">
                <a href="{{ route('portal.dashboard') }}" class="btn-ghost btn-sm {{ request()->routeIs('portal.dashboard') ? 'text-gray-900' : '' }}">Home</a>
                <a href="{{ route('portal.plans', 'diet') }}" class="btn-ghost btn-sm">Diet</a>
                <a href="{{ route('portal.plans', 'workout') }}" class="btn-ghost btn-sm">Workouts</a>
                <a href="{{ route('portal.account') }}" class="btn-ghost btn-sm">Account</a>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn-ghost btn-sm">Logout</button></form>
            </div>
        </div>
    </header>
    <main class="mx-auto max-w-5xl px-4 py-6">{{ $slot }}</main>
</x-layouts.base>
