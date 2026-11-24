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
                    <thead><tr><th>City</th><th>State</th><th>Gyms</th><th>Localities</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($cities as $c)
                            <tr wire:key="city-{{ $c->id }}">
                                <td class="font-medium">{{ $c->name }}</td>
                                <td>{{ $c->state ?: '—' }}</td>
                                <td>{{ $c->gyms_count }}</td>
                                <td><button type="button" wire:click="showLocalities({{ $c->id }})" class="font-medium text-brand-600 hover:underline">{{ $c->localities_count }}</button></td>
                                <td><x-ui.badge :color="$c->is_active ? 'green' : 'gray'">{{ $c->is_active ? 'Live' : 'Hidden' }}</x-ui.badge></td>
                                <td class="text-right whitespace-nowrap">
                                    <button type="button" wire:click="edit({{ $c->id }})" class="btn-ghost btn-sm">Edit</button>
                                    <button type="button" wire:click="toggle({{ $c->id }})" @if ($c->is_active) wire:confirm="Hide {{ $c->name }}? New gyms and app sign-ups won't see it or its gyms." @endif class="btn-secondary btn-sm">{{ $c->is_active ? 'Hide' : 'Make live' }}</button>
                                </td>
                            </tr>
                            @if ($localityCityId === $c->id)
                                <tr wire:key="loc-{{ $c->id }}">
                                    <td colspan="6" class="bg-gray-50 dark:bg-ink-800">
                                        <p class="mb-2 text-xs text-gray-500">Gyms add localities when they set up their profile. Rename duplicates or spelling mistakes; removing one keeps its gyms in {{ $c->name }} without a locality.</p>
                                        <div class="space-y-1">
                                            @forelse ($localities as $l)
                                                <div wire:key="l-{{ $l->id }}" class="flex items-center gap-2 text-sm">
                                                    @if ($editingLocalityId === $l->id)
                                                        <form wire:submit="renameLocality" class="flex flex-1 items-start gap-2">
                                                            <x-ui.field name="localityName" class="flex-1"><input wire:model="localityName" class="input py-1"></x-ui.field>
                                                            <button class="btn-primary btn-sm">Save</button>
                                                            <button type="button" wire:click="cancelLocality" class="btn-secondary btn-sm">Cancel</button>
                                                        </form>
                                                    @else
                                                        <span class="flex-1">{{ $l->name }} <span class="text-gray-500">· {{ $l->gyms_count }} {{ \Illuminate\Support\Str::plural('gym', $l->gyms_count) }}</span></span>
                                                        <button type="button" wire:click="editLocality({{ $l->id }})" class="btn-ghost btn-sm">Rename</button>
                                                        <button type="button" wire:click="deleteLocality({{ $l->id }})" wire:confirm="Remove {{ $l->name }}? Its gyms stay in {{ $c->name }} without a locality." class="btn-secondary btn-sm">Remove</button>
                                                    @endif
                                                </div>
                                            @empty
                                                <p class="text-sm text-gray-500">No localities yet.</p>
                                            @endforelse
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="6"><x-ui.empty message="No cities yet. Add the first city Gymai is live in." icon="building" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
