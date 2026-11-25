@php
    $stateLabel = ['active' => 'Active', 'expired' => 'Expired', 'upcoming' => 'Starts soon', 'none' => 'No membership'][$state];
    $stateColor = ['active' => 'green', 'expired' => 'red', 'upcoming' => 'blue'][$state] ?? 'gray';
    $daysLeft = $current?->daysRemaining();
    $left = $current && $current->duration_days ? (int) min(100, max(0, round($daysLeft / $current->duration_days * 100))) : 0;
    $shown = $current ?? $latest;
    $paidInFull = $shown && (float) $shown->amount_paid >= (float) $shown->total_amount;
@endphp
<div class="space-y-6">
    <x-ui.page-header title="My membership" subtitle="Your plan, renewal and payments." />

    <div class="grid gap-6 lg:grid-cols-5">
        {{-- Membership card --}}
        <section class="pm-rise relative isolate overflow-hidden rounded-3xl bg-gradient-to-br from-ink-800 via-ink-900 to-black p-6 text-white ring-1 ring-white/10 sm:p-8 lg:col-span-3">
            <div aria-hidden="true" class="absolute -top-24 -right-16 -z-10 size-72 rounded-full bg-brand-600/35 blur-3xl"></div>
            <div aria-hidden="true" class="absolute -bottom-28 -left-10 -z-10 size-64 rounded-full bg-brand-600/10 blur-3xl"></div>
            <div aria-hidden="true" class="absolute inset-0 -z-10 bg-[repeating-linear-gradient(115deg,transparent_0_22px,rgb(255_255_255/0.025)_22px_23px)]"></div>

            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold tracking-[0.2em] text-brand-400 uppercase">{{ $member->gym->name }}</p>
                    <p class="mt-1 font-mono text-sm text-zinc-400">{{ $member->member_code }}</p>
                </div>
                <x-ui.badge :color="$stateColor" class="ring-0">{{ $stateLabel }}</x-ui.badge>
            </div>

            <div class="mt-8 flex flex-wrap items-end justify-between gap-6">
                <div class="min-w-0">
                    <p class="text-sm text-zinc-400">Current plan</p>
                    <p class="mt-1 truncate text-3xl font-bold tracking-tight sm:text-4xl">{{ $current?->plan_name ?? ($state === 'upcoming' ? $latest->plan_name : 'No active plan') }}</p>
                    <p class="mt-2 text-sm text-zinc-300">{{ $member->name }}</p>
                </div>
                @if ($current)
                    <div class="pm-ring grid size-28 place-items-center" style="--p: {{ $left }}" role="img" aria-label="{{ $daysLeft }} days remaining">
                        <div class="grid size-[5.75rem] place-items-center rounded-full bg-ink-900 text-center">
                            <div>
                                <p class="text-3xl leading-none font-bold" data-count="{{ $daysLeft }}">{{ $daysLeft }}</p>
                                <p class="mt-1 text-[10px] tracking-wider text-zinc-400 uppercase">days left</p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <dl class="mt-8 grid grid-cols-2 gap-4 border-t border-white/10 pt-5 text-sm sm:grid-cols-4">
                <div><dt class="text-zinc-400">Start</dt><dd class="mt-0.5 font-semibold">{{ $shown?->start_date->format('d M Y') ?? '—' }}</dd></div>
                <div><dt class="text-zinc-400">Expiry</dt><dd class="mt-0.5 font-semibold">{{ $shown?->end_date->format('d M Y') ?? '—' }}</dd></div>
                <div><dt class="text-zinc-400">Payment</dt><dd @class(['mt-0.5 font-semibold', 'text-emerald-400' => $paidInFull, 'text-amber-400' => $shown && ! $paidInFull])>{{ $shown ? ($paidInFull ? 'Paid in full' : money((float) $shown->total_amount - (float) $shown->amount_paid).' due') : '—' }}</dd></div>
                <div><dt class="text-zinc-400">Member since</dt><dd class="mt-0.5 font-semibold">{{ $member->joining_date->format('M Y') }}</dd></div>
            </dl>
        </section>

        {{-- Renewal + totals --}}
        <div class="space-y-6 lg:col-span-2">
            <div @class(['pm-rise card card-body', 'ring-1 ring-amber-300 dark:ring-amber-500/40' => ($current && $daysLeft <= 7) || in_array($state, ['expired', 'none'], true)]) style="--d: 80ms">
                <div class="flex items-start gap-3">
                    <span @class(['flex size-11 shrink-0 items-center justify-center rounded-xl', 'bg-amber-50 text-amber-600' => ! $current || $daysLeft <= 7, 'bg-emerald-50 text-emerald-600' => $current && $daysLeft > 7])><x-ui.icon name="bell" class="size-5" /></span>
                    <div>
                        <h2 class="font-semibold text-gray-900">Renewal</h2>
                        <p class="mt-1 text-sm text-gray-600">
                            @if ($current && $daysLeft <= 7)
                                Your plan ends in <strong>{{ $daysLeft }} {{ \Illuminate\Support\Str::plural('day', $daysLeft) }}</strong>. Renew at the front desk so your next plan starts on {{ $current->end_date->copy()->addDay()->format('d M') }}.
                            @elseif ($current)
                                You're all set until <strong>{{ $current->end_date->format('d M Y') }}</strong>. We'll remind you a few days before it ends.
                            @elseif ($state === 'upcoming')
                                Your <strong>{{ $latest->plan_name }}</strong> starts on {{ $latest->start_date->format('d M Y') }}.
                            @else
                                You don't have an active membership{{ $latest ? ' — your last one ended on '.$latest->end_date->format('d M Y') : '' }}. Visit the front desk to renew.
                            @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="pm-rise card card-body" style="--d: 140ms">
                    <p class="text-xs font-medium tracking-wide text-gray-500 uppercase">Total paid</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ money($totalPaid, false) }}</p>
                    <p class="text-xs text-gray-500">{{ $payments->count() }} {{ \Illuminate\Support\Str::plural('payment', $payments->count()) }}</p>
                </div>
                <div class="pm-rise card card-body" style="--d: 200ms">
                    <p class="text-xs font-medium tracking-wide text-gray-500 uppercase">Pending</p>
                    <p @class(['mt-1 text-2xl font-bold', 'text-amber-600' => $pending > 0, 'text-emerald-600' => $pending <= 0])>{{ $pending > 0 ? money($pending, false) : 'None' }}</p>
                    <p class="text-xs text-gray-500">{{ $pending > 0 ? 'Pay at the front desk' : 'All paid' }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- History --}}
    <div class="pm-reveal card overflow-x-auto">
        <div class="card-header"><h2 class="card-title">Membership history</h2></div>
        <table class="table">
            <thead><tr><th>Plan</th><th>Period</th><th>Total</th><th>Paid</th><th>Status</th></tr></thead>
            <tbody>
                @forelse ($memberships as $ms)
                    <tr wire:key="ms-{{ $ms->id }}">
                        <td class="font-medium">{{ $ms->plan_name }}</td>
                        <td>{{ $ms->start_date->format('d M Y') }} – {{ $ms->end_date->format('d M Y') }}</td>
                        <td>{{ money($ms->total_amount) }}</td>
                        <td>{{ money($ms->amount_paid) }}</td>
                        <td><x-ui.badge :color="$ms->computedStatus()->color()">{{ $ms->computedStatus()->label() }}</x-ui.badge></td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-ui.empty message="No memberships yet." icon="card" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Payments --}}
    <div id="payments" class="pm-reveal card scroll-mt-24">
        <div class="card-header"><h2 class="card-title">Payments &amp; receipts</h2><x-ui.icon name="receipt" class="size-5 text-gray-400" /></div>
        <ul class="divide-y divide-gray-100">
            @forelse ($payments as $p)
                <li wire:key="p-{{ $p->id }}" class="flex flex-wrap items-center gap-3 px-5 py-3.5 text-sm">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"><x-ui.icon name="check" class="size-5" /></span>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-gray-900">{{ money($p->amount) }} <span class="font-normal text-gray-500">· {{ $p->method->label() }}</span></p>
                        <p class="truncate text-xs text-gray-500">{{ $p->paid_at->format('d M Y, h:i A') }} · {{ $p->receipt_number }}</p>
                    </div>
                    <a href="{{ route('portal.receipt', $p) }}" target="_blank" class="inline-flex items-center gap-1 rounded-full border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:border-brand-600 hover:text-brand-600 dark:border-ink-700">Receipt <x-ui.icon name="external" class="size-3" /></a>
                </li>
            @empty
                <li><x-ui.empty message="No payments yet." icon="cash" /></li>
            @endforelse
        </ul>
    </div>
</div>
