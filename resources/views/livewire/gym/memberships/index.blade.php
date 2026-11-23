<div>
    <x-ui.page-header title="Memberships" :subtitle="'Pending fees across all members: '.money($totalDue)" />

    <div class="mb-4 flex gap-6 overflow-x-auto border-b border-gray-200">
        @foreach (['active' => 'Active', 'expiring' => 'Expiring (15 days)', 'expired' => 'Expired', 'upcoming' => 'Upcoming', 'dues' => 'Pending fees', 'all' => 'All'] as $key => $label)
            <button wire:key="f-{{ $key }}" wire:click="$set('filter', '{{ $key }}')" class="tab {{ $filter === $key ? 'tab-active' : '' }}">{{ $label }}</button>
        @endforeach
    </div>

    <div class="card">
        <div class="border-b border-gray-100 p-4">
            <div class="relative max-w-md">
                <x-ui.icon name="search" class="pointer-events-none absolute top-2.5 left-3 size-4 text-gray-400" />
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search member…" class="input pl-9">
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Member</th><th>Plan</th><th>Start</th><th>End</th><th>Total</th><th>Balance</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse ($memberships as $ms)
                        @php($status = $ms->computedStatus())
                        <tr wire:key="ms-{{ $ms->id }}">
                            <td><a href="{{ route('gym.members.show', $ms->member_id) }}" class="font-medium text-gray-900 hover:text-indigo-600">{{ $ms->member->name }}</a><p class="text-xs text-gray-500">{{ $ms->member->phone }}</p></td>
                            <td>{{ $ms->plan_name }}</td>
                            <td>{{ $ms->start_date->format('d M Y') }}</td>
                            <td>
                                {{ $ms->end_date->format('d M Y') }}
                                @if ($status->value === 'active')<span class="block text-xs text-gray-500">{{ $ms->daysRemaining() }} days left</span>@endif
                            </td>
                            <td>{{ money($ms->total_amount) }}</td>
                            <td class="{{ $ms->balance() > 0 ? 'font-medium text-amber-700' : 'text-emerald-600' }}">{{ $ms->balance() > 0 ? money($ms->balance()) : 'Paid' }}</td>
                            <td><x-ui.badge :color="$status->color()">{{ $status->label() }}</x-ui.badge></td>
                            <td class="text-right">
                                <a href="{{ route('gym.members.show', [$ms->member_id, 'tab' => 'memberships']) }}" class="text-xs font-medium text-indigo-600 hover:underline">
                                    {{ in_array($filter, ['expired', 'expiring']) ? 'Renew' : ($ms->balance() > 0 ? 'Collect' : 'Open') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-ui.empty message="No memberships in this view." icon="card" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($memberships->hasPages())<div class="border-t border-gray-100 px-4 py-3">{{ $memberships->links() }}</div>@endif
    </div>
</div>
