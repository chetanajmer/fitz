<div>
    <x-ui.page-header title="Payments" subtitle="Manual payment records — cash, UPI, bank transfer and card.">
        <x-slot:actions><a href="{{ route('gym.memberships.index', ['filter' => 'dues']) }}" class="btn-secondary">Pending fees</a></x-slot:actions>
    </x-ui.page-header>

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
        <x-ui.stat label="Total collected" :value="money($total, false)" color="green" icon="cash" />
        @foreach (\App\Enums\PaymentMethod::cases() as $pm)
            <x-ui.stat wire:key="pm-{{ $pm->value }}" :label="$pm->label()" :value="money($byMethod[$pm->value]->total ?? 0, false)" :hint="($byMethod[$pm->value]->count ?? 0).' payments'" />
        @endforeach
    </div>

    <div class="card">
        <div class="flex flex-col gap-3 border-b border-gray-100 p-4 md:flex-row md:items-end">
            <div class="relative flex-1">
                <x-ui.icon name="search" class="pointer-events-none absolute top-2.5 left-3 size-4 text-gray-400" />
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Receipt no. or member…" class="input pl-9">
            </div>
            <select wire:model.live="method" class="input md:w-40">
                <option value="">All methods</option>
                @foreach (\App\Enums\PaymentMethod::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
            </select>
            <input wire:model.live="from" type="date" class="input md:w-40" aria-label="From">
            <input wire:model.live="to" type="date" class="input md:w-40" aria-label="To">
        </div>
        @error('to') <p class="input-error px-4 pt-2">{{ $message }}</p> @enderror
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Receipt</th><th>Date</th><th>Member</th><th>Plan</th><th>Method</th><th>Received by</th><th class="text-right">Amount</th><th></th></tr></thead>
                <tbody>
                    @forelse ($payments as $p)
                        <tr wire:key="p-{{ $p->id }}">
                            <td class="font-medium">{{ $p->receipt_number }}</td>
                            <td>{{ $p->paid_at->format('d M Y, h:i A') }}</td>
                            <td><a href="{{ route('gym.members.show', $p->member_id) }}" class="hover:text-indigo-600">{{ $p->member->name }}</a></td>
                            <td>{{ $p->membership?->plan_name ?? '—' }}</td>
                            <td>{{ $p->method->label() }} @if($p->reference)<span class="block text-xs text-gray-500">{{ $p->reference }}</span>@endif</td>
                            <td>{{ $p->receiver?->name ?? '—' }}</td>
                            <td class="text-right font-semibold text-emerald-600">{{ money($p->amount) }}</td>
                            <td class="text-right"><a href="{{ route('gym.payments.receipt', $p) }}" target="_blank" class="text-xs font-medium text-indigo-600 hover:underline">Receipt</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-ui.empty message="No payments in this period." icon="cash" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($payments->hasPages())<div class="border-t border-gray-100 px-4 py-3">{{ $payments->links() }}</div>@endif
    </div>
</div>
