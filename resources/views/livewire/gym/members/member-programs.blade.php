<div class="mb-8 space-y-4">
    <div class="grid gap-4 lg:grid-cols-2">
        @foreach (['workout' => ['dumbbell', 'Workout program'], 'diet' => ['meal', 'Diet program']] as $type => [$icon, $label])
            @php $a = $current[$type]; $s = $a ? $summaries[$a->id] : null; $t = $today[$type]; @endphp
            <div class="card card-body">
                <div class="flex items-start justify-between gap-3">
                    <p class="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-gray-500 uppercase"><x-ui.icon :name="$icon" class="size-4" /> {{ $label }}</p>
                    @if ($canAssign)
                        <button wire:click="openAssign('{{ $type }}')" class="btn-secondary btn-sm">{{ $a ? 'Change' : 'Assign' }}</button>
                    @endif
                </div>
                @if ($a)
                    <h3 class="mt-2 font-semibold text-gray-900">{{ $s['program']['title'] }}</h3>
                    <p class="text-xs text-gray-500">
                        {{ $s['program']['goal_label'] }}@if ($s['program']['diet_preference_label']) · {{ $s['program']['diet_preference_label'] }}@endif
                        · {{ \Illuminate\Support\Carbon::parse($s['start_date'])->format('d M') }} – {{ \Illuminate\Support\Carbon::parse($s['end_date'])->format('d M Y') }}
                        @if ($s['state'] === 'upcoming')<x-ui.badge color="blue">Starts {{ \Illuminate\Support\Carbon::parse($s['start_date'])->format('d M') }}</x-ui.badge>@endif
                    </p>
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full bg-indigo-500" style="width: {{ $s['progress_percent'] }}%"></div></div>
                    <p class="mt-1 text-xs text-gray-500">Day {{ $s['current_day'] }} of {{ $s['total_days'] }} · week {{ $s['current_week'] }}</p>
                    @if ($t)
                        <div class="mt-3 rounded-lg {{ $t['is_rest'] ? 'bg-gray-50' : 'bg-indigo-50' }} p-3 text-sm">
                            <span class="font-medium">Today:</span> {{ $t['title'] }}
                            @if ($t['plan'])<a href="{{ route('gym.content.show', $t['plan']['id']) }}" class="ml-1 inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:underline">View plan <x-ui.icon name="arrow-right" class="size-3.5" /></a>@endif
                        </div>
                    @endif
                    @if ($canAssign)
                        <div class="mt-3 text-right"><button wire:click="stop({{ $a->id }})" wire:confirm="Stop this program for {{ $member->name }}?" class="text-xs font-medium text-red-600 hover:underline">Stop program</button></div>
                    @endif
                @else
                    <p class="mt-2 text-sm text-gray-500">No {{ strtolower($label) }} assigned. {{ $type === 'diet' && $member->diet_preference ? 'Preference: '.$member->diet_preference->label().'.' : '' }}</p>
                @endif
            </div>
        @endforeach
    </div>

    @if ($current->filter()->isNotEmpty())
        <div class="card overflow-x-auto">
            <div class="card-header"><h2 class="card-title">Next 7 days</h2></div>
            <div class="grid min-w-[42rem] grid-cols-7 divide-x divide-gray-100">
                @foreach ($week as $d)
                    <div wire:key="wk-{{ $d['date'] }}" class="p-3 text-xs {{ $d['is_today'] ? 'bg-indigo-50/60' : '' }}">
                        <p class="font-semibold {{ $d['is_today'] ? 'text-indigo-700' : 'text-gray-700' }}">{{ $d['weekday'] }} {{ \Illuminate\Support\Carbon::parse($d['date'])->format('d') }}</p>
                        @if ($d['workout'])<p class="mt-1 flex items-start gap-1 {{ $d['workout']['is_rest'] ? 'text-gray-400' : 'text-gray-800' }}"><x-ui.icon :name="$d['workout']['is_rest'] ? 'moon' : 'dumbbell'" class="mt-px size-3.5 shrink-0" /> {{ $d['workout']['title'] }}</p>@endif
                        @if ($d['diet'])<p class="mt-1 flex items-start gap-1 text-gray-600"><x-ui.icon name="meal" class="mt-px size-3.5 shrink-0" /> {{ $d['diet']['title'] }}</p>@endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if ($history->isNotEmpty())
        <details class="card">
            <summary class="card-header cursor-pointer"><h2 class="card-title">Program history ({{ $history->count() }})</h2></summary>
            <ul class="divide-y divide-gray-100 text-sm">
                @foreach ($history as $h)
                    <li wire:key="ph-{{ $h->id }}" class="flex items-center justify-between gap-3 px-5 py-2.5">
                        <span class="inline-flex items-center gap-1.5"><x-ui.icon :name="$h->type->value === 'diet' ? 'meal' : 'dumbbell'" class="size-4 text-gray-400" /> {{ $h->program?->title }} <span class="text-xs text-gray-500">{{ $h->start_date->format('d M Y') }} – {{ $h->end_date->format('d M Y') }}@if ($h->assigner) · by {{ $h->assigner->name }}@endif</span></span>
                        <x-ui.badge :color="$h->state() === 'completed' ? 'green' : 'gray'">{{ ucfirst($h->state()) }}</x-ui.badge>
                    </li>
                @endforeach
            </ul>
        </details>
    @endif

    @if ($canAssign)
        <x-ui.modal wire-model="showAssign" :title="$assignType === 'diet' ? 'Assign diet program' : 'Assign workout program'" max-width="xl">
            <form wire:submit="assign" class="space-y-4">
                @error('program') <div class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $message }}</div> @enderror
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-ui.field label="Goal" name="goal">
                        <select wire:model.live="goal" class="input"><option value="">All goals</option>@foreach ($goals as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                    </x-ui.field>
                    @if ($assignType === 'diet')
                        <x-ui.field label="Diet preference" name="preference">
                            <select wire:model.live="preference" class="input"><option value="">Any</option>@foreach (\App\Enums\DietPreference::options() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                        </x-ui.field>
                    @endif
                </div>
                <x-ui.field label="Program" name="programId" required>
                    <div class="max-h-64 space-y-1.5 overflow-y-auto">
                        @forelse ($options as $o)
                            <label wire:key="opt-{{ $o->id }}" class="flex cursor-pointer items-start gap-3 rounded-lg border p-2.5 {{ (string) $o->id === $programId ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200 hover:bg-gray-50' }}">
                                <input type="radio" wire:model.live="programId" value="{{ $o->id }}" class="mt-1 text-indigo-600">
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium">{{ $o->title }}</span>
                                    <span class="block text-xs text-gray-500">{{ $o->duration_weeks }} weeks · {{ $o->cycleLength() }}-day cycle @if ($o->level)· {{ ucfirst($o->level) }}@endif @if ($o->diet_preference)· {{ $o->diet_preference->label() }}@endif</span>
                                </span>
                            </label>
                        @empty
                            <p class="rounded-lg bg-gray-50 p-3 text-sm text-gray-500">
                                No matching programs.
                                @if (! $hasPrograms)<a href="{{ route('gym.programs', ['type' => $assignType]) }}" class="font-medium text-indigo-600 hover:underline">Import the default programs</a> first.@else Try another goal or preference.@endif
                            </p>
                        @endforelse
                    </div>
                </x-ui.field>
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-ui.field label="Start date" name="startDate" required><input wire:model="startDate" type="date" class="input"></x-ui.field>
                    <x-ui.field label="Note for the member" name="notes"><input wire:model="notes" class="input" placeholder="Optional"></x-ui.field>
                </div>
                <p class="text-xs text-gray-500">Any current {{ $assignType }} program is stopped and replaced. The member sees each day's plan in their app.</p>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="$set('showAssign', false)" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled">Assign program</button>
                </div>
            </form>
        </x-ui.modal>
    @endif
</div>
