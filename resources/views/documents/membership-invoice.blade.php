@php($invoiceNo = 'INV-'.$gym->id.'-'.str_pad((string) $membership->id, 5, '0', STR_PAD_LEFT))
<x-layouts.print :title="'Invoice '.$invoiceNo">
    @include('documents.partials.gym-header', ['docTitle' => 'Invoice'])

    <div class="mt-6 grid grid-cols-2 gap-6 text-sm">
        <div>
            <p class="text-xs font-semibold text-gray-500 uppercase">Bill to</p>
            <p class="mt-1 font-semibold">{{ $membership->member->name }}</p>
            <p class="text-gray-600">Member ID: {{ $membership->member->member_code }}</p>
            <p class="text-gray-600">{{ $membership->member->phone }}</p>
            @if ($membership->member->address)<p class="text-gray-600">{{ $membership->member->address }}</p>@endif
        </div>
        <div class="text-right">
            <p><span class="text-gray-500">Invoice no:</span> <strong>{{ $invoiceNo }}</strong></p>
            <p><span class="text-gray-500">Date:</span> {{ $membership->created_at->format('d M Y') }}</p>
            <p class="mt-2"><span class="rounded px-2 py-0.5 text-xs font-semibold uppercase {{ $membership->isPaid() ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-800' }}">{{ $membership->paymentStatus() }}</span></p>
        </div>
    </div>

    <table class="mt-8 w-full text-sm">
        <thead><tr class="border-b border-gray-200 text-left text-xs text-gray-500 uppercase"><th class="py-2">Description</th><th class="py-2">Period</th><th class="py-2 text-right">Amount</th></tr></thead>
        <tbody>
            <tr class="border-b border-gray-100">
                <td class="py-3">{{ $membership->plan_name }} membership <span class="block text-xs text-gray-500">{{ $membership->duration_days }} days</span></td>
                <td class="py-3">{{ $membership->start_date->format('d M Y') }} – {{ $membership->end_date->format('d M Y') }}</td>
                <td class="py-3 text-right">{{ money($membership->price) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="mt-6 ml-auto w-full max-w-xs space-y-1 text-sm">
        <div class="flex justify-between"><span class="text-gray-600">Subtotal</span><span>{{ money($membership->price) }}</span></div>
        @if ((float) $membership->discount > 0)<div class="flex justify-between"><span class="text-gray-600">Discount</span><span>−{{ money($membership->discount) }}</span></div>@endif
        <div class="flex justify-between border-t border-gray-200 pt-1 text-base font-bold"><span>Total</span><span>{{ money($membership->total_amount) }}</span></div>
        <div class="flex justify-between text-gray-600"><span>Paid</span><span>{{ money($membership->amount_paid) }}</span></div>
        <div class="flex justify-between font-semibold {{ $membership->balance() > 0 ? 'text-amber-700' : 'text-emerald-600' }}"><span>Balance due</span><span>{{ money($membership->balance()) }}</span></div>
    </div>

    @if ($membership->payments->isNotEmpty())
        <h3 class="mt-10 text-xs font-semibold text-gray-500 uppercase">Payments</h3>
        <table class="mt-2 w-full text-sm">
            <tbody>
                @foreach ($membership->payments as $p)
                    <tr class="border-b border-gray-100"><td class="py-2">{{ $p->receipt_number }}</td><td class="py-2">{{ $p->paid_at->format('d M Y') }}</td><td class="py-2">{{ $p->method->label() }}</td><td class="py-2 text-right">{{ money($p->amount) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="mt-12 text-xs text-gray-500">This is a computer-generated invoice.</p>
</x-layouts.print>
