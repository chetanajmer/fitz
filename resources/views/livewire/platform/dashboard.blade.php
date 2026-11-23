<div>
    <x-ui.page-header title="Platform overview" :subtitle="'Current price: '.money($rate).' per active member / month'" />

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-ui.stat label="Total gyms" :value="$stats['total_gyms']" icon="building" :hint="$stats['active_gyms'].' active · '.$stats['suspended_gyms'].' suspended'" :href="route('platform.gyms.index')" />
        <x-ui.stat label="Active members" :value="number_format($stats['active_members'])" icon="users" color="green" :hint="number_format($stats['total_members']).' total members'" />
        <x-ui.stat label="Collected this month" :value="money($stats['collected_this_month'], false)" icon="cash" color="blue" :hint="money($stats['collected_total'], false).' all time'" :href="route('platform.invoices')" />
        <x-ui.stat label="Outstanding" :value="money($stats['outstanding'], false)" icon="receipt" :color="$stats['outstanding'] > 0 ? 'red' : 'green'" :hint="money($stats['billed_total'], false).' billed all time'" :href="route('platform.invoices', ['status' => 'outstanding'])" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <div class="card-header"><h2 class="card-title">Platform revenue</h2><span class="text-xs text-gray-500">Billed vs collected</span></div>
            <div class="card-body"><div class="h-64" wire:ignore x-data x-init="renderChart($refs.c, { type: 'bar', data: { labels: @js($revenue['labels']), datasets: [
                { label: 'Billed', data: @js($revenue['billed']), backgroundColor: '#c7d2fe', borderRadius: 6 },
                { label: 'Collected', data: @js($revenue['collected']), backgroundColor: '#4f46e5', borderRadius: 6 },
            ] } })"><canvas x-ref="c"></canvas></div></div>
        </div>
        <div class="card">
            <div class="card-header"><h2 class="card-title">New gyms</h2></div>
            <div class="card-body"><div class="h-64" wire:ignore x-data x-init="renderChart($refs.c, { type: 'line', data: { labels: @js($signups['labels']), datasets: [{ label: 'Signups', data: @js($signups['values']), borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,.12)', fill: true, tension: .3 }] } })"><canvas x-ref="c"></canvas></div></div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Top gyms by active members</h2></div>
            <ul class="divide-y divide-gray-100">
                @forelse ($topGyms as $g)
                    <li wire:key="tg-{{ $g->id }}" class="flex items-center justify-between px-5 py-3 text-sm">
                        <a href="{{ route('platform.gyms.show', $g->id) }}" class="font-medium hover:text-indigo-600">{{ $g->name }}</a>
                        <span class="text-gray-600">{{ $g->active_members_count }}</span>
                    </li>
                @empty
                    <li><x-ui.empty message="No gyms yet." icon="building" /></li>
                @endforelse
            </ul>
        </div>
        <div class="card">
            <div class="card-header"><h2 class="card-title">Recently registered</h2></div>
            <ul class="divide-y divide-gray-100">
                @forelse ($recentGyms as $g)
                    <li wire:key="rg-{{ $g->id }}" class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                        <a href="{{ route('platform.gyms.show', $g->id) }}" class="min-w-0"><p class="truncate font-medium">{{ $g->name }}</p><p class="truncate text-xs text-gray-500">{{ $g->owner?->name }} · {{ $g->city }}</p></a>
                        <span class="text-xs text-gray-500">{{ $g->created_at->diffForHumans() }}</span>
                    </li>
                @empty
                    <li><x-ui.empty message="No gyms yet." icon="building" /></li>
                @endforelse
            </ul>
        </div>
        <div class="card">
            <div class="card-header"><h2 class="card-title">Overdue invoices</h2></div>
            <ul class="divide-y divide-gray-100">
                @forelse ($overdue as $inv)
                    <li wire:key="od-{{ $inv->id }}" class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                        <div class="min-w-0"><p class="truncate font-medium">{{ $inv->gym->name }}</p><p class="text-xs text-gray-500">{{ $inv->invoice_number }} · due {{ $inv->due_on->format('d M') }}</p></div>
                        <span class="font-medium text-red-600">{{ money($inv->balance(), false) }}</span>
                    </li>
                @empty
                    <li><x-ui.empty message="No overdue invoices." icon="check" /></li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
