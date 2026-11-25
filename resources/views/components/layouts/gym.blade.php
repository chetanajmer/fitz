@props(['title' => null])
@php
    $gym = current_gym();
    $user = auth()->user();
    // Waiting in Enquiries: new enquiries + website/app join requests (for staff who can approve them).
    $newEnquiries = $user->can('enquiries.manage') ? \App\Models\Enquiry::where('status', \App\Enums\EnquiryStatus::New->value)->count() : 0;
    $joinRequests = $user->can('members.manage') ? \App\Models\Member::where('status', \App\Enums\MemberStatus::Pending->value)->count() : 0;
    $links = [
        ['route' => 'gym.dashboard', 'match' => 'gym.dashboard', 'icon' => 'home', 'label' => 'Dashboard', 'can' => 'gym.dashboard'],
        ['route' => 'gym.enquiries.index', 'match' => 'gym.enquiries.*', 'icon' => 'inbox', 'label' => 'Enquiries', 'can' => 'enquiries.manage',
            'badge' => $newEnquiries + $joinRequests, 'badge_title' => "{$joinRequests} join ".\Illuminate\Support\Str::plural('request', $joinRequests).", {$newEnquiries} new ".\Illuminate\Support\Str::plural('enquiry', $newEnquiries)],
        ['route' => 'gym.members.index', 'match' => 'gym.members.*', 'icon' => 'users', 'label' => $user->can('members.all') ? 'Members' : 'My Members', 'can' => 'members.view'],
        ['route' => 'gym.memberships.index', 'match' => 'gym.memberships.*', 'icon' => 'card', 'label' => 'Memberships', 'can' => 'memberships.manage'],
        ['route' => 'gym.payments.index', 'match' => 'gym.payments.*', 'icon' => 'cash', 'label' => 'Payments', 'can' => 'payments.manage'],
        ['route' => 'gym.attendance.index', 'match' => 'gym.attendance.index', 'icon' => 'calendar', 'label' => 'Attendance', 'can' => 'attendance.manage'],
        ['route' => 'gym.qr-codes', 'match' => 'gym.qr-codes', 'icon' => 'qr', 'label' => 'QR Code', 'can' => 'attendance.manage'],
        ['route' => 'gym.progress', 'match' => 'gym.progress', 'icon' => 'chart', 'label' => 'Weight Tracking', 'can' => 'members.view'],
        ['route' => 'gym.streaks', 'match' => 'gym.streaks', 'icon' => 'chart', 'label' => 'Streaks', 'can' => 'attendance.reports'],
        ['route' => 'gym.attendance.reports', 'match' => 'gym.attendance.reports', 'icon' => 'chart', 'label' => 'Reports', 'can' => 'attendance.reports'],
        ['route' => 'gym.content.index', 'params' => ['type' => 'diet'], 'match' => null, 'active' => request()->routeIs('gym.content.*') && (request()->route('type') === 'diet' || request()->route('plan')?->type?->value === 'diet'), 'icon' => 'tag', 'label' => 'Diet Charts', 'can' => 'content.custom'],
        ['route' => 'gym.content.index', 'params' => ['type' => 'workout'], 'match' => null, 'active' => request()->routeIs('gym.content.*') && (request()->route('type') === 'workout' || request()->route('plan')?->type?->value === 'workout'), 'icon' => 'whistle', 'label' => 'Workout Plans', 'can' => 'content.custom'],
        ['route' => 'gym.programs', 'match' => 'gym.programs', 'icon' => 'calendar', 'label' => 'Programs', 'can' => 'content.custom'],
        ['route' => 'gym.ai-coach', 'match' => 'gym.ai-coach', 'icon' => 'sparkles', 'label' => 'AI Coach', 'can' => 'ai.manage'],
        ['route' => 'gym.announcements', 'match' => 'gym.announcements', 'icon' => 'bell', 'label' => 'Notifications', 'can' => 'announcements.send'],
        ['route' => 'gym.plans.index', 'match' => 'gym.plans.*', 'icon' => 'tag', 'label' => 'Membership Plans', 'can' => 'plans.manage'],
        ['route' => 'gym.trainers.index', 'match' => 'gym.trainers.*', 'icon' => 'whistle', 'label' => 'Trainers', 'can' => 'trainers.manage'],
        ['route' => 'gym.settings', 'match' => 'gym.settings*', 'icon' => 'cog', 'label' => 'Gym Profile', 'can' => 'gym.settings'],
        ['route' => 'gym.billing', 'match' => 'gym.billing*', 'icon' => 'receipt', 'label' => 'Gymai Billing', 'can' => 'gym.billing'],
    ];
@endphp
<x-layouts.panel :title="$title" :brand-name="$gym?->name ?? 'Gymai'" brand-sub="Powered by Gymai" :brand-logo="$gym?->logoUrl()" account-route="gym.account">
    <x-slot:nav>
        <x-ui.dashboard-theme area="admin" :gym="$gym" />
        @foreach ($links as $link)
            @can($link['can'])
                <a href="{{ route($link['route'], $link['params'] ?? []) }}" class="nav-link {{ ($link['active'] ?? request()->routeIs($link['match'])) ? 'nav-link-active' : '' }}">
                    <x-ui.icon :name="$link['icon']" /> <span class="flex-1">{{ $link['label'] }}</span>
                    @if (! empty($link['badge']))<span class="rounded-full bg-brand-600 px-2 py-0.5 text-xs font-semibold text-white" title="{{ $link['badge_title'] ?? '' }}">{{ $link['badge'] }}</span>@endif
                </a>
            @endcan
        @endforeach
        @if ($gym)
            <div class="mt-4 border-t border-ink-700 pt-4">
                <a href="{{ $gym->publicUrl() }}" target="_blank" class="nav-link"><x-ui.icon name="globe" /> Public page <x-ui.icon name="external" class="ml-auto size-3.5 opacity-60" /></a>
            </div>
        @endif
    </x-slot:nav>
    {{ $slot }}
</x-layouts.panel>
