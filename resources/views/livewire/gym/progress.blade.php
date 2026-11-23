@php $fmt = fn ($kg) => $kg === null ? '—' : rtrim(rtrim(number_format((float) $kg, 2), '0'), '.').' kg'; @endphp
<div>
    <x-ui.page-header title="Weight tracking" :subtitle="'Members are asked for a weigh-in every '.$every.' days in their app. Record weights and targets from a member\'s Progress tab.'" />

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat label="Weighed in recently" :value="$stats['tracking']" icon="users" :hint="'In the last '.$every.' days'" />
        <x-ui.stat label="Due for weigh-in" :value="$stats['due']" icon="clock" :color="$stats['due'] ? 'red' : 'green'" hint="Active members" />
        <x-ui.stat label="Active targets" :value="$stats['active_goals']" icon="chart" color="blue" />
        <x-ui.stat label="Targets achieved" :value="$stats['achieved_30']" icon="check" color="green" hint="Last 30 days" />
    </div>

    <div class="grid gap-6 lg:grid-cols-4">
        <div class="lg:col-span-3">
            <div class="mb-4 flex gap-6 overflow-x-auto border-b border-gray-200">
                @foreach (['due' => 'Due for weigh-in', 'near' => 'Close to target', 'achieved' => 'Achieved'] as $key => $label)
                    <button wire:click="$set('view', '{{ $key }}')" class="tab {{ $view === $key ? 'tab-active' : '' }}">{{ $label }}</button>
                @endforeach
            </div>

            <div class="card overflow-x-auto">
                @if ($view === 'due')
                    <table class="table">
                        <thead><tr><th>Member</th><th>Phone</th><th>Last weigh-in</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($due as $m)
                                <tr wire:key="due-{{ $m->id }}">
                                    <td><a href="{{ route('gym.members.show', [$m, 'tab' => 'progress']) }}" class="font-medium text-gray-900 hover:text-indigo-600">{{ $m->name }}</a> <span class="text-xs text-gray-500">{{ $m->member_code }}</span></td>
                                    <td>{{ $m->phone }}</td>
                                    <td>
                                        @if ($m->last_weighed_on)
                                            {{ \Illuminate\Support\Carbon::parse($m->last_weighed_on)->format('d M Y') }} <span class="text-xs text-red-600">({{ \Illuminate\Support\Carbon::parse($m->last_weighed_on)->diffInDays(today()) }} days ago)</span>
                                        @else
                                            <x-ui.badge color="gray">Never</x-ui.badge>
                                        @endif
                                    </td>
                                    <td class="text-right"><a href="{{ route('gym.members.show', [$m, 'tab' => 'progress']) }}" class="btn-secondary btn-sm">Record weight</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="4"><x-ui.empty message="Everyone is up to date with their weigh-ins." icon="check" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    @if ($due->hasPages())<div class="border-t border-gray-100 px-4 py-3">{{ $due->links() }}</div>@endif
                @elseif ($view === 'near')
                    <table class="table">
                        <thead><tr><th>Member</th><th>Start / Target</th><th>Current</th><th>Progress</th><th>To go</th></tr></thead>
                        <tbody>
                            @forelse ($near as $row)
                                @php $p = $row['progress']; @endphp
                                <tr wire:key="near-{{ $row['goal']->id }}">
                                    <td><a href="{{ route('gym.members.show', [$row['goal']->member, 'tab' => 'progress']) }}" class="font-medium text-gray-900 hover:text-indigo-600">{{ $row['goal']->member->name }}</a>
                                        @if ($p['is_near_target'])<x-ui.badge color="amber">Almost there</x-ui.badge>@endif</td>
                                    <td><span class="inline-flex items-center gap-1.5">{{ $fmt($p['start_weight_kg']) }} <x-ui.icon name="arrow-right" class="size-3.5 text-gray-400" /> {{ $fmt($p['target_weight_kg']) }}</span></td>
                                    <td>{{ $fmt($p['current_weight_kg']) }}</td>
                                    <td class="w-48"><div class="h-2 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full bg-indigo-500" style="width: {{ $p['progress_percent'] }}%"></div></div><span class="text-xs text-gray-500">{{ $p['progress_percent'] }}%</span></td>
                                    <td class="font-medium">{{ $fmt($p['remaining_kg']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><x-ui.empty message="No active weight targets yet." icon="chart" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                @else
                    <table class="table">
                        <thead><tr><th>Member</th><th>Start / Target</th><th>Reached</th><th>Achieved on</th></tr></thead>
                        <tbody>
                            @forelse ($goals as $g)
                                <tr wire:key="ach-{{ $g->id }}">
                                    <td><a href="{{ route('gym.members.show', [$g->member, 'tab' => 'progress']) }}" class="font-medium text-gray-900 hover:text-indigo-600">{{ $g->member?->name }}</a></td>
                                    <td><span class="inline-flex items-center gap-1.5">{{ $fmt($g->start_weight_kg) }} <x-ui.icon name="arrow-right" class="size-3.5 text-gray-400" /> {{ $fmt($g->target_weight_kg) }}</span></td>
                                    <td class="font-semibold text-emerald-600">{{ $fmt($g->achieved_weight_kg) }}</td>
                                    <td>{{ $g->achieved_at?->format('d M Y') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4"><x-ui.empty message="No targets achieved yet." icon="check" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    @if ($goals->hasPages())<div class="border-t border-gray-100 px-4 py-3">{{ $goals->links() }}</div>@endif
                @endif
            </div>
        </div>

        <div class="space-y-6">
            @if ($canEditSettings)
                <form wire:submit="saveInterval" class="card card-body space-y-4">
                    <h2 class="card-title">Weigh-in reminder</h2>
                    <x-ui.field label="Ask members for their weight every" name="everyDays">
                        <select wire:model="everyDays" class="input">
                            <option value="7">7 days (weekly)</option>
                            <option value="15">15 days</option>
                            <option value="30">30 days (monthly)</option>
                        </select>
                    </x-ui.field>
                    <button type="submit" class="btn-primary w-full">Save</button>
                </form>
            @endif
            <div class="card card-body space-y-2 text-sm text-gray-600">
                <h2 class="card-title text-gray-900">How it works</h2>
                <p class="flex gap-2"><x-ui.icon name="scale" class="size-4 shrink-0 text-indigo-500" /> When a weigh-in is due, the member app asks for their weight.</p>
                <p class="flex gap-2"><x-ui.icon name="flag" class="size-4 shrink-0 text-indigo-500" /> Members (or you) set a target; progress updates with every weigh-in.</p>
                <p class="flex gap-2"><x-ui.icon name="bolt" class="size-4 shrink-0 text-amber-500" /> Within about 1 kg of the target they get an "Almost there" popup.</p>
                <p class="flex gap-2"><x-ui.icon name="trophy" class="size-4 shrink-0 text-emerald-500" /> Once the target is reached they get a "Target achieved" popup.</p>
            </div>
        </div>
    </div>
</div>
