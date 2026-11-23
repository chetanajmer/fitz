<div>
    <x-ui.page-header title="Programs" subtitle="Day-by-day workout and diet schedules. Assign one to a member and they see the right plan for every day in their app.">
        @if ($canManage)
            <x-slot:actions>
                <button wire:click="openImport" class="btn-secondary"><x-ui.icon name="upload" class="size-4" /> Import default programs</button>
                <button wire:click="create" class="btn-primary"><x-ui.icon name="plus" class="size-4" /> New program</button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <div class="mb-4 flex flex-wrap items-center gap-6 border-b border-gray-200">
        <button wire:click="$set('type', 'workout')" class="tab {{ $type === 'workout' ? 'tab-active' : '' }}"><span class="inline-flex items-center gap-1.5"><x-ui.icon name="dumbbell" class="size-4" /> Workout programs</span></button>
        <button wire:click="$set('type', 'diet')" class="tab {{ $type === 'diet' ? 'tab-active' : '' }}"><span class="inline-flex items-center gap-1.5"><x-ui.icon name="meal" class="size-4" /> Diet programs</span></button>
    </div>

    <div class="mb-6 flex flex-wrap gap-3">
        <select wire:model.live="goal" class="input w-auto">
            <option value="">All goals</option>
            @foreach ($goals as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
        </select>
        @if ($isDiet)
            <select wire:model.live="preference" class="input w-auto">
                <option value="">Veg, egg, non-veg & vegan</option>
                @foreach (\App\Enums\DietPreference::options() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
            </select>
        @endif
    </div>

    @forelse ($programs as $goalLabel => $group)
        <h2 class="mb-3 text-sm font-semibold tracking-wide text-gray-500 uppercase">{{ $goalLabel }}</h2>
        <div class="mb-8 grid gap-4 lg:grid-cols-2">
            @foreach ($group as $p)
                <div wire:key="prog-{{ $p->id }}" class="card card-body {{ $p->is_active ? '' : 'opacity-60' }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="font-semibold text-gray-900">{{ $p->title }}</h3>
                            <div class="mt-1 flex flex-wrap gap-1.5">
                                @if ($p->diet_preference)<x-ui.badge :color="$p->diet_preference->color()">{{ $p->diet_preference->label() }}</x-ui.badge>@endif
                                @if ($p->level)<x-ui.badge color="blue">{{ \App\Models\FitnessPlan::LEVELS[$p->level] ?? ucfirst($p->level) }}</x-ui.badge>@endif
                                <x-ui.badge color="gray">{{ $p->duration_weeks }} weeks · {{ $p->cycleLength() }}-day cycle</x-ui.badge>
                                @unless ($p->is_active)<x-ui.badge color="gray">Inactive</x-ui.badge>@endunless
                            </div>
                        </div>
                        <span class="shrink-0 text-xs text-gray-500">{{ $p->active_members }} {{ Str::plural('member', $p->active_members) }}</span>
                    </div>
                    @if ($p->summary)<p class="mt-2 text-sm text-gray-600">{{ $p->summary }}</p>@endif
                    <ol class="mt-3 grid gap-1 text-xs sm:grid-cols-2">
                        @foreach ($p->days as $d)
                            <li class="flex gap-2 rounded-md px-2 py-1 {{ $d->is_rest ? 'bg-gray-50 text-gray-500' : 'bg-indigo-50 text-indigo-900' }}">
                                <span class="font-semibold">Day {{ $d->day_number }}</span>
                                <span class="truncate">{{ $d->title ?: ($d->plan?->title ?? 'Rest') }}</span>
                            </li>
                        @endforeach
                    </ol>
                    @if ($canManage)
                        <div class="mt-4 flex justify-end gap-1 border-t border-gray-100 pt-3">
                            <button wire:click="toggleActive({{ $p->id }})" class="btn-ghost btn-sm">{{ $p->is_active ? 'Deactivate' : 'Activate' }}</button>
                            <button wire:click="edit({{ $p->id }})" class="btn-secondary btn-sm">Edit</button>
                            <button wire:click="delete({{ $p->id }})" wire:confirm="Delete {{ $p->title }}?" class="btn-ghost btn-sm text-red-600">Delete</button>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @empty
        <div class="card">
            <x-ui.empty :message="$total ? 'No programs match these filters.' : 'No '.$type.' programs yet. Import the ready-made programs to get started.'" icon="calendar">
                @if ($canManage && ! $total)<button wire:click="openImport" class="btn-primary btn-sm">Import default programs</button>@endif
            </x-ui.empty>
        </div>
    @endforelse

    {{-- Import --}}
    <x-ui.modal wire-model="showImport" title="Import default programs" max-width="2xl">
        <form wire:submit="import" class="space-y-4">
            <p class="text-sm text-gray-600">Ready-made {{ $isDiet ? 'diet programs for veg, eggetarian, non-veg and vegan members' : 'workout programs for every common goal' }}. The {{ $isDiet ? 'diet charts' : 'workout plans' }} they use are imported too. Everything is editable afterwards.</p>
            <div class="max-h-96 divide-y divide-gray-100 overflow-y-auto rounded-lg border border-gray-200">
                @foreach ($catalogue as $c)
                    <label wire:key="cat-{{ $c['key'] }}" class="flex cursor-pointer items-start gap-3 p-3 hover:bg-gray-50 {{ $c['imported'] ? 'opacity-60' : '' }}">
                        <input type="checkbox" wire:model="importKeys" value="{{ $c['key'] }}" @disabled($c['imported']) class="mt-1 rounded border-gray-300 text-indigo-600">
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-medium">{{ $c['title'] }} @if ($c['imported'])<x-ui.badge color="green">Imported</x-ui.badge>@endif</span>
                            <span class="block text-xs text-gray-500">{{ \App\Enums\ProgramGoal::from($c['goal'])->label() }}@if (! empty($c['diet_preference'])) · {{ \App\Enums\DietPreference::from($c['diet_preference'])->label() }}@endif · {{ $c['duration_weeks'] }} weeks · {{ count($c['days']) }}-day cycle</span>
                        </span>
                    </label>
                @endforeach
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" wire:click="$set('showImport', false)" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary" wire:loading.attr="disabled">Import {{ count($importKeys) ?: '' }}</button>
            </div>
        </form>
    </x-ui.modal>

    {{-- Editor --}}
    <x-ui.modal wire-model="showEditor" :title="$editingId ? 'Edit program' : 'New '.$type.' program'" max-width="3xl">
        <form wire:submit="save" class="space-y-5">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Title" name="form.title" required class="sm:col-span-2"><input wire:model="form.title" class="input" placeholder="{{ $isDiet ? 'Weight Loss — Vegetarian' : 'Weight Loss — Beginner' }}"></x-ui.field>
                <x-ui.field label="Goal" name="form.goal" required>
                    <select wire:model="form.goal" class="input">@foreach ($goals as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                </x-ui.field>
                @if ($isDiet)
                    <x-ui.field label="Diet preference" name="form.diet_preference" required>
                        <select wire:model="form.diet_preference" class="input">@foreach (\App\Enums\DietPreference::options() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                    </x-ui.field>
                @else
                    <x-ui.field label="Level" name="form.level" required>
                        <select wire:model="form.level" class="input">@foreach (\App\Models\FitnessPlan::LEVELS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                    </x-ui.field>
                @endif
                <x-ui.field label="Duration (weeks)" name="form.duration_weeks" required hint="The day cycle repeats for the whole duration."><input wire:model="form.duration_weeks" type="number" min="1" max="52" class="input"></x-ui.field>
                <label class="flex items-end gap-2 pb-2 text-sm font-medium"><input wire:model="form.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600"> Active (can be assigned)</label>
                <x-ui.field label="Summary" name="form.summary" class="sm:col-span-2"><textarea wire:model="form.summary" rows="2" class="input"></textarea></x-ui.field>
            </div>

            <div>
                <div class="mb-2 flex items-center justify-between">
                    <h3 class="text-sm font-semibold">Days in the cycle ({{ count($days) }})</h3>
                    <button type="button" wire:click="addDay" class="btn-ghost btn-sm"><x-ui.icon name="plus" class="size-4" /> Add day</button>
                </div>
                @error('days') <p class="mb-2 text-sm text-red-600">{{ $message }}</p> @enderror
                <div class="space-y-2">
                    @foreach ($days as $i => $day)
                        <div wire:key="day-{{ $i }}" class="grid items-start gap-2 rounded-lg border border-gray-200 p-2 sm:grid-cols-[4rem_1fr_1fr_auto]">
                            <span class="pt-2 text-sm font-semibold text-gray-700">Day {{ $i + 1 }}</span>
                            <select wire:model="days.{{ $i }}.fitness_plan_id" class="input">
                                <option value="">Rest day</option>
                                @foreach ($plans as $plan)<option value="{{ $plan->id }}">{{ $plan->title }}</option>@endforeach
                            </select>
                            <input wire:model="days.{{ $i }}.title" class="input" placeholder="Label (optional), e.g. Leg day">
                            <button type="button" wire:click="removeDay({{ $i }})" class="btn-ghost btn-sm text-red-600" @disabled(count($days) === 1)>Remove</button>
                            <input wire:model="days.{{ $i }}.notes" class="input sm:col-span-2 sm:col-start-2" placeholder="Note for the member (optional)">
                        </div>
                    @endforeach
                </div>
                <p class="mt-2 text-xs text-gray-500">Choose from your {{ $isDiet ? 'diet charts' : 'workout plans' }} (default plans). A 7-day cycle maps to one week; use 14 days for A/B alternating programs.</p>
            </div>

            <div class="flex justify-end gap-2">
                <button type="button" wire:click="$set('showEditor', false)" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary" wire:loading.attr="disabled">Save program</button>
            </div>
        </form>
    </x-ui.modal>
</div>
