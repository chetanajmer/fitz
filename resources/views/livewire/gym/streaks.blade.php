<div>
    <x-ui.page-header title="Streaks" subtitle="Attendance streaks shown in the member app. Calculated from check-ins.">
        <x-slot:actions>
            <button wire:click="recalculate" class="btn-secondary" wire:loading.attr="disabled" wire:target="recalculate">
                <span wire:loading.remove wire:target="recalculate">Recalculate all</span>
                <span wire:loading wire:target="recalculate">Recalculating…</span>
            </button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-ui.stat label="Members on a streak" :value="$stats['active']" icon="check" color="green" :hint="'of '.$stats['members'].' active members'" />
        <x-ui.stat label="Average streak" :value="$stats['average']" icon="chart" hint="visit days" />
        <x-ui.stat label="Best ever" :value="$stats['best']" icon="tag" color="amber" hint="visit days" />
        <x-ui.stat label="At risk today" :value="$atRisk->count()" icon="clock" :color="$atRisk->isNotEmpty() ? 'red' : 'gray'" hint="streak breaks if they skip today" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Leaderboard</h2>
                    <div class="inline-flex rounded-lg border border-gray-300 bg-white p-0.5">
                        <button wire:click="$set('board', 'current')" class="rounded-md px-3 py-1 text-xs font-medium {{ $board === 'current' ? 'bg-gray-900 text-white' : 'text-gray-600' }}">Current</button>
                        <button wire:click="$set('board', 'longest')" class="rounded-md px-3 py-1 text-xs font-medium {{ $board === 'longest' ? 'bg-gray-900 text-white' : 'text-gray-600' }}">All-time best</button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>#</th><th>Member</th><th>{{ $board === 'current' ? 'Current streak' : 'Best streak' }}</th><th>{{ $board === 'current' ? 'Since' : 'Period' }}</th><th>Last visit</th><th>Total visits</th></tr></thead>
                        <tbody>
                            @forelse ($leaderboard as $i => $s)
                                <tr wire:key="lb-{{ $s->id }}">
                                    <td class="font-semibold {{ $i < 3 ? 'text-rose-600' : 'text-gray-400' }}">{{ $i + 1 }}</td>
                                    <td>
                                        <a href="{{ route('gym.members.show', $s->member_id) }}" class="flex items-center gap-3 hover:text-indigo-600">
                                            <img src="{{ $s->member->photoUrl() }}" alt="" class="size-8 rounded-full object-cover">
                                            <span><span class="font-medium">{{ $s->member->name }}</span> <span class="text-xs text-gray-500">{{ $s->member->member_code }}</span></span>
                                        </a>
                                    </td>
                                    <td><span class="inline-flex items-center gap-1 text-lg font-bold"><x-ui.icon name="fire" class="size-5 text-orange-500" /> {{ $board === 'current' ? $s->current_streak : $s->longest_streak }}</span></td>
                                    <td class="text-gray-500">
                                        @if ($board === 'current')
                                            {{ $s->current_started_on?->format('d M') }}
                                        @else
                                            {{ $s->longest_started_on?->format('d M') }} – {{ $s->longest_ended_on?->format('d M Y') }}
                                        @endif
                                    </td>
                                    <td>{{ $s->last_visit_on?->format('d M') ?? '—' }}</td>
                                    <td>{{ $s->total_visit_days }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><x-ui.empty message="No streaks yet. They start with the first check-in." icon="chart" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">At risk today</h2><span class="text-xs text-gray-500">A reminder call or WhatsApp keeps them going</span></div>
                <ul class="divide-y divide-gray-100">
                    @forelse ($atRisk as $s)
                        <li wire:key="risk-{{ $s->id }}" class="flex items-center justify-between gap-3 px-5 py-3">
                            <a href="{{ route('gym.members.show', $s->member_id) }}" class="min-w-0">
                                <p class="truncate text-sm font-medium">{{ $s->member->name }}</p>
                                <p class="flex items-center gap-1 text-xs text-gray-500"><x-ui.icon name="fire" class="size-3.5 text-orange-500" /> {{ $s->current_streak }} day streak · last visit {{ $s->last_visit_on?->format('D, d M') }}</p>
                            </a>
                            <div class="flex gap-2">
                                <a href="tel:{{ $s->member->phone }}" class="btn-secondary btn-sm">Call</a>
                                <a href="https://wa.me/91{{ $s->member->phone_digits }}?text={{ urlencode('Hi '.$s->member->name.'! Your '.$s->current_streak.'-day streak at '.current_gym()->name.' ends today if you miss your workout. See you at the gym!') }}" target="_blank" rel="noopener" class="btn-success btn-sm">WhatsApp</a>
                            </div>
                        </li>
                    @empty
                        <li><x-ui.empty message="Nobody's streak is at risk today." icon="check" /></li>
                    @endforelse
                </ul>
            </div>
        </div>

        <form wire:submit="saveRules" class="card h-fit">
            <div class="card-header"><h2 class="card-title">Check-in & streak rules</h2></div>
            <div class="card-body space-y-4">
                <label class="flex items-start gap-2 text-sm">
                    <input wire:model="selfCheckIn" type="checkbox" class="mt-0.5 rounded border-gray-300 text-indigo-600" @disabled(! $canEditRules)>
                    <span>Members can check themselves in
                        <span class="block text-xs text-gray-500">"Check in now" button in the member app and portal. Off = members show their QR at the desk. Self check-ins are marked SELF in attendance.</span>
                    </span>
                </label>
                <x-ui.field label="Rest days allowed between visits" name="graceDays" hint="0 = must visit every open day. 1 = one rest day between visits keeps the streak, and so on.">
                    <select wire:model="graceDays" class="input" @disabled(! $canEditRules)>
                        @foreach (range(0, 7) as $n)<option value="{{ $n }}">{{ $n }} {{ $n === 1 ? 'day' : 'days' }}</option>@endforeach
                    </select>
                </x-ui.field>
                <label class="flex items-start gap-2 text-sm">
                    <input wire:model="skipClosedDays" type="checkbox" class="mt-0.5 rounded border-gray-300 text-indigo-600" @disabled(! $canEditRules)>
                    <span>Days the gym is closed never break a streak
                        <span class="block text-xs text-gray-500">Closed: {{ $closedDays ? implode(', ', array_map('ucfirst', $closedDays)) : 'none' }} (from opening hours)</span>
                    </span>
                </label>
                <x-ui.field label="Milestones" name="milestones" hint="Streak lengths to celebrate in the app, comma separated.">
                    <input wire:model="milestones" class="input" placeholder="7, 14, 30, 60, 100" @disabled(! $canEditRules)>
                </x-ui.field>
                <div class="rounded-lg bg-gray-50 p-3 text-xs text-gray-600">
                    The streak is the number of visit days in a row, with the rest days you allow. It counts one visit per day, and today never breaks a streak until the day is over.
                </div>
                @if ($canEditRules)
                    <button type="submit" class="btn-primary w-full">Save & recalculate</button>
                @endif
            </div>
        </form>
    </div>
</div>
