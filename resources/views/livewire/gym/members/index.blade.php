<div>
    <x-ui.page-header :title="$canManage ? 'Members' : 'My members'" :subtitle="$members->total().' member'.($members->total() === 1 ? '' : 's')">
        @if ($canManage)
            <x-slot:actions>
                <a href="{{ route('gym.members.import') }}" class="btn-secondary"><x-ui.icon name="upload" class="size-4" /> Import</a>
                <a href="{{ route('gym.members.create') }}" class="btn-primary"><x-ui.icon name="plus" class="size-4" /> Add member</a>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <div class="card">
        <div class="flex flex-col gap-3 border-b border-gray-100 p-4 lg:flex-row">
            <div class="relative flex-1">
                <x-ui.icon name="search" class="pointer-events-none absolute top-2.5 left-3 size-4 text-gray-400" />
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search name, phone, email or member ID…" class="input pl-9">
            </div>
            <select wire:model.live="membership" class="input lg:w-44">
                <option value="">All memberships</option>
                <option value="active">Active membership</option>
                <option value="upcoming">Upcoming</option>
                <option value="expired">Expired</option>
                <option value="none">No membership</option>
            </select>
            <select wire:model.live="status" class="input lg:w-36">
                <option value="">Any status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="pending">Pending approval</option>
            </select>
            @if ($trainers->isNotEmpty())
                <select wire:model.live="trainer" class="input lg:w-44">
                    <option value="">All trainers</option>
                    <option value="none">Unassigned</option>
                    @foreach ($trainers as $t)
                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                    @endforeach
                </select>
            @endif
            @if ($search || $status || $membership || $trainer)
                <button wire:click="clearFilters" class="btn-ghost">Clear</button>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr><th>Member</th><th>Phone</th><th>Plan</th><th>Valid till</th><th>Trainer</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($members as $member)
                        @php($latest = $member->latestMembership)
                        @php($state = $member->membershipState())
                        <tr wire:key="member-{{ $member->id }}">
                            <td>
                                <a href="{{ route('gym.members.show', $member) }}" class="flex items-center gap-3">
                                    <img src="{{ $member->photoUrl() }}" alt="" class="size-9 rounded-full object-cover">
                                    <div>
                                        <p class="font-medium text-gray-900">{{ $member->name }}</p>
                                        <p class="text-xs text-gray-500">{{ $member->member_code }}</p>
                                    </div>
                                </a>
                            </td>
                            <td>{{ $member->phone }}</td>
                            <td>{{ $latest?->plan_name ?? '—' }}</td>
                            <td>
                                @if ($latest)
                                    <x-ui.badge :color="['active' => 'green', 'expired' => 'red', 'upcoming' => 'blue'][$state] ?? 'gray'">
                                        {{ $latest->end_date->format('d M Y') }}
                                    </x-ui.badge>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td>{{ $member->trainer?->name ?? '—' }}</td>
                            <td><x-ui.badge :color="$member->status->color()">{{ $member->status->label() }}</x-ui.badge></td>
                            <td class="text-right"><a href="{{ route('gym.members.show', $member) }}" class="text-sm font-medium text-indigo-600 hover:underline">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-ui.empty message="No members match your filters." icon="users" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($members->hasPages())
            <div class="border-t border-gray-100 px-4 py-3">{{ $members->links() }}</div>
        @endif
    </div>
</div>
