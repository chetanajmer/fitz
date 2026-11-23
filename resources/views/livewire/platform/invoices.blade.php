<div>
    <x-ui.page-header title="Invoices & payments" :subtitle="'Outstanding across all gyms: '.money($outstanding)">
        <x-slot:actions>
            <form wire:submit="generate" class="flex items-center gap-2">
                <input wire:model="generateMonth" type="month" max="{{ now()->subMonthNoOverflow()->format('Y-m') }}" class="input w-auto">
                <button type="submit" class="btn-primary" wire:loading.attr="disabled">Generate invoices</button>
            </form>
        </x-slot:actions>
    </x-ui.page-header>
    @error('month') <p class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $message }}</p> @enderror

    <div class="mb-4 flex gap-6 border-b border-gray-200">
        <button wire:click="$set('tab', 'invoices')" class="tab {{ $tab === 'invoices' ? 'tab-active' : '' }}">Invoices</button>
        <button wire:click="$set('tab', 'payments')" class="tab {{ $tab === 'payments' ? 'tab-active' : '' }}">Payments received</button>
    </div>

    <div class="card">
        <div class="flex flex-col gap-3 border-b border-gray-100 p-4 md:flex-row">
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ $tab === 'invoices' ? 'Invoice no. or gym…' : 'Gym…' }}" class="input flex-1">
            @if ($tab === 'invoices')
                <select wire:model.live="status" class="input md:w-44">
                    <option value="">All</option>
                    <option value="outstanding">Outstanding</option>
                    <option value="overdue">Overdue</option>
                    @foreach (\App\Enums\InvoiceStatus::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                </select>
            @endif
        </div>

        <div class="overflow-x-auto">
            @if ($tab === 'invoices')
                <table class="table">
                    <thead><tr><th>Invoice</th><th>Gym</th><th>Period</th><th>Members × rate</th><th>Total</th><th>Balance</th><th>Due</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($invoices as $inv)
                            <tr wire:key="i-{{ $inv->id }}">
                                <td class="font-medium">{{ $inv->invoice_number }}</td>
                                <td><a href="{{ route('platform.gyms.show', $inv->gym_id) }}" class="hover:text-indigo-600">{{ $inv->gym->name }}</a></td>
                                <td>{{ $inv->period_start->format('M Y') }}</td>
                                <td>{{ $inv->active_members }} × {{ money($inv->rate_per_member) }}</td>
                                <td class="font-semibold">{{ money($inv->total) }}</td>
                                <td class="{{ $inv->balance() > 0 ? 'font-medium text-red-600' : '' }}">{{ money($inv->balance()) }}</td>
                                <td class="{{ $inv->isOverdue() ? 'font-medium text-red-600' : '' }}">{{ $inv->due_on->format('d M Y') }}</td>
                                <td><x-ui.badge :color="$inv->status->color()">{{ $inv->status->label() }}</x-ui.badge></td>
                                <td class="space-x-2 text-right">
                                    <a href="{{ route('platform.invoices.show', $inv) }}" target="_blank" class="text-xs font-medium text-indigo-600 hover:underline">View</a>
                                    @if ($inv->balance() > 0)
                                        <button wire:click="openPayment({{ $inv->id }})" class="text-xs font-medium text-emerald-600 hover:underline">Record payment</button>
                                    @endif
                                    @if ($inv->status->value !== 'void')
                                        <button wire:click="openAdjust({{ $inv->id }})" class="text-xs font-medium text-gray-600 hover:underline">Adjust</button>
                                        @if ((float) $inv->amount_paid === 0.0)
                                            <button wire:click="void({{ $inv->id }})" wire:confirm="Void this invoice?" class="text-xs font-medium text-red-600 hover:underline">Void</button>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9"><x-ui.empty message="No invoices found." icon="receipt" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
                @if ($invoices->hasPages())<div class="border-t border-gray-100 px-4 py-3">{{ $invoices->links() }}</div>@endif
            @else
                <table class="table">
                    <thead><tr><th>Date</th><th>Gym</th><th>Invoice</th><th>Method</th><th>Reference</th><th>Recorded by</th><th class="text-right">Amount</th></tr></thead>
                    <tbody>
                        @forelse ($payments as $p)
                            <tr wire:key="sp-{{ $p->id }}">
                                <td>{{ $p->paid_at->format('d M Y') }}</td>
                                <td>{{ $p->gym->name }}</td>
                                <td>{{ $p->invoice->invoice_number }}</td>
                                <td>{{ $p->method->label() }}</td>
                                <td>{{ $p->reference ?: '—' }}</td>
                                <td>{{ $p->recorder?->name ?? '—' }}</td>
                                <td class="text-right font-semibold text-emerald-600">{{ money($p->amount) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><x-ui.empty message="No payments recorded." icon="cash" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
                @if ($payments->hasPages())<div class="border-t border-gray-100 px-4 py-3">{{ $payments->links() }}</div>@endif
            @endif
        </div>
    </div>

    <x-ui.modal wire-model="showPayment" :title="'Record payment'.($selected ? ' · '.$selected->invoice_number : '')">
        <form wire:submit="savePayment" class="space-y-4">
            @if ($selected)<p class="text-sm text-gray-600">{{ $selected->gym->name }} — balance <strong>{{ money($selected->balance()) }}</strong></p>@endif
            @error('amount') <div class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $message }}</div> @enderror
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Amount (₹)" name="payment.amount" required><input wire:model="payment.amount" type="number" step="0.01" min="0.01" class="input"></x-ui.field>
                <x-ui.field label="Method" name="payment.method"><select wire:model="payment.method" class="input">@foreach (\App\Enums\PaymentMethod::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></x-ui.field>
                <x-ui.field label="Reference" name="payment.reference"><input wire:model="payment.reference" class="input"></x-ui.field>
                <x-ui.field label="Paid at" name="payment.paid_at" required><input wire:model="payment.paid_at" type="datetime-local" class="input"></x-ui.field>
            </div>
            <x-ui.field label="Notes" name="payment.notes"><input wire:model="payment.notes" class="input"></x-ui.field>
            <div class="flex justify-end gap-2">
                <button type="button" wire:click="$set('showPayment', false)" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">Record</button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal wire-model="showAdjust" title="Adjust invoice" max-width="md">
        <form wire:submit="saveAdjust" class="space-y-4">
            @if ($selected)<p class="text-sm text-gray-600">Subtotal {{ money($selected->subtotal) }} ({{ $selected->active_members }} × {{ money($selected->rate_per_member) }}). Use a negative value for a discount or credit.</p>@endif
            <x-ui.field label="Adjustment (₹)" name="adjustment"><input wire:model="adjustment" type="number" step="0.01" class="input"></x-ui.field>
            <x-ui.field label="Notes (shown on invoice)" name="adjustNotes"><textarea wire:model="adjustNotes" rows="2" class="input"></textarea></x-ui.field>
            <div class="flex justify-end gap-2">
                <button type="button" wire:click="$set('showAdjust', false)" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">Save</button>
            </div>
        </form>
    </x-ui.modal>
</div>
