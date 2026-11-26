@props(['title' => null])
@php
    $links = [
        ['route' => 'platform.dashboard', 'match' => 'platform.dashboard', 'icon' => 'home', 'label' => 'Overview'],
        ['route' => 'platform.gyms.index', 'match' => 'platform.gyms.*', 'icon' => 'building', 'label' => 'Gyms'],
        ['route' => 'platform.cities', 'match' => 'platform.cities', 'icon' => 'globe', 'label' => 'Cities'],
        ['route' => 'platform.leads', 'match' => 'platform.leads', 'icon' => 'inbox', 'label' => 'App Leads', 'badge' => \App\Models\PlatformLead::where('status', 'new')->count()],
        ['route' => 'platform.notifications', 'match' => 'platform.notifications', 'icon' => 'bell', 'label' => 'App Notifications', 'badge' => \App\Models\Announcement::withoutGlobalScope(\App\Models\Scopes\GymScope::class)->where('status', 'pending')->count()],
        ['route' => 'platform.library', 'match' => 'platform.library', 'icon' => 'tag', 'label' => 'Default Library'],
        ['route' => 'platform.invoices', 'match' => 'platform.invoices*', 'icon' => 'receipt', 'label' => 'Invoices & Payments'],
        ['route' => 'platform.ai-coach', 'match' => 'platform.ai-coach', 'icon' => 'sparkles', 'label' => 'AI Coach'],
        ['route' => 'platform.pricing', 'match' => 'platform.pricing', 'icon' => 'tag', 'label' => 'Pricing'],
        ['route' => 'platform.settings', 'match' => 'platform.settings', 'icon' => 'cog', 'label' => 'Settings'],
    ];
@endphp
<x-layouts.panel :title="$title" brand-name="Fitnix Platform" brand-sub="Super Admin" account-route="platform.account">
    <x-slot:nav>
        @foreach ($links as $link)
            <a href="{{ route($link['route']) }}" class="nav-link {{ request()->routeIs($link['match']) ? 'nav-link-active' : '' }}">
                <x-ui.icon :name="$link['icon']" /> <span class="flex-1">{{ $link['label'] }}</span>
                @if (! empty($link['badge']))<span class="rounded-full bg-brand-600 px-2 py-0.5 text-xs font-semibold text-white">{{ $link['badge'] }}</span>@endif
            </a>
        @endforeach
    </x-slot:nav>
    {{ $slot }}
</x-layouts.panel>
