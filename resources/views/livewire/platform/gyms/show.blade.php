<div>
    <div class="card card-body mb-6 flex flex-col gap-4 md:flex-row md:items-center">
        @if ($g->logoUrl())
            <img src="{{ $g->logoUrl() }}" alt="" class="size-16 rounded-xl object-contain ring-1 ring-gray-200">
        @else
            <div class="flex size-16 items-center justify-center rounded-xl text-xl font-bold text-white" style="background: {{ $g->primaryColor() }}">{{ $g->initials() }}</div>
        @endif
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-semibold">{{ $g->name }}</h1>
                <x-ui.badge :color="$g->status->color()">{{ $g->status->label() }}</x-ui.badge>
            </div>
            <p class="text-sm text-gray-500">/{{ $g->slug }} · {{ $g->city }} · registered {{ $g->created_at->format('d M Y') }}</p>
            <p class="text-sm text-gray-500">Owner: <strong class="text-gray-700">{{ $g->owner?->name }}</strong> · {{ $g->owner?->email }} · {{ $g->owner?->phone }}</p>
            @if ($g->status_reason)<p class="mt-1 text-sm text-amber-700">Reason: {{ $g->status_reason }} ({{ $g->status_changed_at?->format('d M Y') }})</p>@endif
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ $g->publicUrl() }}" target="_blank" class="btn-secondary btn-sm">Public page ↗</a>
            @if ($g->status->value !== 'active')<button wire:click="openStatus('active')" class="btn-success btn-sm">Activate</button>@endif
            @if ($g->status->value !== 'suspended')<button wire:click="openStatus('suspended')" class="btn-secondary btn-sm">Suspend</button>@endif
            @if ($g->status->value !== 'deactivated')<button wire:click="openStatus('deactivated')" class="btn-danger btn-sm">Deactivate</button>@endif
        </div>
    </div>

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
        <x-ui.stat label="Members" :value="$counts['members']" icon="users" />
        <x-ui.stat label="Billable now" :value="$counts['billable']" icon="check" color="green" />
        <x-ui.stat label="Trainers" :value="$counts['trainers']" icon="whistle" color="gray" />
        <x-ui.stat label="This month (est.)" :value="money($estimate['amount'], false)" icon="receipt" color="blue" :hint="$estimate['members'].' × '.money($estimate['rate'])" />
        <x-ui.stat label="Outstanding" :value="money($outstanding, false)" icon="cash" :color="$outstanding > 0 ? 'red' : 'green'" />
    </div>

    <div class="mb-4 flex gap-6 border-b border-gray-200">
        <button wire:click="$set('tab', 'members')" class="tab {{ $tab === 'members' ? 'tab-active' : '' }}">Members</button>
        <button wire:click="$set('tab', 'invoices')" class="tab {{ $tab === 'invoices' ? 'tab-active' : '' }}">Invoices</button>
    </div>

    @if ($tab === 'members')
        <div class="card">
            <div class="flex flex-col gap-3 border-b border-gray-100 p-4 md:flex-row">
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search members…" class="input flex-1">
                <select wire:model.live="memberFilter" class="input md:w-48">
                    <option value="">All members</option>
                    <option value="billable">Billable (active + valid)</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Member</th><th>Phone</th><th>Joined</th><th>Plan</th><th>Valid till</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($members as $m)
                            <tr wire:key="m-{{ $m->id }}">
                                <td><p class="font-medium">{{ $m->name }}</p><p class="text-xs text-gray-500">{{ $m->member_code }}</p></td>
                                <td>{{ $m->phone }}</td>
                                <td>{{ $m->joining_date->format('d M Y') }}</td>
                                <td>{{ $m->latestMembership?->plan_name ?? '—' }}</td>
                                <td>{{ $m->latestMembership?->end_date->format('d M Y') ?? '—' }}</td>
                                <td><x-ui.badge :color="$m->status->color()">{{ $m->status->label() }}</x-ui.badge></td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-ui.empty message="No members." icon="users" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($members->hasPages())<div class="border-t border-gray-100 px-4 py-3">{{ $members->links() }}</div>@endif
        </div>
    @else
        <div class="card">
            <div class="card-header"><h2 class="card-title">SaaS invoices</h2><button wire:click="generateInvoice" class="btn-secondary btn-sm">Generate last month</button></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Invoice</th><th>Period</th><th>Members</th><th>Rate</th><th>Total</th><th>Paid</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($invoices as $inv)
                            <tr wire:key="i-{{ $inv->id }}">
                                <td class="font-medium">{{ $inv->invoice_number }}</td>
                                <td>{{ $inv->period_start->format('M Y') }}</td>
                                <td>{{ $inv->active_members }}</td>
                                <td>{{ money($inv->rate_per_member) }}</td>
                                <td class="font-semibold">{{ money($inv->total) }}</td>
                                <td>{{ money($inv->amount_paid) }}</td>
                                <td><x-ui.badge :color="$inv->status->color()">{{ $inv->status->label() }}</x-ui.badge></td>
                                <td class="text-right"><a href="{{ route('platform.invoices.show', $inv) }}" target="_blank" class="text-xs font-medium text-indigo-600 hover:underline">View</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="8"><x-ui.empty message="No invoices yet." icon="receipt" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($invoices->hasPages())<div class="border-t border-gray-100 px-4 py-3">{{ $invoices->links() }}</div>@endif
        </div>
    @endif

    <x-ui.modal wire-model="showStatusModal" :title="'Set gym to '.ucfirst($newStatus)" max-width="md">
        <form wire:submit="changeStatus" class="space-y-4">
            <p class="text-sm text-gray-600">
                @if ($newStatus === 'active') The gym's staff and members regain access and the public page goes live.
                @elseif ($newStatus === 'suspended') Staff and members are blocked and the public page is hidden. Data is kept; you can reactivate anytime.
                @else The gym is closed: access is blocked, the public page is hidden and it is excluded from future billing. Data is kept. @endif
            </p>
            @if ($newStatus !== 'active')
                <x-ui.field label="Reason" name="reason" required><input wire:model="reason" class="input" placeholder="e.g. Unpaid invoices"></x-ui.field>
            @endif
            <div class="flex justify-end gap-2">
                <button type="button" wire:click="$set('showStatusModal', false)" class="btn-secondary">Cancel</button>
                <button type="submit" class="{{ $newStatus === 'deactivated' ? 'btn-danger' : 'btn-primary' }}">Confirm</button>
            </div>
        </form>
    </x-ui.modal>
</div>
