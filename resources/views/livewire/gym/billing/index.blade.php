<div>
    <x-ui.page-header title="Gymai billing" subtitle="Simple pricing: pay only for members with an active, valid membership. No setup fee." />

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-ui.stat label="Billable members this month" :value="$estimate['members']" icon="users" :hint="'Active + valid membership in '.$estimate['period_start']->format('F')" />
        <x-ui.stat label="Estimated this month" :value="money($estimate['amount'])" icon="receipt" color="blue" :hint="$estimate['members'].' × '.money($estimate['rate']).' per member'.($estimate['custom'] ? ' (your custom price)' : '')" />
        <x-ui.stat label="Outstanding balance" :value="money($outstanding)" icon="cash" :color="$outstanding > 0 ? 'red' : 'green'" :hint="$outstanding > 0 ? 'Please clear pending invoices' : 'All paid up'" />
    </div>

    <div class="card">
        <div class="card-header"><h2 class="card-title">Invoices</h2><span class="text-xs text-gray-500">Generated monthly for the previous month</span></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Invoice</th><th>Period</th><th>Members</th><th>Rate</th><th>Total</th><th>Paid</th><th>Due date</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse ($invoices as $inv)
                        <tr wire:key="inv-{{ $inv->id }}">
                            <td class="font-medium">{{ $inv->invoice_number }}</td>
                            <td>{{ $inv->period_start->format('M Y') }}</td>
                            <td>{{ $inv->active_members }}</td>
                            <td>{{ money($inv->rate_per_member) }}</td>
                            <td class="font-semibold">{{ money($inv->total) }}</td>
                            <td>{{ money($inv->amount_paid) }}</td>
                            <td class="{{ $inv->isOverdue() ? 'font-medium text-red-600' : '' }}">{{ $inv->due_on->format('d M Y') }}</td>
                            <td><x-ui.badge :color="$inv->status->color()">{{ $inv->status->label() }}</x-ui.badge></td>
                            <td class="text-right"><a href="{{ route('gym.billing.invoice', $inv) }}" target="_blank" class="text-xs font-medium text-indigo-600 hover:underline">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><x-ui.empty message="No invoices yet. Your first invoice is generated at the start of next month." icon="receipt" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($invoices->hasPages())<div class="border-t border-gray-100 px-4 py-3">{{ $invoices->links() }}</div>@endif
    </div>

    <p class="mt-4 text-xs text-gray-500">Enquiries, inactive members and members whose memberships had expired before the month are never billed. Invoices keep the price that applied at the time, even if pricing changes later.</p>
</div>
