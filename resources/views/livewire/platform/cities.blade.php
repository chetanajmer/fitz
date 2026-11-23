<div>
    <x-ui.page-header title="Cities" subtitle="Where Gymai is live. Gyms pick their city from this list, and members choose a city first when they sign up in the app." />

    <div class="grid gap-6 lg:grid-cols-3">
        <form wire:submit="save" class="card card-body h-fit space-y-4">
            <h2 class="card-title">{{ $editingId ? 'Edit city' : 'Add a city' }}</h2>
            <x-ui.field label="City name" name="name" required><input wire:model="name" class="input" placeholder="Pune"></x-ui.field>
            <x-ui.field label="State" name="state"><input wire:model="state" class="input" placeholder="Maharashtra"></x-ui.field>
            <div class="flex gap-2">
                <button type="submit" class="btn-primary flex-1">{{ $editingId ? 'Save changes' : 'Add city' }}</button>
                @if ($editingId)<button type="button" wire:click="cancel" class="btn-secondary">Cancel</button>@endif
            </div>
            <p class="text-xs text-gray-500">Renaming a city also updates the city shown on its gyms.</p>
        </form>

        <div class="card lg:col-span-2">
            <div class="card-header"><h2 class="card-title">All cities ({{ $cities->count() }})</h2><span class="text-xs text-gray-500">{{ $cities->where('is_active', true)->count() }} live</span></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>City</th><th>State</th><th>Gyms</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($cities as $c)
                            <tr wire:key="city-{{ $c->id }}">
                                <td class="font-medium">{{ $c->name }}</td>
                                <td>{{ $c->state ?: '—' }}</td>
                                <td>{{ $c->gyms_count }}</td>
                                <td><x-ui.badge :color="$c->is_active ? 'green' : 'gray'">{{ $c->is_active ? 'Live' : 'Hidden' }}</x-ui.badge></td>
                                <td class="text-right whitespace-nowrap">
                                    <button type="button" wire:click="edit({{ $c->id }})" class="btn-ghost btn-sm">Edit</button>
                                    <button type="button" wire:click="toggle({{ $c->id }})" @if ($c->is_active) wire:confirm="Hide {{ $c->name }}? New gyms and app sign-ups won't see it or its gyms." @endif class="btn-secondary btn-sm">{{ $c->is_active ? 'Hide' : 'Make live' }}</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-ui.empty message="No cities yet. Add the first city Gymai is live in." icon="building" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
