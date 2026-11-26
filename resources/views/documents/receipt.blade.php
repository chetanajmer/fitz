<x-layouts.print :title="'Receipt '.$payment->receipt_number">
    @php($gst = $payment->membership?->gstBreakup((float) $payment->amount))
    @include('documents.partials.gym-header', ['docTitle' => 'Payment receipt', 'gstin' => $payment->membership?->gstin])

    <div class="mt-6 grid grid-cols-2 gap-6 text-sm">
        <div>
            <p class="text-xs font-semibold text-gray-500 uppercase">Received from</p>
            <p class="mt-1 font-semibold">{{ $payment->member->name }}</p>
            <p class="text-gray-600">Member ID: {{ $payment->member->member_code }}</p>
            <p class="text-gray-600">{{ $payment->member->phone }}</p>
        </div>
        <div class="text-right">
            <p><span class="text-gray-500">Receipt no:</span> <strong>{{ $payment->receipt_number }}</strong></p>
            <p><span class="text-gray-500">Date:</span> {{ $payment->paid_at->format('d M Y, h:i A') }}</p>
            <p><span class="text-gray-500">Method:</span> {{ $payment->method->label() }}</p>
            @if ($payment->reference)<p><span class="text-gray-500">Reference:</span> {{ $payment->reference }}</p>@endif
        </div>
    </div>

    <table class="mt-8 w-full text-sm">
        <thead><tr class="border-b border-gray-200 text-left text-xs text-gray-500 uppercase"><th class="py-2">Description</th><th class="py-2 text-right">Amount</th></tr></thead>
        <tbody>
            <tr class="border-b border-gray-100">
                <td class="py-3">
                    @if ($payment->membership)
                        {{ $payment->membership->plan_name }} membership{{ (float) $payment->membership->joining_fee > 0 ? ' + joining fee' : '' }}
                        <span class="block text-xs text-gray-500">{{ $payment->membership->start_date->format('d M Y') }} – {{ $payment->membership->end_date->format('d M Y') }}</span>
                    @else
                        Membership payment
                    @endif
                </td>
                <td class="py-3 text-right font-semibold">{{ money($payment->amount) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="mt-6 ml-auto w-full max-w-xs space-y-1 text-sm">
        <div class="flex justify-between text-lg font-bold"><span>Amount paid</span><span>{{ money($payment->amount) }}</span></div>
        @if ($gst)
            <div class="flex justify-between text-gray-600"><span>Includes CGST + SGST ({{ rtrim(rtrim(number_format($gst['rate'], 2), '0'), '.') }}%)</span><span>{{ money($gst['cgst'] + $gst['sgst']) }}</span></div>
        @endif
        @if ($payment->membership)
            <div class="flex justify-between text-gray-600"><span>Membership total</span><span>{{ money($payment->membership->total_amount) }}</span></div>
            <div class="flex justify-between text-gray-600"><span>Total paid to date</span><span>{{ money($payment->membership->amount_paid) }}</span></div>
            <div class="flex justify-between font-medium {{ $payment->membership->balance() > 0 ? 'text-amber-700' : 'text-emerald-600' }}"><span>Balance due</span><span>{{ money($payment->membership->balance()) }}</span></div>
        @endif
    </div>

    @if ($payment->notes)<p class="mt-6 text-sm text-gray-600">Note: {{ $payment->notes }}</p>@endif

    <div class="mt-12 flex items-end justify-between text-xs text-gray-500">
        <p>Received by: {{ $payment->receiver?->name ?? '—' }}</p>
        <p>This is a computer-generated receipt.</p>
    </div>
</x-layouts.print>
