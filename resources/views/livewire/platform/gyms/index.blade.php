<div>
    <x-ui.page-header title="Gyms" :subtitle="$gyms->total().' registered gyms'" />

    <div class="card">
        <div class="flex flex-col gap-3 border-b border-gray-100 p-4 md:flex-row">
            <div class="relative flex-1">
                <x-ui.icon name="search" class="pointer-events-none absolute top-2.5 left-3 size-4 text-gray-400" />
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Gym, city, slug or owner…" class="input pl-9">
            </div>
            <select wire:model.live="status" class="input md:w-44">
                <option value="">All statuses</option>
                @foreach (\App\Enums\GymStatus::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
            </select>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Gym</th><th>Owner</th><th>City</th><th>Members</th><th>Active (billable)</th><th>Outstanding</th><th>Status</th><th>Joined</th></tr></thead>
                <tbody>
                    @forelse ($gyms as $g)
                        <tr wire:key="g-{{ $g->id }}" class="cursor-pointer" onclick="window.location='{{ route('platform.gyms.show', $g->id) }}'">
                            <td><p class="font-medium text-gray-900">{{ $g->name }}</p><p class="text-xs text-gray-500">/{{ $g->slug }}</p></td>
                            <td><p>{{ $g->owner?->name ?? '—' }}</p><p class="text-xs text-gray-500">{{ $g->owner?->email }}</p></td>
                            <td>{{ $g->city ?: '—' }}</td>
                            <td>{{ $g->members_count }}</td>
                            <td class="font-medium">{{ $g->active_members_count }}</td>
                            <td class="{{ $g->outstanding > 0 ? 'font-medium text-red-600' : 'text-gray-500' }}">{{ money($g->outstanding, false) }}</td>
                            <td><x-ui.badge :color="$g->status->color()">{{ $g->status->label() }}</x-ui.badge></td>
                            <td class="text-gray-500">{{ $g->created_at->format('d M Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-ui.empty message="No gyms found." icon="building" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($gyms->hasPages())<div class="border-t border-gray-100 px-4 py-3">{{ $gyms->links() }}</div>@endif
    </div>
</div>
