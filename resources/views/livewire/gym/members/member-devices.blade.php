<div class="card">
    <div class="card-header flex items-center justify-between gap-3">
        <h2 class="card-title flex items-center gap-2"><x-ui.icon name="phone" class="size-4 text-gray-400" /> Phones & notifications</h2>
        @if ($devices->isNotEmpty())
            <button wire:click="sendTest" wire:loading.attr="disabled" class="btn-secondary btn-sm"><x-ui.icon name="bell" class="size-4" /> Send test</button>
        @endif
    </div>
    <div class="card-body space-y-3 text-sm">
        @unless ($firebaseReady)
            <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">Firebase is not set up on this server (FIREBASE_CREDENTIALS), so notifications are only saved to the app's list.</p>
        @endunless

        @forelse ($devices as $device)
            <div wire:key="dev-{{ $device->id }}" x-data="{ show: false, copied: false }" class="rounded-lg border border-gray-200 p-3">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-medium text-gray-900">{{ $device->device_name ?: ucfirst($device->platform).' phone' }}</p>
                        <p class="text-xs text-gray-500">
                            {{ $device->platform === 'ios' ? 'iOS' : 'Android' }}@if ($device->app_version) · app {{ $device->app_version }}@endif
                            · last opened {{ $device->last_seen_at?->diffForHumans() ?? 'never' }}
                        </p>
                    </div>
                    <button wire:click="removeDevice({{ $device->id }})" data-confirm-title="Remove phone?" wire:confirm="Remove this phone? It stops receiving notifications until the app is opened again." class="text-xs text-red-600 hover:underline">Remove</button>
                </div>

                <p class="mt-2 text-xs font-medium text-gray-500">FCM token</p>
                <div class="mt-1 flex items-start gap-2">
                    <code class="min-w-0 flex-1 rounded bg-gray-50 px-2 py-1 font-mono text-[11px] text-gray-700"
                          :class="show ? 'break-all' : 'truncate'"
                          x-text="show ? @js($device->token) : @js(\Illuminate\Support\Str::mask($device->token, '•', 12, max(0, strlen($device->token) - 18)))"></code>
                    <button type="button" x-on:click="show = ! show" class="shrink-0 text-xs font-medium text-indigo-600 hover:underline" x-text="show ? 'Hide' : 'Show'"></button>
                    <button type="button" x-on:click="navigator.clipboard.writeText(@js($device->token)); copied = true; setTimeout(() => copied = false, 1500)"
                            class="shrink-0 text-xs font-medium text-indigo-600 hover:underline" x-text="copied ? 'Copied' : 'Copy'"></button>
                </div>
            </div>
        @empty
            <p class="text-gray-500">No phone registered yet. It appears here after {{ $member->name }} signs in to the app and allows notifications.</p>
        @endforelse

        @if ($lastPush)
            <p class="text-xs text-gray-500">Last notification on phone: <span class="font-medium text-gray-700">{{ $lastPush->title }}</span> · {{ $lastPush->pushed_at->diffForHumans() }}</p>
        @endif
    </div>
</div>
