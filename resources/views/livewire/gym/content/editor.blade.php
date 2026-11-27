<div>
    <x-ui.page-header :title="($plan ? 'Edit ' : 'New ').strtolower($planType->label())"
        :subtitle="$member ? 'Customised for '.$member->name.' ('.$member->member_code.') — only this member will see it.' : 'Default plan — visible to every member of your gym.'">
        <x-slot:actions>
            <a href="{{ $plan ? route('gym.content.show', $plan) : route('gym.content.index', $planType->value) }}" class="btn-secondary">Cancel</a>
        </x-slot:actions>
    </x-ui.page-header>

    <form wire:submit="save" class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            @error('sections') <div class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $message }}</div> @enderror

            @foreach ($sections as $i => $section)
                <div wire:key="section-{{ $section['key'] }}" class="card">
                    <div class="card-header !py-3">
                        <span class="text-xs font-semibold tracking-wide text-gray-500 uppercase">{{ $planType === \App\Enums\FitnessPlanType::Diet ? 'Meal' : 'Exercise' }} {{ $i + 1 }}</span>
                        <div class="flex gap-1">
                            <button type="button" wire:click="moveSection('{{ $section['key'] }}', -1)" class="btn-ghost btn-sm" @disabled($i === 0) title="Move up"><x-ui.icon name="arrow-up" class="size-4" /></button>
                            <button type="button" wire:click="moveSection('{{ $section['key'] }}', 1)" class="btn-ghost btn-sm" @disabled($i === count($sections) - 1) title="Move down"><x-ui.icon name="arrow-down" class="size-4" /></button>
                            <button type="button" wire:click="removeSection('{{ $section['key'] }}')" wire:confirm="Remove this section?" class="btn-ghost btn-sm text-red-600" @disabled(count($sections) === 1)>Remove</button>
                        </div>
                    </div>
                    <div class="card-body grid gap-4 sm:grid-cols-[1fr_11rem]">
                        <div class="space-y-3">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <x-ui.field label="Title" :name="'sections.'.$i.'.title'" required><input wire:model="sections.{{ $i }}.title" class="input" placeholder="{{ $hints['title'] }}"></x-ui.field>
                                <x-ui.field label="Subtitle" :name="'sections.'.$i.'.subtitle'"><input wire:model="sections.{{ $i }}.subtitle" class="input" placeholder="{{ $hints['subtitle'] }}"></x-ui.field>
                            </div>
                            @if ($planType === \App\Enums\FitnessPlanType::Diet)
                                <x-ui.field label="Meal time" :name="'sections.'.$i.'.meal_time'" hint="Members who switch on meal reminders in the app get a reminder at this time. Leave empty for tips and guidelines (taken from the subtitle when it has a time, e.g. 8:30 AM).">
                                    <input wire:model="sections.{{ $i }}.meal_time" type="time" class="input sm:max-w-40">
                                </x-ui.field>
                            @endif
                            <x-ui.field label="Details" :name="'sections.'.$i.'.body'"><textarea wire:model="sections.{{ $i }}.body" rows="4" class="input" placeholder="{{ $hints['body'] }}"></textarea></x-ui.field>
                        </div>
                        <div>
                            <p class="label">Photo</p>
                            @if (isset($uploads[$section['key']]) && method_exists($uploads[$section['key']], 'temporaryUrl'))
                                <img src="{{ $uploads[$section['key']]->temporaryUrl() }}" class="aspect-square w-full rounded-lg object-cover" alt="">
                            @elseif ($section['image_url'])
                                <img src="{{ $section['image_url'] }}" class="aspect-square w-full rounded-lg object-cover" alt="">
                            @else
                                <div class="flex aspect-square w-full items-center justify-center rounded-lg border-2 border-dashed border-gray-200 text-gray-300"><x-ui.icon name="upload" class="size-8" /></div>
                            @endif
                            <label class="btn-secondary btn-sm mt-2 w-full cursor-pointer">
                                {{ $section['image_url'] || isset($uploads[$section['key']]) ? 'Change' : 'Add photo' }}
                                <input type="file" wire:model="uploads.{{ $section['key'] }}" accept="image/*" class="sr-only">
                            </label>
                            @if ($section['image_url'] || isset($uploads[$section['key']]))
                                <button type="button" wire:click="removeSectionImage('{{ $section['key'] }}')" class="mt-1 w-full text-xs text-red-600 hover:underline">Remove photo</button>
                            @endif
                            <div wire:loading wire:target="uploads.{{ $section['key'] }}" class="hint">Uploading…</div>
                            @error('uploads.'.$section['key']) <p class="input-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            @endforeach

            <button type="button" wire:click="addSection" class="btn-secondary w-full border-dashed py-3">
                <x-ui.icon name="plus" class="size-4" /> Add {{ $planType === \App\Enums\FitnessPlanType::Diet ? 'meal' : 'exercise' }}
            </button>
        </div>

        <div class="space-y-6">
            <div class="card card-body space-y-4">
                <x-ui.field label="Title" name="form.title" required><input wire:model="form.title" class="input" placeholder="{{ $planType === \App\Enums\FitnessPlanType::Diet ? 'Fat-loss vegetarian chart' : 'Push day — chest, shoulders, triceps' }}"></x-ui.field>
                <x-ui.field label="Category" name="form.plan_category_id">
                    <select wire:model="form.plan_category_id" class="input">
                        <option value="">Uncategorised</option>
                        @foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </select>
                    @if ($categories->isEmpty() && auth()->user()->can('content.manage'))<p class="hint">Create categories in the library's Categories tab.</p>@endif
                </x-ui.field>
                <x-ui.field label="Summary" name="form.summary"><textarea wire:model="form.summary" rows="3" class="input" placeholder="Short description shown on the plan card"></textarea></x-ui.field>
                <div class="grid grid-cols-2 gap-3">
                    <x-ui.field label="Level" name="form.level">
                        <select wire:model="form.level" class="input"><option value="">Any</option>@foreach (\App\Models\FitnessPlan::LEVELS as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
                    </x-ui.field>
                    <x-ui.field label="Duration (weeks)" name="form.duration_weeks"><input wire:model="form.duration_weeks" type="number" min="1" class="input"></x-ui.field>
                    @if ($planType === \App\Enums\FitnessPlanType::Diet)
                        <x-ui.field label="Calories / day" name="form.daily_calories"><input wire:model="form.daily_calories" type="number" min="300" class="input" placeholder="1800"></x-ui.field>
                    @endif
                    <x-ui.field label="Sort order" name="form.sort_order"><input wire:model="form.sort_order" type="number" min="0" class="input"></x-ui.field>
                </div>
                <label class="flex items-center gap-2 text-sm"><input wire:model="form.is_published" type="checkbox" class="rounded border-gray-300 text-indigo-600"> Published (visible in the member app)</label>
            </div>

            <div class="card card-body space-y-3">
                <p class="label !mb-0">Cover photo</p>
                @if ($cover)
                    <img src="{{ $cover->temporaryUrl() }}" class="aspect-[16/9] w-full rounded-lg object-cover" alt="">
                @elseif ($plan?->coverUrl() && ! $removeCover)
                    <img src="{{ $plan->coverUrl() }}" class="aspect-[16/9] w-full rounded-lg object-cover" alt="">
                @endif
                <input wire:model="cover" type="file" accept="image/*" class="text-sm">
                @if ($plan?->cover_image_path)
                    <label class="flex items-center gap-2 text-sm"><input wire:model.live="removeCover" type="checkbox" class="rounded border-gray-300"> Remove cover photo</label>
                @endif
                @error('cover') <p class="input-error">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="btn-primary w-full py-3" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">{{ $member ? 'Save & send to '.\Illuminate\Support\Str::before($member->name, ' ') : 'Save plan' }}</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
        </div>
    </form>
</div>
