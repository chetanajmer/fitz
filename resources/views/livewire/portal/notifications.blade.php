@php
    // Icon and tone per notification type (types come from PushNotificationService / NotificationCatalog).
    $look = fn (string $type) => match (true) {
        str_contains($type, 'payment') || str_contains($type, 'balance') => ['cash', 'bg-emerald-50 text-emerald-600'],
        str_contains($type, 'expir') || str_contains($type, 'membership') => ['card', 'bg-amber-50 text-amber-600'],
        str_contains($type, 'program') || str_contains($type, 'workout') => ['dumbbell', 'bg-indigo-50 text-indigo-600'],
        str_contains($type, 'diet') => ['meal', 'bg-indigo-50 text-indigo-600'],
        str_contains($type, 'streak') => ['fire', 'bg-amber-50 text-amber-600'],
        str_contains($type, 'weigh') || str_contains($type, 'goal') => ['scale', 'bg-sky-50 text-sky-600'],
        str_contains($type, 'announcement') => ['bell', 'bg-indigo-50 text-indigo-600'],
        default => ['bell', 'bg-gray-100 text-gray-600'],
    };
@endphp
<div class="space-y-6">
    <x-ui.page-header title="Notifications" :subtitle="$unread ? $unread.' unread' : 'You\'re all caught up.'">
        <x-slot:actions>
            @if ($unread)
                <button type="button" wire:click="markAllRead" class="btn-secondary rounded-full"><x-ui.icon name="check" class="size-4" /> Mark all as read</button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <div class="inline-flex rounded-full border border-gray-200 bg-white p-1 text-sm font-medium dark:border-ink-700 dark:bg-ink-800">
                @foreach (['all' => 'All', 'unread' => 'Unread'] as $key => $label)
                    <button type="button" wire:click="$set('filter', '{{ $key }}')" @class(['rounded-full px-4 py-1.5 transition', 'bg-brand-600 text-white' => $filter === $key, 'text-gray-600 hover:text-gray-900' => $filter !== $key])>{{ $label }}</button>
                @endforeach
            </div>

            <ul class="space-y-3">
                @forelse ($items as $i => $n)
                    @php [$icon, $tone] = $look((string) $n->type); @endphp
                    <li wire:key="n-{{ $n->id }}" class="pm-rise" style="--d: {{ min($i, 8) * 40 }}ms">
                        <div @class(['card flex gap-4 p-4 transition', 'ring-1 ring-brand-600/40' => ! $n->read_at])>
                            <span class="flex size-11 shrink-0 items-center justify-center rounded-xl {{ $tone }}"><x-ui.icon :name="$icon" class="size-5" /></span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="font-semibold text-gray-900">{{ $n->title }}</p>
                                    <time class="shrink-0 text-xs text-gray-500" datetime="{{ $n->created_at?->toIso8601String() }}" title="{{ $n->created_at?->format('d M Y, h:i A') }}">{{ $n->created_at?->diffForHumans(short: true) }}</time>
                                </div>
                                @if ($n->body)<p class="mt-1 text-sm text-gray-600">{{ $n->body }}</p>@endif
                                @if ($n->image_url)<img src="{{ $n->image_url }}" alt="" loading="lazy" class="mt-3 max-h-48 rounded-xl object-cover">@endif
                                @unless ($n->read_at)
                                    <button type="button" wire:click="markRead({{ $n->id }})" class="mt-2 text-xs font-semibold text-brand-600 hover:underline">Mark as read</button>
                                @endunless
                            </div>
                            @unless ($n->read_at)<span class="mt-1.5 size-2.5 shrink-0 rounded-full bg-brand-600" aria-label="Unread"></span>@endunless
                        </div>
                    </li>
                @empty
                    <li class="card">
                        <div class="flex flex-col items-center px-6 py-12 text-center">
                            <span class="flex size-16 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600"><x-ui.icon name="bell" class="size-8" /></span>
                            <p class="mt-4 font-semibold text-gray-900">{{ $filter === 'unread' ? 'No unread notifications' : 'No notifications yet' }}</p>
                            <p class="mt-1 max-w-xs text-sm text-gray-500">Membership reminders, payment receipts, program updates and gym news will show up here.</p>
                        </div>
                    </li>
                @endforelse
            </ul>
            {{ $items->links() }}
        </div>

        <div class="card h-max">
            <div class="card-header"><h2 class="card-title">Phone alerts</h2></div>
            <div class="card-body space-y-4">
                <p class="text-sm text-gray-500">Choose which alerts are pushed to your phone. Membership, payment and account alerts are always sent, and everything still shows in this list.</p>
                @foreach ($categories as $key => $label)
                    <label class="flex cursor-pointer items-center justify-between gap-4">
                        <span class="text-sm font-medium text-gray-900">{{ $label }}</span>
                        <span class="relative inline-flex shrink-0">
                            <input type="checkbox" wire:model.live="settings.{{ $key }}" class="peer sr-only">
                            <span class="h-6 w-11 rounded-full bg-gray-200 transition peer-checked:bg-brand-600 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500 peer-focus-visible:ring-offset-2"></span>
                            <span class="absolute top-0.5 left-0.5 size-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
                        </span>
                    </label>
                @endforeach
            </div>
        </div>
    </div>
</div>
