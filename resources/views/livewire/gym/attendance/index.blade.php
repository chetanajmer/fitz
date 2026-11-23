<div>
    <x-ui.page-header title="Attendance" :subtitle="now()->format('l, d F Y').' · '.$todays->count().' check-ins today'">
        @can('attendance.reports')
            <x-slot:actions><a href="{{ route('gym.attendance.reports') }}" class="btn-secondary"><x-ui.icon name="chart" class="size-4" /> Reports</a></x-slot:actions>
        @endcan
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-5">
        <div class="space-y-6 lg:col-span-2">
            <div class="card card-body">
                <h2 class="card-title">Manual check-in</h2>
                <div class="relative mt-3">
                    <x-ui.icon name="search" class="pointer-events-none absolute top-2.5 left-3 size-4 text-gray-400" />
                    <input wire:model.live.debounce.250ms="search" type="search" placeholder="Name, phone or member ID…" class="input pl-9" autofocus>
                </div>
                @error('member') <p class="mt-2 rounded-lg bg-red-50 p-2 text-sm text-red-700">{{ $message }}</p> @enderror
                @if ($results->isNotEmpty())
                    <ul class="mt-3 divide-y divide-gray-100 rounded-lg border border-gray-200">
                        @foreach ($results as $r)
                            @php($state = $r->membershipState())
                            <li wire:key="r-{{ $r->id }}" class="flex items-center justify-between gap-3 px-3 py-2">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">{{ $r->name }} <span class="text-xs text-gray-500">{{ $r->member_code }}</span></p>
                                    <p class="text-xs {{ $state === 'active' ? 'text-emerald-600' : 'text-red-600' }}">{{ $state === 'active' ? 'Valid till '.$r->latestMembership->end_date->format('d M Y') : 'No active membership' }}</p>
                                </div>
                                @if (in_array($r->id, $checkedInIds))
                                    <x-ui.badge color="green">Checked in</x-ui.badge>
                                @else
                                    <button wire:click="checkIn({{ $r->id }})" class="btn-success btn-sm" @disabled($state !== 'active')>Check in</button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @elseif (strlen(trim($search)) >= 2)
                    <p class="mt-3 text-sm text-gray-500">No matching active members.</p>
                @endif
            </div>

            <form wire:submit="submitScan" class="card card-body">
                <h2 class="card-title flex items-center gap-2"><x-ui.icon name="qr" class="size-5" /> QR check-in / out</h2>
                <p class="mt-1 text-xs text-gray-500">Use a USB/Bluetooth QR scanner here, or scan the member's QR with your phone camera while logged in — it opens the check-in link directly.</p>
                <input wire:model="scan" type="text" placeholder="Scan member QR…" class="input mt-3 font-mono" autocomplete="off">
                @error('scan') <p class="input-error">{{ $message }}</p> @enderror
                <button type="submit" class="btn-primary mt-3 w-full">Submit scan</button>
            </form>

            @php($registerUrl = current_gym()->registerUrl())
            <div class="card card-body">
                <h2 class="card-title flex items-center gap-2"><x-ui.icon name="qr" class="size-5" /> Member registration QR</h2>
                <p class="mt-1 text-xs text-gray-500">New members scan this at the desk to open the registration form. Assign a plan afterwards from Members.</p>
                <img src="{{ route('gym.attendance.register-qr') }}" alt="Member registration QR code" class="mx-auto mt-3 size-48">
                <div class="mt-3 flex gap-2" x-data="{ copied: false }">
                    <input readonly value="{{ $registerUrl }}" class="input font-mono text-xs">
                    <button type="button" class="btn-secondary btn-sm" @click="navigator.clipboard.writeText('{{ $registerUrl }}'); copied = true; setTimeout(() => copied = false, 1500)" x-text="copied ? 'Copied' : 'Copy'"></button>
                </div>
                <div class="mt-3 flex justify-center gap-2">
                    <a href="{{ route('gym.attendance.register-qr', ['download' => 1]) }}" class="btn-primary btn-sm">Download QR</a>
                    <a href="{{ $registerUrl }}" target="_blank" class="btn-secondary btn-sm">Open form</a>
                </div>
            </div>
        </div>

        <div class="card lg:col-span-3">
            <div class="card-header"><h2 class="card-title">Today</h2><span class="text-xs text-gray-500">{{ $todays->whereNull('check_out_at')->count() }} inside now</span></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Member</th><th>In</th><th>Out</th><th>Method</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($todays as $a)
                            <tr wire:key="t-{{ $a->id }}">
                                <td><a href="{{ route('gym.members.show', $a->member_id) }}" class="font-medium hover:text-indigo-600">{{ $a->member->name }}</a></td>
                                <td>{{ $a->check_in_at->format('h:i A') }}</td>
                                <td>{{ $a->check_out_at?->format('h:i A') ?? '—' }}</td>
                                <td><x-ui.badge :color="['qr' => 'indigo', 'self' => 'purple'][$a->method] ?? 'gray'">{{ strtoupper($a->method) }}</x-ui.badge></td>
                                <td class="text-right">
                                    @if (! $a->check_out_at)
                                        <button wire:click="checkOut({{ $a->id }})" class="btn-secondary btn-sm">Check out</button>
                                    @else
                                        <span class="text-xs text-gray-500">{{ $a->durationLabel() }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-ui.empty message="No check-ins yet today." icon="calendar" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
