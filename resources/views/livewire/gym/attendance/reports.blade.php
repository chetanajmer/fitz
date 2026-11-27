<div>
    <x-ui.page-header title="Attendance reports">
        <x-slot:actions>
            <div class="inline-flex rounded-lg border border-gray-300 bg-white p-0.5">
                <button wire:click="$set('view', 'daily')" class="rounded-md px-3 py-1.5 text-sm font-medium {{ $view === 'daily' ? 'bg-gray-900 text-white' : 'text-gray-600' }}">Daily</button>
                <button wire:click="$set('view', 'monthly')" class="rounded-md px-3 py-1.5 text-sm font-medium {{ $view === 'monthly' ? 'bg-gray-900 text-white' : 'text-gray-600' }}">Monthly</button>
            </div>
            @if ($view === 'daily')
                <input wire:model.live="date" type="date" class="input w-auto">
            @else
                <input wire:model.live="month" type="month" class="input w-auto">
            @endif
            <button onclick="window.print()" class="btn-secondary no-print"><x-ui.icon name="printer" class="size-4" /> Print</button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($view === 'daily')
        <div class="mb-6 grid grid-cols-3 gap-4">
            <x-ui.stat label="Check-ins" :value="$total" icon="calendar" />
            <x-ui.stat label="Checked out" :value="$checkedOut" icon="check" color="green" />
            <x-ui.stat label="Avg. session" :value="$avgDuration" icon="clock" color="blue" />
        </div>
        <div class="card mb-6">
            <div class="card-header"><h2 class="card-title">Check-ins by hour</h2></div>
            <div class="card-body"><div class="h-56" wire:key="chart-d-{{ $date }}" x-data x-init="renderChart($refs.c, { type: 'bar', data: { labels: @js($labels), datasets: [{ label: 'Check-ins', data: @js($values), backgroundColor: chartColors.primary, borderRadius: 4 }] } })"><canvas x-ref="c"></canvas></div></div>
        </div>
        <div class="card overflow-x-auto">
            <table class="table">
                <thead><tr><th>Member</th><th>Code</th><th>In</th><th>Out</th><th>Duration</th><th>Method</th></tr></thead>
                <tbody>
                    @forelse ($records as $a)
                        <tr wire:key="r-{{ $a->id }}">
                            <td class="font-medium">{{ $a->member->name }}</td>
                            <td>{{ $a->member->member_code }}</td>
                            <td>{{ $a->check_in_at->format('h:i A') }}</td>
                            <td>{{ $a->check_out_at?->format('h:i A') ?? '—' }}</td>
                            <td>{{ $a->durationLabel() ?? '—' }}</td>
                            <td>{{ $a->methodLabel() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-ui.empty message="No attendance on this day." icon="calendar" /></td></tr>
                    @endforelse
                </tbody>
            </table>
            @if ($records->hasPages())<div class="border-t border-gray-100 px-4 py-3">{{ $records->links() }}</div>@endif
        </div>
    @else
        <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-ui.stat label="Total visits" :value="number_format($total)" icon="calendar" />
            <x-ui.stat label="Unique members" :value="number_format($unique)" icon="users" color="green" />
            <x-ui.stat label="Avg. per day" :value="$avg" icon="chart" color="blue" />
            <x-ui.stat label="Busiest day" :value="$busiest" icon="clock" color="amber" />
        </div>
        <div class="card mb-6">
            <div class="card-header"><h2 class="card-title">Daily check-ins</h2></div>
            <div class="card-body"><div class="h-56" wire:key="chart-m-{{ $month }}" x-data x-init="renderChart($refs.c, { type: 'line', data: { labels: @js($labels), datasets: [{ label: 'Check-ins', data: @js($values), borderColor: chartColors.primary, backgroundColor: chartColors.primarySoft, fill: true, tension: .3 }] } })"><canvas x-ref="c"></canvas></div></div>
        </div>
        <div class="card overflow-x-auto">
            <div class="card-header"><h2 class="card-title">Visits per member</h2></div>
            <table class="table">
                <thead><tr><th>#</th><th>Member</th><th>Code</th><th class="text-right">Visits</th></tr></thead>
                <tbody>
                    @forelse ($topMembers as $i => $row)
                        <tr wire:key="tm-{{ $row->member_id }}">
                            <td class="text-gray-500">{{ $topMembers->firstItem() + $i }}</td>
                            <td class="font-medium">{{ $row->member?->name }}</td>
                            <td>{{ $row->member?->member_code }}</td>
                            <td class="text-right font-semibold">{{ $row->visits }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><x-ui.empty message="No attendance this month." icon="calendar" /></td></tr>
                    @endforelse
                </tbody>
            </table>
            @if ($topMembers->hasPages())<div class="border-t border-gray-100 px-4 py-3">{{ $topMembers->links() }}</div>@endif
        </div>
    @endif
</div>
