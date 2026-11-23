<div>
    <x-ui.page-header :title="'Welcome, '.auth()->user()->name" :subtitle="now()->format('l, d F Y')">
        <x-slot:actions>
            <a href="{{ route('gym.attendance.index') }}" class="btn-secondary"><x-ui.icon name="calendar" class="size-4" /> Check-in</a>
            <a href="{{ route('gym.members.create') }}" class="btn-primary"><x-ui.icon name="plus" class="size-4" /> Add member</a>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($stats['pending_members'] > 0)
        @can('members.manage')
            <a href="{{ route('gym.members.index', ['status' => 'pending']) }}" class="mb-4 flex items-center justify-between gap-3 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200 hover:bg-amber-100">
                <span><strong>{{ $stats['pending_members'] }} app {{ \Illuminate\Support\Str::plural('sign-up', $stats['pending_members']) }}</strong> waiting for your approval.</span>
                <span class="font-semibold">Review →</span>
            </a>
        @endcan
    @endif

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-ui.stat label="Total members" :value="number_format($stats['total_members'])" icon="users" :href="route('gym.members.index')" />
        <x-ui.stat label="Active members" :value="number_format($stats['active_members'])" icon="check" color="green" hint="Valid membership today" :href="route('gym.members.index', ['membership' => 'active'])" />
        <x-ui.stat label="Inactive" :value="number_format($stats['inactive_members'])" icon="user" color="gray" :href="route('gym.members.index', ['status' => 'inactive'])" />
        <x-ui.stat label="Expired" :value="number_format($stats['expired_members'])" icon="clock" color="red" hint="Need renewal" :href="route('gym.members.index', ['membership' => 'expired'])" />
        <x-ui.stat label="New enquiries" :value="number_format($stats['new_enquiries'])" icon="inbox" color="blue" :hint="$stats['followups_today'].' follow-ups due'" :href="route('gym.enquiries.index')" />
        <x-ui.stat label="Revenue this month" :value="money($stats['monthly_revenue'], false)" icon="cash" color="green" :href="route('gym.payments.index')" />
        <x-ui.stat label="Pending fees" :value="money($stats['pending_fees'], false)" icon="receipt" color="amber" :href="route('gym.memberships.index', ['filter' => 'dues'])" />
        <x-ui.stat label="Today's attendance" :value="number_format($stats['today_attendance'])" icon="calendar" :hint="$stats['currently_inside'].' currently inside'" :href="route('gym.attendance.index')" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Member growth</h2><span class="text-xs text-gray-500">Last 6 months</span></div>
            <div class="card-body">
                <div class="h-64" wire:ignore x-data x-init="renderChart($refs.canvas, {
                    type: 'bar',
                    data: { labels: @js($growth['labels']), datasets: [
                        { type: 'line', label: 'Total members', data: @js($growth['total']), borderColor: '#EE4444', backgroundColor: '#EE4444', tension: .3 },
                        { label: 'New members', data: @js($growth['new']), backgroundColor: 'rgba(238,68,68,.35)', borderRadius: 6 },
                    ] }
                })"><canvas x-ref="canvas"></canvas></div>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h2 class="card-title">Revenue</h2><span class="text-xs text-gray-500">Payments collected</span></div>
            <div class="card-body">
                <div class="h-64" wire:ignore x-data x-init="renderChart($refs.canvas, {
                    type: 'bar',
                    data: { labels: @js($revenue['labels']), datasets: [{ label: 'Revenue (₹)', data: @js($revenue['values']), backgroundColor: '#34d399', borderRadius: 6 }] }
                })"><canvas x-ref="canvas"></canvas></div>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-1">
            <div class="card-header"><h2 class="card-title">Expiring in 7 days</h2><a href="{{ route('gym.memberships.index', ['filter' => 'expiring']) }}" class="text-xs text-indigo-600 hover:underline">View all</a></div>
            <ul class="divide-y divide-gray-100">
                @forelse ($expiries as $m)
                    <li wire:key="exp-{{ $m->id }}" class="flex items-center justify-between gap-3 px-5 py-3">
                        <a href="{{ route('gym.members.show', $m->member_id) }}" class="min-w-0">
                            <p class="truncate text-sm font-medium text-gray-900">{{ $m->member->name }}</p>
                            <p class="truncate text-xs text-gray-500">{{ $m->plan_name }}</p>
                        </a>
                        <x-ui.badge :color="$m->end_date->isToday() ? 'red' : 'amber'">{{ $m->end_date->isToday() ? 'Today' : $m->end_date->format('d M') }}</x-ui.badge>
                    </li>
                @empty
                    <li><x-ui.empty message="No memberships expiring this week." icon="check" /></li>
                @endforelse
            </ul>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Recent payments</h2><a href="{{ route('gym.payments.index') }}" class="text-xs text-indigo-600 hover:underline">View all</a></div>
            <ul class="divide-y divide-gray-100">
                @forelse ($payments as $p)
                    <li wire:key="pay-{{ $p->id }}" class="flex items-center justify-between gap-3 px-5 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-gray-900">{{ $p->member->name }}</p>
                            <p class="text-xs text-gray-500">{{ $p->paid_at->format('d M, h:i A') }} · {{ $p->method->label() }}</p>
                        </div>
                        <span class="text-sm font-semibold text-emerald-600">{{ money($p->amount, false) }}</span>
                    </li>
                @empty
                    <li><x-ui.empty message="No payments recorded yet." icon="cash" /></li>
                @endforelse
            </ul>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Recent enquiries</h2><a href="{{ route('gym.enquiries.index') }}" class="text-xs text-indigo-600 hover:underline">View all</a></div>
            <ul class="divide-y divide-gray-100">
                @forelse ($enquiries as $e)
                    <li wire:key="enq-{{ $e->id }}" class="flex items-center justify-between gap-3 px-5 py-3">
                        <a href="{{ route('gym.enquiries.show', $e) }}" class="min-w-0">
                            <p class="truncate text-sm font-medium text-gray-900">{{ $e->name }}</p>
                            <p class="text-xs text-gray-500">{{ $e->source->label() }} · {{ $e->created_at->diffForHumans() }}</p>
                        </a>
                        <x-ui.badge :color="$e->status->color()">{{ $e->status->label() }}</x-ui.badge>
                    </li>
                @empty
                    <li><x-ui.empty message="No enquiries yet. Share your QR code!" /></li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
