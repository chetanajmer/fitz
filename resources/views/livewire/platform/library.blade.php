<div>
    <x-ui.page-header title="Default library" subtitle="Ready-made diet charts & workout plans every gym can import. Edits apply to future imports — gyms keep the copies they already have.">
        <x-slot:actions>
            <div class="inline-flex rounded-lg border border-gray-300 bg-white p-0.5">
                <button wire:click="$set('type', 'diet')" class="rounded-md px-3 py-1.5 text-sm font-medium {{ $type === 'diet' ? 'bg-gray-900 text-white' : 'text-gray-600' }}">Diet charts</button>
                <button wire:click="$set('type', 'workout')" class="rounded-md px-3 py-1.5 text-sm font-medium {{ $type === 'workout' ? 'bg-gray-900 text-white' : 'text-gray-600' }}">Workout plans</button>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-6 grid grid-cols-3 gap-4">
        <x-ui.stat label="Plans in library" :value="$stats['plans']" icon="tag" />
        <x-ui.stat label="Published (importable)" :value="$stats['published']" icon="check" color="green" />
        <x-ui.stat label="Gyms using it" :value="$stats['gymsImported']" icon="building" color="blue" />
    </div>

    <div class="grid gap-6 lg:grid-cols-[22rem_1fr]">
        {{-- ================= Left: categories & plans ================= --}}
        <div class="space-y-4">
            <button wire:click="editCategory" class="btn-secondary w-full border-dashed"><x-ui.icon name="plus" class="size-4" /> Add category</button>

            @foreach ($categories as $ci => $category)
                <div wire:key="cat-{{ $category->id }}" class="card overflow-hidden">
                    <div class="flex items-center gap-3 border-b border-gray-100 p-3">
                        @if ($category->imageUrl())
                            <img src="{{ $category->imageUrl() }}" alt="" class="size-11 shrink-0 rounded-lg object-cover">
                        @else
                            <div class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-lg">{{ $type === 'diet' ? '🥗' : '🏋️' }}</div>
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold">{{ $category->name }}</p>
                            <p class="text-xs text-gray-500">{{ $category->plans->count() }} plans</p>
                        </div>
                        <div class="flex shrink-0 items-center">
                            <button wire:click="moveCategory({{ $category->id }}, -1)" class="btn-ghost btn-sm !px-1.5" @disabled($ci === 0) title="Move up">↑</button>
                            <button wire:click="moveCategory({{ $category->id }}, 1)" class="btn-ghost btn-sm !px-1.5" @disabled($loop->last) title="Move down">↓</button>
                            <button wire:click="editCategory({{ $category->id }})" class="btn-ghost btn-sm !px-1.5" title="Edit category"><x-ui.icon name="cog" class="size-4" /></button>
                        </div>
                    </div>
                    <ul class="divide-y divide-gray-50">
                        @foreach ($category->plans as $pi => $p)
                            <li wire:key="p-{{ $p->id }}" class="flex items-center {{ $plan === (string) $p->id ? 'bg-indigo-50' : 'hover:bg-gray-50' }}">
                                <button wire:click="selectPlan({{ $p->id }})" class="min-w-0 flex-1 px-3 py-2 text-left text-sm">
                                    <span class="block truncate {{ $plan === (string) $p->id ? 'font-medium text-indigo-700' : '' }}">{{ $p->title }}</span>
                                    <span class="text-xs text-gray-400">
                                        {{ $p->sections_count }} sections · 📷 {{ $p->photo_count + ($p->cover_image_path ? 1 : 0) }}
                                        @unless ($p->is_published) · <span class="text-amber-600">Draft</span>@endunless
                                    </span>
                                </button>
                                <div class="flex shrink-0 pr-1">
                                    <button wire:click="movePlan({{ $p->id }}, -1)" class="btn-ghost btn-sm !px-1" @disabled($pi === 0)>↑</button>
                                    <button wire:click="movePlan({{ $p->id }}, 1)" class="btn-ghost btn-sm !px-1" @disabled($loop->last)>↓</button>
                                </div>
                            </li>
                        @endforeach
                        <li>
                            <button wire:click="newPlan({{ $category->id }})" class="flex w-full items-center gap-1 px-3 py-2 text-left text-xs font-medium text-indigo-600 hover:bg-gray-50">
                                <x-ui.icon name="plus" class="size-3.5" /> Add {{ $type === 'diet' ? 'diet chart' : 'workout plan' }}
                            </button>
                        </li>
                    </ul>
                </div>
            @endforeach
        </div>

        {{-- ================= Right: plan editor ================= --}}
        <div>
            @if ($plan === '')
                <div class="card"><x-ui.empty message="Add a category, then add a plan to it." icon="tag" /></div>
            @else
                <form wire:submit="savePlan" class="space-y-4">
                    <div wire:dirty class="sticky top-2 z-20 flex items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 shadow-sm">
                        <span>You have unsaved changes.</span>
                        <button type="submit" class="btn-primary btn-sm">Save now</button>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <div class="min-w-0">
                                <h2 class="truncate text-lg font-semibold">{{ $plan === 'new' ? 'New '.strtolower($planType->label()) : $form['title'] }}</h2>
                                @if ($selected)
                                    <p class="text-xs text-gray-500">Key <code>{{ $selected->key }}</code> · last saved {{ $selected->updated_at->diffForHumans() }}{{ $selected->editor ? ' by '.$selected->editor->name : '' }}</p>
                                @endif
                            </div>
                            @if ($selected)
                                <div class="flex shrink-0 gap-1">
                                    <button type="button" wire:click="duplicatePlan" class="btn-ghost btn-sm">Duplicate</button>
                                    <button type="button" wire:click="deletePlan" wire:confirm="Delete this plan from the library? Gyms keep the copies they already imported." class="btn-ghost btn-sm text-red-600">Delete</button>
                                </div>
                            @endif
                        </div>
                        <div class="card-body grid gap-4 sm:grid-cols-2">
                            <x-ui.field label="Title" name="form.title" required class="sm:col-span-2"><input wire:model="form.title" class="input"></x-ui.field>
                            <x-ui.field label="Category" name="form.library_category_id" required>
                                <select wire:model="form.library_category_id" class="input">@foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
                            </x-ui.field>
                            <x-ui.field label="Level" name="form.level">
                                <select wire:model="form.level" class="input"><option value="">Any</option>@foreach (\App\Models\FitnessPlan::LEVELS as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
                            </x-ui.field>
                            <x-ui.field label="Duration (weeks)" name="form.duration_weeks"><input wire:model="form.duration_weeks" type="number" min="1" class="input"></x-ui.field>
                            @if ($type === 'diet')
                                <x-ui.field label="Calories / day" name="form.daily_calories"><input wire:model="form.daily_calories" type="number" min="300" class="input"></x-ui.field>
                            @endif
                            <x-ui.field label="Summary" name="form.summary" class="sm:col-span-2"><textarea wire:model="form.summary" rows="3" class="input"></textarea></x-ui.field>
                            <label class="flex items-start gap-2 text-sm sm:col-span-2">
                                <input wire:model="form.is_published" type="checkbox" class="mt-0.5 rounded border-gray-300 text-indigo-600">
                                <span>Published — available for gyms to import<span class="block text-xs text-gray-500">Keep new plans as drafts until they're ready.</span></span>
                            </label>
                        </div>
                    </div>

                    {{-- Cover --}}
                    <div class="card card-body flex flex-col gap-4 sm:flex-row sm:items-center">
                        <label class="group relative block aspect-[16/9] w-full shrink-0 cursor-pointer overflow-hidden rounded-lg bg-gray-100 sm:w-64">
                            @if ($cover)
                                <img src="{{ $cover->temporaryUrl() }}" class="size-full object-cover" alt="">
                            @elseif ($selected?->coverUrl() && ! $removeCover)
                                <img src="{{ $selected->coverUrl() }}" class="size-full object-cover" alt="">
                            @else
                                <span class="flex size-full flex-col items-center justify-center gap-1 text-gray-400"><x-ui.icon name="upload" class="size-7" /><span class="text-xs">Add cover photo</span></span>
                            @endif
                            <input type="file" wire:model="cover" accept="image/*" class="sr-only">
                        </label>
                        <div class="text-sm">
                            <p class="font-medium">Cover photo</p>
                            <p class="text-xs text-gray-500">Wide image, e.g. 1600×900. JPG, PNG or WebP up to 5 MB.</p>
                            <div wire:loading wire:target="cover" class="text-xs text-indigo-600">Uploading…</div>
                            @error('cover') <p class="input-error">{{ $message }}</p> @enderror
                            @if ($selected?->cover_image_path && ! $removeCover)
                                <button type="button" wire:click="$set('removeCover', true)" class="mt-1 text-xs text-red-600 hover:underline">Remove cover</button>
                            @elseif ($removeCover)
                                <p class="mt-1 text-xs text-amber-700">Cover will be removed when you save. <button type="button" wire:click="$set('removeCover', false)" class="underline">Undo</button></p>
                            @endif
                        </div>
                    </div>

                    @error('sections') <div class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $message }}</div> @enderror

                    {{-- Sections --}}
                    @foreach ($sections as $i => $section)
                        <div wire:key="section-{{ $section['key'] }}" class="card">
                            <div class="card-header !py-2.5">
                                <span class="text-xs font-semibold tracking-wide text-gray-500 uppercase">{{ $type === 'diet' ? 'Section' : 'Exercise' }} {{ $i + 1 }}</span>
                                <div class="flex gap-1">
                                    <button type="button" wire:click="moveSection('{{ $section['key'] }}', -1)" class="btn-ghost btn-sm" @disabled($i === 0)>↑</button>
                                    <button type="button" wire:click="moveSection('{{ $section['key'] }}', 1)" class="btn-ghost btn-sm" @disabled($i === count($sections) - 1)>↓</button>
                                    <button type="button" wire:click="removeSection('{{ $section['key'] }}')" wire:confirm="Remove this section?" class="btn-ghost btn-sm text-red-600" @disabled(count($sections) === 1)>Remove</button>
                                </div>
                            </div>
                            <div class="card-body grid gap-4 sm:grid-cols-[1fr_11rem]">
                                <div class="space-y-3">
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <x-ui.field label="Title" :name="'sections.'.$i.'.title'" required><input wire:model="sections.{{ $i }}.title" class="input" placeholder="{{ $hints['title'] }}"></x-ui.field>
                                        <x-ui.field label="Subtitle" :name="'sections.'.$i.'.subtitle'"><input wire:model="sections.{{ $i }}.subtitle" class="input" placeholder="{{ $hints['subtitle'] }}"></x-ui.field>
                                    </div>
                                    <x-ui.field label="Details" :name="'sections.'.$i.'.body'"><textarea wire:model="sections.{{ $i }}.body" rows="4" class="input" placeholder="{{ $hints['body'] }}"></textarea></x-ui.field>
                                </div>
                                <div>
                                    <label class="group relative block aspect-[4/3] w-full cursor-pointer overflow-hidden rounded-lg bg-gray-100">
                                        @if (isset($uploads[$section['key']]) && method_exists($uploads[$section['key']], 'temporaryUrl'))
                                            <img src="{{ $uploads[$section['key']]->temporaryUrl() }}" class="size-full object-cover" alt="">
                                        @elseif ($section['image_url'])
                                            <img src="{{ $section['image_url'] }}" class="size-full object-cover" alt="">
                                        @else
                                            <span class="flex size-full flex-col items-center justify-center gap-1 text-gray-400"><x-ui.icon name="upload" class="size-6" /><span class="text-xs">Add photo</span></span>
                                        @endif
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

                    <button type="button" wire:click="addSection" class="btn-secondary w-full border-dashed py-3"><x-ui.icon name="plus" class="size-4" /> Add {{ $type === 'diet' ? 'meal / section' : 'exercise' }}</button>

                    <div class="flex justify-end">
                        <button type="submit" class="btn-primary px-6 py-2.5" wire:loading.attr="disabled" wire:target="savePlan">
                            <span wire:loading.remove wire:target="savePlan">{{ $plan === 'new' ? 'Create plan' : 'Save changes' }}</span>
                            <span wire:loading wire:target="savePlan">Saving…</span>
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    {{-- Category modal --}}
    <x-ui.modal wire-model="showCategory" :title="$categoryId ? 'Edit category' : 'New category'">
        <form wire:submit="saveCategory" class="space-y-4">
            <x-ui.field label="Name" name="categoryForm.name" required><input wire:model="categoryForm.name" class="input"></x-ui.field>
            <x-ui.field label="Description" name="categoryForm.description"><textarea wire:model="categoryForm.description" rows="2" class="input"></textarea></x-ui.field>
            <x-ui.field label="Photo" name="categoryImage">
                <input wire:model="categoryImage" type="file" accept="image/*" class="text-sm">
                @if ($categoryImage)<img src="{{ $categoryImage->temporaryUrl() }}" class="mt-2 h-24 rounded-lg object-cover" alt="">@endif
            </x-ui.field>
            @if ($categoryId)
                <label class="flex items-center gap-2 text-sm"><input wire:model="categoryForm.remove_image" type="checkbox" class="rounded border-gray-300"> Remove current photo</label>
            @endif
            <div class="flex items-center justify-between gap-2">
                @if ($categoryId)
                    <button type="button" wire:click="deleteCategory" wire:confirm="Delete this category AND all its plans from the library? Gyms keep what they already imported." class="btn-ghost text-red-600">Delete category</button>
                @else
                    <span></span>
                @endif
                <div class="flex gap-2">
                    <button type="button" wire:click="$set('showCategory', false)" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">Save</button>
                </div>
            </div>
        </form>
    </x-ui.modal>
</div>
