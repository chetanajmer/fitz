@php
    $firstName = \Illuminate\Support\Str::of($member->name)->before(' ');
    $stateLabel = ['active' => 'Membership active', 'expired' => 'Membership expired', 'upcoming' => 'Starts soon', 'none' => 'No membership'][$state];
    $stateColor = ['active' => 'green', 'expired' => 'red', 'upcoming' => 'blue'][$state] ?? 'gray';
    $daysLeft = $current?->daysRemaining();
    $progress = $current && $current->duration_days ? (int) min(100, max(0, round(100 - $daysLeft / $current->duration_days * 100))) : 0;
@endphp
<div class="space-y-6">
    {{-- Welcome + today's check-in --}}
    <section class="card relative overflow-hidden">
        <div aria-hidden="true" class="pointer-events-none absolute -top-24 -right-24 size-72 rounded-full bg-brand-600/20 blur-3xl"></div>
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-gradient-to-br from-brand-600/10 via-transparent to-transparent"></div>
        <div class="relative grid gap-6 p-5 sm:p-7 lg:grid-cols-[1.3fr_1fr] lg:items-center">
            <div class="flex items-center gap-4 sm:gap-5">
                <img src="{{ $member->photoUrl() }}" alt="" class="size-16 shrink-0 rounded-2xl object-cover ring-2 ring-brand-600/60 ring-offset-2 ring-offset-white sm:size-20 dark:ring-offset-ink-800">
                <div class="min-w-0">
                    <p class="text-xs font-semibold tracking-[0.18em] text-brand-600 uppercase">{{ $member->gym->name }}</p>
                    <h1 class="mt-1 truncate text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">Hi, {{ $firstName }} 👋</h1>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-gray-500">
                        <span class="rounded-full bg-gray-100 px-2.5 py-1 font-mono font-semibold text-gray-700">{{ $member->member_code }}</span>
                        <span>Joined {{ $member->joining_date->format('d M Y') }}</span>
                        @if ($member->trainer)<span class="inline-flex items-center gap-1"><x-ui.icon name="whistle" class="size-3.5 text-brand-600" /> {{ $member->trainer->name }}</span>@endif
                        <x-ui.badge :color="$stateColor">{{ $stateLabel }}</x-ui.badge>
                    </div>
                </div>
            </div>

            {{-- Today --}}
            <div class="rounded-2xl border border-gray-200 bg-gray-50/50 p-4 dark:border-ink-700">
                <div class="mb-3 flex items-center justify-between text-sm">
                    <p class="font-semibold text-gray-900">Today</p>
                    <p class="text-gray-500">{{ now()->format('D, d M') }}</p>
                </div>
                @error('member') <p class="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</p> @enderror
                @if ($todayVisit && $todayVisit->check_out_at)
                    <div class="flex items-center justify-center gap-2 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                        <x-ui.icon name="check" class="size-5" /> Visit done today · {{ $todayVisit->check_in_at->format('h:i A') }} – {{ $todayVisit->check_out_at->format('h:i A') }}
                    </div>
                @elseif ($todayVisit)
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <p class="flex flex-1 items-center gap-2 text-sm font-semibold text-emerald-700">
                            <span class="relative flex size-2.5"><span class="absolute inset-0 animate-ping rounded-full bg-emerald-500/70"></span><span class="relative size-2.5 rounded-full bg-emerald-500"></span></span>
                            Checked in at {{ $todayVisit->check_in_at->format('h:i A') }}
                        </p>
                        <button wire:click="checkOut" wire:loading.attr="disabled" class="btn-secondary rounded-full px-6 py-2.5">
                            <x-ui.icon name="logout" class="size-5" /> Check out
                        </button>
                    </div>
                @elseif ($selfCheckIn)
                    <button wire:click="checkIn" wire:loading.attr="disabled" class="btn-primary w-full rounded-full py-3.5 text-base font-bold tracking-wider uppercase">
                        <x-ui.icon name="qr" class="size-5" />
                        <span wire:loading.remove wire:target="checkIn">Check in now</span>
                        <span wire:loading wire:target="checkIn">Checking in…</span>
                    </button>
                @else
                    <p class="rounded-xl bg-white px-3 py-3 text-center text-sm text-gray-600 dark:bg-ink-800">Show your QR code at the front desk to check in.</p>
                @endif
            </div>
        </div>
    </section>

    {{-- At a glance --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-ui.stat class="flex-col gap-3 sm:flex-row sm:gap-4" label="Current streak" :value="$streak['current_streak'].' '.\Illuminate\Support\Str::plural('day', $streak['current_streak'])" icon="fire" :hint="'Best '.$streak['longest_streak']" />
        <x-ui.stat class="flex-col gap-3 sm:flex-row sm:gap-4" label="Visits this month" :value="$visitsThisMonth" icon="calendar" color="blue" :hint="now()->format('F')" />
        <x-ui.stat class="flex-col gap-3 sm:flex-row sm:gap-4" label="Days remaining" :value="$current ? $daysLeft : '—'" icon="clock" :color="$current ? ($daysLeft <= 7 ? 'amber' : 'green') : 'gray'" :hint="$current ? 'Ends '.$current->end_date->format('d M') : 'No active plan'" />
        <x-ui.stat class="flex-col gap-3 sm:flex-row sm:gap-4" label="Pending fees" :value="$pending > 0 ? money($pending, false) : 'None'" icon="receipt" :color="$pending > 0 ? 'amber' : 'green'" :hint="$pending > 0 ? 'Pay at the front desk' : 'All paid'" />
    </div>

    {{-- Streak --}}
    <div class="card card-body">
        <div class="flex items-center gap-4">
            <div class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-brand-600 text-white shadow-[0_10px_30px_-10px_color-mix(in_srgb,var(--gym-orange)_80%,transparent)]"><x-ui.icon name="fire" class="size-7" /></div>
            <div class="flex-1">
                <p class="text-2xl font-bold text-gray-900">{{ $streak['current_streak'] }} day streak</p>
                <p class="text-sm text-gray-500">{{ $streak['visits_this_month'] }} visits this month · best {{ $streak['longest_streak'] }}</p>
            </div>
        </div>
        @if ($streak['at_risk'])<p class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-sm font-medium text-amber-800">Visit today to keep your streak alive!</p>@endif
        <x-streak.week :week="$streak['week']" class="mt-6" />
        @if ($streak['next_milestone'])
            @php($milestonePct = (int) round(($streak['next_milestone']['target'] - $streak['next_milestone']['remaining']) / max(1, $streak['next_milestone']['target']) * 100))
            <div class="mt-6">
                <div class="flex items-center justify-between text-sm">
                    <p class="text-gray-500">{{ $streak['next_milestone']['remaining'] }} more visit days to your {{ $streak['next_milestone']['target'] }}-day milestone.</p>
                    <x-ui.icon name="trophy" class="size-5 shrink-0 text-brand-600" />
                </div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full bg-gradient-to-r from-brand-600 to-brand-400" style="width: {{ $milestonePct }}%"></div></div>
            </div>
        @endif
    </div>

    @include('livewire.portal.partials.programs', ['programs' => $programs, 'types' => ['workout', 'diet'], 'detailed' => false])

    {{-- Membership --}}
    <div class="card overflow-hidden">
        <div class="card-header"><h2 class="card-title">Current membership</h2><x-ui.badge :color="$stateColor">{{ $stateLabel }}</x-ui.badge></div>
        <div class="card-body">
            @if ($current)
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="text-2xl font-bold text-gray-900">{{ $current->plan_name }}</p>
                        <p class="text-sm text-gray-500">{{ $current->start_date->format('d M Y') }} – {{ $current->end_date->format('d M Y') }}</p>
                    </div>
                    <p class="text-sm text-gray-600"><strong class="text-3xl font-bold text-brand-600">{{ $daysLeft }}</strong> days remaining</p>
                </div>
                <div class="mt-5 h-2.5 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full bg-gradient-to-r from-brand-600 to-brand-400" style="width: {{ $progress }}%"></div></div>
                <p class="mt-2 text-xs text-gray-500">{{ $progress }}% of your plan used</p>
            @elseif ($latest && $state === 'upcoming')
                <p class="text-sm text-gray-600">Your <strong>{{ $latest->plan_name }}</strong> starts on {{ $latest->start_date->format('d M Y') }}.</p>
            @else
                <div class="flex flex-col items-start gap-3 sm:flex-row sm:items-center">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600"><x-ui.icon name="warning" class="size-5" /></span>
                    <p class="text-sm text-gray-600">You don't have an active membership{{ $latest ? ' — your last one ended on '.$latest->end_date->format('d M Y') : '' }}. Please visit the front desk to renew.</p>
                </div>
            @endif
        </div>
    </div>

    <div class="grid items-start gap-6 md:grid-cols-2">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Payments</h2><x-ui.icon name="cash" class="size-5 text-gray-400" /></div>
            <ul class="divide-y divide-gray-100">
                @forelse ($payments as $p)
                    <li wire:key="p-{{ $p->id }}" class="flex items-center gap-3 px-5 py-3 text-sm">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600"><x-ui.icon name="check" class="size-4" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-gray-900">{{ money($p->amount) }} <span class="font-normal text-gray-500">· {{ $p->method->label() }}</span></p>
                            <p class="truncate text-xs text-gray-500">{{ $p->paid_at->format('d M Y') }} · {{ $p->receipt_number }}</p>
                        </div>
                        <a href="{{ route('portal.receipt', $p) }}" target="_blank" class="inline-flex items-center gap-1 rounded-full border border-gray-200 px-3 py-1 text-xs font-medium text-gray-700 transition hover:border-brand-600 hover:text-brand-600 dark:border-ink-700">Receipt <x-ui.icon name="external" class="size-3" /></a>
                    </li>
                @empty
                    <li><x-ui.empty message="No payments yet." icon="cash" /></li>
                @endforelse
            </ul>
        </div>
        <div class="card">
            <div class="card-header"><h2 class="card-title">Recent visits</h2><x-ui.icon name="calendar" class="size-5 text-gray-400" /></div>
            <ul class="divide-y divide-gray-100">
                @forelse ($attendance as $a)
                    <li wire:key="a-{{ $a->id }}" class="flex items-center gap-3 px-5 py-2.5 text-sm">
                        <span class="flex size-9 shrink-0 flex-col items-center justify-center rounded-lg bg-indigo-50 leading-none text-indigo-600">
                            <span class="text-[10px] font-semibold uppercase">{{ $a->date->format('M') }}</span>
                            <span class="text-sm font-bold">{{ $a->date->format('d') }}</span>
                        </span>
                        <span class="flex-1 text-gray-900">{{ $a->date->format('l') }}</span>
                        <span class="text-gray-500">{{ $a->check_in_at->format('h:i A') }} – {{ $a->check_out_at?->format('h:i A') ?? '…' }}</span>
                    </li>
                @empty
                    <li><x-ui.empty message="No visits recorded yet." icon="calendar" /></li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="card overflow-x-auto">
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
</div>
