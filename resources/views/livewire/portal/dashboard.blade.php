<div class="space-y-6">
    <div class="card card-body flex flex-col gap-4 sm:flex-row sm:items-center">
        <img src="{{ $member->photoUrl() }}" alt="" class="size-16 rounded-full object-cover">
        <div class="flex-1">
            <h1 class="text-xl font-semibold">Hi, {{ $member->name }}</h1>
            <p class="text-sm text-gray-500">Member {{ $member->member_code }} · joined {{ $member->joining_date->format('d M Y') }} @if($member->trainer)· Trainer: {{ $member->trainer->name }}@endif</p>
        </div>
        <x-ui.badge :color="['active' => 'green', 'expired' => 'red', 'upcoming' => 'blue'][$state] ?? 'gray'" class="self-start text-sm">
            {{ ['active' => 'Membership active', 'expired' => 'Membership expired', 'upcoming' => 'Starts soon', 'none' => 'No membership'][$state] }}
        </x-ui.badge>
    </div>

    <div class="card card-body">
        <div class="flex items-center gap-4">
            <div class="flex size-14 items-center justify-center rounded-2xl bg-gray-900 text-orange-400"><x-ui.icon name="fire" class="size-7" /></div>
            <div class="flex-1">
                <p class="text-2xl font-bold">{{ $streak['current_streak'] }} day streak</p>
                <p class="text-sm text-gray-500">{{ $streak['visits_this_month'] }} visits this month · best {{ $streak['longest_streak'] }}</p>
            </div>
        </div>
        @if ($streak['at_risk'])<p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-sm font-medium text-amber-800">Visit today to keep your streak alive!</p>@endif
        <x-streak.week :week="$streak['week']" class="mt-5" />
        <div class="mt-5">
            @error('member') <p class="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</p> @enderror
            @if ($todayVisit && $todayVisit->check_out_at)
                <div class="flex items-center justify-center gap-2 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                    <x-ui.icon name="check" class="size-5" /> Visit done today · {{ $todayVisit->check_in_at->format('h:i A') }} – {{ $todayVisit->check_out_at->format('h:i A') }}
                </div>
            @elseif ($todayVisit)
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <p class="flex flex-1 items-center gap-2 text-sm font-semibold text-emerald-700"><x-ui.icon name="check" class="size-5" /> Checked in at {{ $todayVisit->check_in_at->format('h:i A') }}</p>
                    <button wire:click="checkOut" wire:loading.attr="disabled" class="btn-secondary px-6 py-3">
                        <x-ui.icon name="logout" class="size-5" /> Check out
                    </button>
                </div>
            @elseif ($selfCheckIn)
                <button wire:click="checkIn" wire:loading.attr="disabled" class="btn w-full bg-rose-600 py-3.5 text-base font-bold tracking-wider text-white uppercase hover:bg-rose-700 focus:ring-rose-500">
                    <x-ui.icon name="qr" class="size-5" />
                    <span wire:loading.remove wire:target="checkIn">Check in now</span>
                    <span wire:loading wire:target="checkIn">Checking in…</span>
                </button>
            @else
                <p class="rounded-lg bg-gray-50 px-3 py-2 text-center text-sm text-gray-600">Show your QR code at the front desk to check in.</p>
            @endif
        </div>
        @if ($streak['next_milestone'])<p class="mt-4 text-sm text-gray-500">{{ $streak['next_milestone']['remaining'] }} more visit days to your {{ $streak['next_milestone']['target'] }}-day milestone.</p>@endif
    </div>

    @include('livewire.portal.partials.programs', ['programs' => $programs, 'types' => ['workout', 'diet'], 'detailed' => false])

    <div class="grid gap-6 md:grid-cols-3">
        <div class="card card-body md:col-span-2">
            <h2 class="card-title">Current membership</h2>
            @if ($current)
                <p class="mt-3 text-2xl font-semibold">{{ $current->plan_name }}</p>
                <p class="text-sm text-gray-500">{{ $current->start_date->format('d M Y') }} – {{ $current->end_date->format('d M Y') }}</p>
                @php($pct = $current->duration_days ? min(100, max(0, round(100 - $current->daysRemaining() / $current->duration_days * 100))) : 0)
                <div class="mt-4 h-2 rounded-full bg-gray-100"><div class="h-2 rounded-full bg-emerald-500" style="width: {{ $pct }}%"></div></div>
                <p class="mt-2 text-sm text-gray-600"><strong>{{ $current->daysRemaining() }}</strong> days remaining</p>
            @elseif ($latest && $state === 'upcoming')
                <p class="mt-3 text-sm text-gray-600">Your <strong>{{ $latest->plan_name }}</strong> starts on {{ $latest->start_date->format('d M Y') }}.</p>
            @else
                <p class="mt-3 text-sm text-gray-600">You don't have an active membership{{ $latest ? ' — your last one ended on '.$latest->end_date->format('d M Y') : '' }}. Please visit the front desk to renew.</p>
            @endif
            <div class="mt-5 grid grid-cols-2 gap-4 border-t border-gray-100 pt-4 text-sm">
                <div><p class="text-gray-500">Visits this month</p><p class="text-lg font-semibold">{{ $visitsThisMonth }}</p></div>
                <div><p class="text-gray-500">Pending fees</p><p class="text-lg font-semibold {{ $pending > 0 ? 'text-amber-700' : 'text-emerald-600' }}">{{ $pending > 0 ? money($pending) : 'None' }}</p></div>
            </div>
        </div>
        <div class="card card-body text-center">
            <h2 class="card-title">My check-in QR</h2>
            <img src="{{ route('portal.qr') }}" alt="My QR code" class="mx-auto mt-3 size-44">
            <p class="mt-2 text-xs text-gray-500">Show this at the front desk to check in or out.</p>
        </div>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Payments</h2></div>
            <ul class="divide-y divide-gray-100">
                @forelse ($payments as $p)
                    <li wire:key="p-{{ $p->id }}" class="flex items-center justify-between px-5 py-3 text-sm">
                        <div><p class="font-medium">{{ money($p->amount) }} <span class="font-normal text-gray-500">· {{ $p->method->label() }}</span></p><p class="text-xs text-gray-500">{{ $p->paid_at->format('d M Y') }} · {{ $p->receipt_number }}</p></div>
                        <a href="{{ route('portal.receipt', $p) }}" target="_blank" class="text-xs font-medium text-indigo-600 hover:underline">Receipt</a>
                    </li>
                @empty
                    <li><x-ui.empty message="No payments yet." icon="cash" /></li>
                @endforelse
            </ul>
        </div>
        <div class="card">
            <div class="card-header"><h2 class="card-title">Recent visits</h2></div>
            <ul class="divide-y divide-gray-100">
                @forelse ($attendance as $a)
                    <li wire:key="a-{{ $a->id }}" class="flex justify-between px-5 py-2.5 text-sm">
                        <span>{{ $a->date->format('D, d M') }}</span>
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
