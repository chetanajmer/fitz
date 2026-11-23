<x-layouts.print :title="'Invoice '.$invoice->invoice_number">
    <div class="flex items-start justify-between gap-6 border-b border-gray-200 pb-6">
        <div>
            <div class="flex items-center gap-2">
                <div class="flex size-10 items-center justify-center rounded-xl bg-indigo-600 font-bold text-white">G</div>
                <span class="text-xl font-bold">{{ $platform['name'] }}</span>
            </div>
            @if ($platform['address'])<p class="mt-2 text-sm whitespace-pre-line text-gray-500">{{ $platform['address'] }}</p>@endif
            <p class="text-sm text-gray-500">{{ $platform['email'] }} @if($platform['phone']) · {{ $platform['phone'] }} @endif</p>
            @if ($platform['gst'])<p class="text-sm text-gray-500">GSTIN: {{ $platform['gst'] }}</p>@endif
        </div>
        <div class="text-right text-sm">
            <p class="text-2xl font-bold tracking-wide text-indigo-600 uppercase">Invoice</p>
            <p class="mt-2"><span class="text-gray-500">Number:</span> <strong>{{ $invoice->invoice_number }}</strong></p>
            <p><span class="text-gray-500">Issued:</span> {{ $invoice->issued_on->format('d M Y') }}</p>
            <p><span class="text-gray-500">Due:</span> {{ $invoice->due_on->format('d M Y') }}</p>
            <p class="mt-2"><span class="rounded px-2 py-0.5 text-xs font-semibold uppercase {{ ['paid' => 'bg-emerald-100 text-emerald-700', 'partial' => 'bg-amber-100 text-amber-800', 'void' => 'bg-gray-100 text-gray-600'][$invoice->status->value] ?? 'bg-red-100 text-red-700' }}">{{ $invoice->status->label() }}</span></p>
        </div>
    </div>

    <div class="mt-6 text-sm">
        <p class="text-xs font-semibold text-gray-500 uppercase">Bill to</p>
        <p class="mt-1 font-semibold">{{ $invoice->meta['gym_name'] ?? $invoice->gym->name }}</p>
        <p class="text-gray-600">{{ $invoice->gym->owner?->name }} · {{ $invoice->meta['owner_email'] ?? $invoice->gym->owner?->email }}</p>
        <p class="text-gray-600">{{ trim($invoice->gym->address.', '.($invoice->meta['gym_city'] ?? $invoice->gym->city), ', ') }}</p>
    </div>

    <table class="mt-8 w-full text-sm">
        <thead><tr class="border-b border-gray-200 text-left text-xs text-gray-500 uppercase"><th class="py-2">Description</th><th class="py-2 text-right">Qty</th><th class="py-2 text-right">Rate</th><th class="py-2 text-right">Amount</th></tr></thead>
        <tbody>
            <tr class="border-b border-gray-100">
                <td class="py-3">Gymai subscription — active members<span class="block text-xs text-gray-500">{{ $invoice->period_start->format('d M Y') }} – {{ $invoice->period_end->format('d M Y') }} · {{ $invoice->meta['billing_rule'] ?? '' }}</span></td>
                <td class="py-3 text-right">{{ $invoice->active_members }}</td>
                <td class="py-3 text-right">{{ money($invoice->rate_per_member) }}</td>
                <td class="py-3 text-right">{{ money($invoice->subtotal) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="mt-6 ml-auto w-full max-w-xs space-y-1 text-sm">
        <div class="flex justify-between"><span class="text-gray-600">Subtotal</span><span>{{ money($invoice->subtotal) }}</span></div>
        @if ((float) $invoice->adjustment != 0)<div class="flex justify-between"><span class="text-gray-600">Adjustment</span><span>{{ money($invoice->adjustment) }}</span></div>@endif
        <div class="flex justify-between border-t border-gray-200 pt-1 text-base font-bold"><span>Total</span><span>{{ money($invoice->total) }}</span></div>
        <div class="flex justify-between text-gray-600"><span>Paid</span><span>{{ money($invoice->amount_paid) }}</span></div>
        <div class="flex justify-between font-semibold {{ $invoice->balance() > 0 ? 'text-red-600' : 'text-emerald-600' }}"><span>Balance due</span><span>{{ money($invoice->balance()) }}</span></div>
    </div>

    @if ($invoice->notes)<p class="mt-6 text-sm text-gray-600">Notes: {{ $invoice->notes }}</p>@endif

    @if ($invoice->payments->isNotEmpty())
        <h3 class="mt-10 text-xs font-semibold text-gray-500 uppercase">Payments received</h3>
        <table class="mt-2 w-full text-sm">
            <tbody>
                @foreach ($invoice->payments as $p)
                    <tr class="border-b border-gray-100"><td class="py-2">{{ $p->paid_at->format('d M Y') }}</td><td class="py-2">{{ $p->method->label() }}</td><td class="py-2">{{ $p->reference }}</td><td class="py-2 text-right">{{ money($p->amount) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="mt-12 text-xs text-gray-500">No setup fee. Billed per active member with a valid membership during the period. This is a computer-generated invoice.</p>
</x-layouts.print>
