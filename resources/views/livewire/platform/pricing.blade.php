<div>
    <x-ui.page-header title="Pricing" subtitle="Single plan, all features included, no setup fee. Gyms pay per active member per month." />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6">
            <div class="card card-body">
                <p class="text-xs font-medium text-gray-500 uppercase">Current price</p>
                <p class="mt-2 text-4xl font-bold">{{ money($current) }}</p>
                <p class="text-sm text-gray-500">per active member / month</p>
                @if ($rates->isEmpty())<p class="mt-2 text-xs text-gray-500">Using the platform default ({{ money($default) }}).</p>@endif
            </div>

            <form wire:submit="save" class="card card-body space-y-4">
                <h2 class="card-title">Schedule a price change</h2>
                <x-ui.field label="Price per active member (₹)" name="price" required><input wire:model="price" type="number" min="0" step="0.01" class="input"></x-ui.field>
                <x-ui.field label="Effective from" name="effectiveFrom" required hint="Applies to billing months starting on/after this date."><input wire:model="effectiveFrom" type="date" class="input"></x-ui.field>
                <x-ui.field label="Note" name="notes"><input wire:model="notes" class="input" placeholder="Reason for change"></x-ui.field>
                <button type="submit" class="btn-primary w-full">Save price</button>
                <p class="text-xs text-gray-500">Existing invoices keep their original rate. Only new invoices use the new price.</p>
            </form>
        </div>

        <div class="card lg:col-span-2">
            <div class="card-header"><h2 class="card-title">Price history</h2></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Price</th><th>Effective from</th><th>Note</th><th>Set by</th><th>Created</th></tr></thead>
                    <tbody>
                        @forelse ($rates as $r)
                            <tr wire:key="r-{{ $r->id }}">
                                <td class="font-semibold">{{ money($r->price_per_member) }}</td>
                                <td>
                                    {{ $r->effective_from->format('d M Y') }}
                                    @if ($r->effective_from->isFuture())<x-ui.badge color="blue">Scheduled</x-ui.badge>@endif
                                </td>
                                <td>{{ $r->notes ?: '—' }}</td>
                                <td>{{ $r->creator?->name ?? 'System' }}</td>
                                <td class="text-gray-500">{{ $r->created_at->format('d M Y') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-ui.empty message="No price changes yet." icon="tag" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
