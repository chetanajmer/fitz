<div>
    <x-ui.page-header title="App leads" subtitle="People who tried to log in to the member app but aren't connected to any active gym. Call them back with gym options near them." />

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-ui.stat label="New (not called)" :value="$stats['new']" icon="inbox" color="blue" />
        <x-ui.stat label="Open" :value="$stats['open']" icon="users" />
        <x-ui.stat label="Follow-ups due" :value="$stats['due']" icon="clock" :color="$stats['due'] ? 'amber' : 'gray'" />
        <x-ui.stat label="Connected to a gym" :value="$stats['converted']" icon="check" color="green" />
    </div>

    <div class="card">
        <div class="flex flex-col gap-3 border-b border-gray-100 p-4 md:flex-row">
            <div class="relative flex-1">
                <x-ui.icon name="search" class="pointer-events-none absolute top-2.5 left-3 size-4 text-gray-400" />
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Phone, name or city…" class="input pl-9">
            </div>
            <select wire:model.live="status" class="input md:w-48">
                <option value="open">Open (needs action)</option>
                <option value="due">Follow-up due</option>
                <option value="all">All</option>
                @foreach (\App\Enums\LeadStatus::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
            </select>
            <select wire:model.live="reason" class="input md:w-56">
                <option value="">Any reason</option>
                @foreach ($reasons as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Phone</th><th>Name / city</th><th>Reason</th><th>Previous gym</th><th>Attempts</th><th>Last tried</th><th>Follow-up</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse ($leads as $lead)
                        <tr wire:key="lead-{{ $lead->id }}">
                            <td>
                                <a href="tel:{{ $lead->phone }}" class="font-medium text-indigo-600 hover:underline">{{ $lead->phone }}</a>
                                <a href="https://wa.me/91{{ $lead->phone_digits }}" target="_blank" rel="noopener" class="block text-xs text-emerald-600">WhatsApp</a>
                            </td>
                            <td>
                                <p>{{ $lead->name ?: '—' }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ $lead->city ?: '' }}
                                    @if ($lead->mapUrl())<a href="{{ $lead->mapUrl() }}" target="_blank" rel="noopener" class="text-indigo-600">📍 map</a>@endif
                                </p>
                            </td>
                            <td><x-ui.badge :color="$lead->reason->color()">{{ $lead->reason->label() }}</x-ui.badge></td>
                            <td>{{ $lead->previousGym?->name ?? '—' }}</td>
                            <td>{{ $lead->request_count }}</td>
                            <td class="text-gray-500" title="{{ $lead->last_requested_at?->format('d M Y, h:i A') }}">{{ $lead->last_requested_at?->diffForHumans() }}</td>
                            <td class="{{ $lead->follow_up_date?->lte(today()) && in_array($lead->status->value, \App\Enums\LeadStatus::openValues()) ? 'font-medium text-amber-700' : '' }}">{{ $lead->follow_up_date?->format('d M') ?? '—' }}</td>
                            <td>
                                <x-ui.badge :color="$lead->status->color()">{{ $lead->status->label() }}</x-ui.badge>
                                @if ($lead->assignedGym)<p class="mt-0.5 text-xs text-gray-500">→ {{ $lead->assignedGym->name }}</p>@endif
                            </td>
                            <td class="text-right"><button wire:click="open({{ $lead->id }})" class="btn-secondary btn-sm">Update</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><x-ui.empty message="No leads in this view." icon="inbox" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($leads->hasPages())<div class="border-t border-gray-100 px-4 py-3">{{ $leads->links() }}</div>@endif
    </div>

    <x-ui.modal wire-model="showModal" :title="$selected ? 'Lead · '.$selected->phone : 'Lead'" max-width="xl">
        @if ($selected)
            <div class="mb-4 rounded-lg bg-gray-50 p-3 text-sm text-gray-600">
                <p><strong>{{ $selected->reason->label() }}</strong>@if ($selected->previousGym) — previously at {{ $selected->previousGym->name }}@endif.</p>
                <p class="mt-1">Tried {{ $selected->request_count }} time(s), first on {{ $selected->created_at->format('d M Y') }}.
                    @if ($selected->app_platform) App: {{ ucfirst($selected->app_platform) }} {{ $selected->app_version }}. @endif
                    @if ($selected->mapUrl()) <a href="{{ $selected->mapUrl() }}" target="_blank" rel="noopener" class="text-indigo-600 underline">View location</a>@endif
                </p>
                <a href="tel:{{ $selected->phone }}" class="btn-success btn-sm mt-2">Call {{ $selected->phone }}</a>
            </div>
        @endif
        <form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
            <x-ui.field label="Name" name="form.name"><input wire:model="form.name" class="input"></x-ui.field>
            <x-ui.field label="City / area" name="form.city"><input wire:model="form.city" class="input"></x-ui.field>
            <x-ui.field label="Status" name="form.status" required>
                <select wire:model.live="form.status" class="input">@foreach (\App\Enums\LeadStatus::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
            </x-ui.field>
            <x-ui.field label="Follow-up date" name="form.follow_up_date"><input wire:model="form.follow_up_date" type="date" class="input"></x-ui.field>
            <x-ui.field label="Connect to gym" name="form.assigned_gym_id" class="sm:col-span-2" hint="Active gyms, those in the lead's city first.">
                <select wire:model="form.assigned_gym_id" class="input">
                    <option value="">—</option>
                    @foreach ($suggestedGyms as $g)<option value="{{ $g->id }}">{{ $g->name }}{{ $g->city ? ' · '.$g->city : '' }}{{ $g->phone ? ' · '.$g->phone : '' }}</option>@endforeach
                </select>
            </x-ui.field>
            <x-ui.field label="Call notes" name="form.notes" class="sm:col-span-2"><textarea wire:model="form.notes" rows="3" class="input" placeholder="What did they say? Which gyms were suggested?"></textarea></x-ui.field>
            <div class="flex justify-end gap-2 sm:col-span-2">
                <button type="button" wire:click="$set('showModal', false)" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">Save</button>
            </div>
        </form>
    </x-ui.modal>
</div>
