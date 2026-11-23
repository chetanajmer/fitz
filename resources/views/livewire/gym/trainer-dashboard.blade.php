<div>
    <x-ui.page-header :title="'Hi, '.auth()->user()->name" subtitle="Your assigned members at a glance." />

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-ui.stat label="Assigned members" :value="$stats['assigned']" icon="users" :href="route('gym.members.index')" />
        <x-ui.stat label="Active" :value="$stats['active']" icon="check" color="green" />
        <x-ui.stat label="Expiring (7 days)" :value="$stats['expiring']" icon="clock" color="amber" />
        <x-ui.stat label="Checked in today" :value="$stats['today_attendance']" icon="calendar" color="blue" :href="route('gym.attendance.index')" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <div class="card-header"><h2 class="card-title">My members</h2><a href="{{ route('gym.members.index') }}" class="text-xs text-indigo-600 hover:underline">View all</a></div>
            <ul class="grid divide-y divide-gray-100 sm:grid-cols-2 sm:divide-y-0">
                @forelse ($members as $member)
                    @php($state = $member->membershipState())
                    <li wire:key="m-{{ $member->id }}">
                        <a href="{{ route('gym.members.show', $member) }}" class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50">
                            <img src="{{ $member->photoUrl() }}" alt="" class="size-9 rounded-full object-cover">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ $member->name }}</p>
                                <p class="truncate text-xs text-gray-500">{{ $member->fitness_goal ?: 'No goal set' }}</p>
                            </div>
                            <x-ui.badge :color="['active' => 'green', 'expired' => 'red', 'upcoming' => 'blue'][$state] ?? 'gray'">{{ ucfirst($state) }}</x-ui.badge>
                        </a>
                    </li>
                @empty
                    <li class="sm:col-span-2"><x-ui.empty message="No members have been assigned to you yet." /></li>
                @endforelse
            </ul>
        </div>

        <div class="space-y-6">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Today's check-ins</h2></div>
                <ul class="divide-y divide-gray-100">
                    @forelse ($todayAttendance as $a)
                        <li wire:key="a-{{ $a->id }}" class="flex justify-between px-5 py-2.5 text-sm">
                            <span>{{ $a->member->name }}</span>
                            <span class="text-gray-500">{{ $a->check_in_at->format('h:i A') }}</span>
                        </li>
                    @empty
                        <li><x-ui.empty message="No check-ins yet today." icon="calendar" /></li>
                    @endforelse
                </ul>
            </div>
            <div class="card">
                <div class="card-header"><h2 class="card-title">Expiring soon</h2></div>
                <ul class="divide-y divide-gray-100">
                    @forelse ($expiring as $m)
                        <li wire:key="e-{{ $m->id }}" class="flex justify-between px-5 py-2.5 text-sm">
                            <span>{{ $m->member->name }}</span>
                            <x-ui.badge color="amber">{{ $m->end_date->format('d M') }}</x-ui.badge>
                        </li>
                    @empty
                        <li><x-ui.empty message="Nothing expiring this week." icon="check" /></li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
