<div>
    <x-ui.page-header :title="$planType->pluralLabel()" :subtitle="$planType === \App\Enums\FitnessPlanType::Diet ? 'Default diet charts are visible to every member. Send a customised chart to a specific member.' : 'Default workout plans are visible to every member. Send a customised plan to a specific member.'">
        <x-slot:actions>
            @if ($canManage)
                <button wire:click="openImport" class="btn-secondary"><x-ui.icon name="upload" class="size-4" /> Import default library</button>
            @endif
            <button wire:click="openSend" class="btn-secondary"><x-ui.icon name="user" class="size-4" /> Custom for a member</button>
            @if ($canManage)
                <a href="{{ route('gym.content.create', ['type' => $planType->value]) }}" class="btn-primary"><x-ui.icon name="plus" class="size-4" /> New default {{ strtolower($planType->label()) }}</a>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-5 flex gap-6 overflow-x-auto border-b border-gray-200">
        <button wire:click="$set('tab', 'defaults')" class="tab {{ $tab === 'defaults' ? 'tab-active' : '' }}">Default (all members)</button>
        <button wire:click="$set('tab', 'custom')" class="tab {{ $tab === 'custom' ? 'tab-active' : '' }}">Customised for members</button>
        @if ($canManage)
            <button wire:click="$set('tab', 'categories')" class="tab {{ $tab === 'categories' ? 'tab-active' : '' }}">Categories ({{ $categories->count() }})</button>
        @endif
    </div>

    @if ($tab === 'categories')
        <div class="mb-4 flex justify-end"><button wire:click="editCategory" class="btn-primary"><x-ui.icon name="plus" class="size-4" /> Add category</button></div>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($categories as $c)
                <div wire:key="cat-{{ $c->id }}" class="card overflow-hidden {{ $c->is_active ? '' : 'opacity-60' }}">
                    @if ($c->imageUrl())
                        <img src="{{ $c->imageUrl() }}" alt="" class="aspect-[16/7] w-full object-cover">
                    @else
                        <div class="flex aspect-[16/7] items-center justify-center bg-gradient-to-br from-indigo-50 to-sky-50 text-indigo-300"><x-ui.icon :name="$planType === \App\Enums\FitnessPlanType::Diet ? 'meal' : 'dumbbell'" class="size-10" /></div>
                    @endif
                    <div class="card-body">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="font-semibold">{{ $c->name }}</h3>
                            @unless ($c->is_active)<x-ui.badge>Hidden</x-ui.badge>@endunless
                        </div>
                        @if ($c->description)<p class="mt-1 text-sm text-gray-600">{{ $c->description }}</p>@endif
                        <div class="mt-4 flex items-center justify-between text-sm">
                            <button wire:click="showCategoryPlans({{ $c->id }})" class="text-indigo-600 hover:underline"><span class="inline-flex items-center gap-1">{{ $c->plans_count }} plans <x-ui.icon name="arrow-right" class="size-3.5" /></span></button>
                            <div class="flex gap-1">
                                <button wire:click="editCategory({{ $c->id }})" class="btn-secondary btn-sm">Edit</button>
                                <button wire:click="deleteCategory({{ $c->id }})" wire:confirm="Delete category “{{ $c->name }}”? Its plans stay, uncategorised." class="btn-ghost btn-sm text-red-600">Delete</button>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="card sm:col-span-2 lg:col-span-3">
                    <x-ui.empty :message="$planType === \App\Enums\FitnessPlanType::Diet ? 'No categories yet. Try Weight Loss, Muscle Gain, Vegetarian, Diabetic-friendly…' : 'No categories yet. Try Chest, Back, Legs, Full Body, Cardio, Beginner…'">
                        <button wire:click="editCategory" class="btn-primary btn-sm">Add category</button>
                    </x-ui.empty>
                </div>
            @endforelse
        </div>
    @else
        <div class="mb-4 flex flex-col gap-3 md:flex-row">
            <div class="relative flex-1">
                <x-ui.icon name="search" class="pointer-events-none absolute top-2.5 left-3 size-4 text-gray-400" />
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ $tab === 'custom' ? 'Plan title or member name…' : 'Search plans…' }}" class="input pl-9">
            </div>
            <select wire:model.live="category" class="input md:w-56">
                <option value="">All categories</option>
                @foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                <option value="none">Uncategorised</option>
            </select>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($plans as $p)
                <div wire:key="plan-{{ $p->id }}" class="card flex flex-col overflow-hidden {{ $p->is_published ? '' : 'opacity-70' }}">
                    <a href="{{ route('gym.content.show', $p) }}">
                        @if ($p->coverUrl())
                            <img src="{{ $p->coverUrl() }}" alt="" class="aspect-[16/9] w-full object-cover">
                        @else
                            <div class="flex aspect-[16/9] items-center justify-center bg-gradient-to-br from-rose-50 to-amber-50 text-rose-300"><x-ui.icon :name="$planType === \App\Enums\FitnessPlanType::Diet ? 'meal' : 'dumbbell'" class="size-12" /></div>
                        @endif
                    </a>
                    <div class="card-body flex flex-1 flex-col">
                        <div class="flex flex-wrap gap-1">
                            @if ($p->category)<x-ui.badge color="indigo">{{ $p->category->name }}</x-ui.badge>@endif
                            @if ($p->level)<x-ui.badge>{{ \App\Models\FitnessPlan::LEVELS[$p->level] ?? $p->level }}</x-ui.badge>@endif
                            @unless ($p->is_published)<x-ui.badge color="amber">Draft</x-ui.badge>@endunless
                        </div>
                        <a href="{{ route('gym.content.show', $p) }}" class="mt-2 font-semibold text-gray-900 hover:text-indigo-600">{{ $p->title }}</a>
                        @if ($p->member)<p class="text-sm text-rose-600">For {{ $p->member->name }} ({{ $p->member->member_code }})</p>@endif
                        @if ($p->summary)<p class="mt-1 line-clamp-2 text-sm text-gray-600">{{ $p->summary }}</p>@endif
                        <p class="mt-2 text-xs text-gray-500">
                            {{ $p->sections_count }} {{ $planType === \App\Enums\FitnessPlanType::Diet ? 'meals' : 'exercises' }}
                            @if ($p->daily_calories) · {{ number_format($p->daily_calories) }} kcal/day @endif
                            @if ($p->duration_weeks) · {{ $p->duration_weeks }} weeks @endif
                            · updated {{ $p->updated_at->diffForHumans() }}
                        </p>
                        <div class="mt-auto flex flex-wrap items-center gap-1 border-t border-gray-100 pt-3">
                            @can('update', $p)
                                <a href="{{ route('gym.content.edit', $p) }}" class="btn-secondary btn-sm">Edit</a>
                                <button wire:click="togglePublished({{ $p->id }})" class="btn-ghost btn-sm">{{ $p->is_published ? 'Unpublish' : 'Publish' }}</button>
                            @endcan
                            @if (! $p->member_id)
                                <button wire:click="openSend({{ $p->id }})" class="btn-ghost btn-sm" title="Copy to a member and customise">Send to member</button>
                            @endif
                            @can('delete', $p)
                                <button wire:click="deletePlan({{ $p->id }})" wire:confirm="Delete “{{ $p->title }}”?" class="btn-ghost btn-sm ml-auto text-red-600">Delete</button>
                            @endcan
                        </div>
                    </div>
                </div>
            @empty
                <div class="card sm:col-span-2 lg:col-span-3">
                    <x-ui.empty :message="$tab === 'custom' ? 'No customised plans yet.' : 'No plans here yet.'">
                        @if ($tab === 'custom')
                            <button wire:click="openSend" class="btn-primary btn-sm">Create one for a member</button>
                        @elseif ($canManage)
                            <div class="flex flex-wrap justify-center gap-2">
                                <button wire:click="openImport" class="btn-primary btn-sm"><x-ui.icon name="upload" class="size-4" /> Import Gymai's ready-made {{ strtolower($planType->pluralLabel()) }}</button>
                                <a href="{{ route('gym.content.create', ['type' => $planType->value]) }}" class="btn-secondary btn-sm">Create your own</a>
                            </div>
                        @endif
                    </x-ui.empty>
                </div>
            @endforelse
        </div>
        @if ($plans->hasPages())<div class="mt-4">{{ $plans->links() }}</div>@endif
    @endif

    {{-- Category modal --}}
    <x-ui.modal wire-model="showCategory" :title="$categoryId ? 'Edit category' : 'New category'">
        <form wire:submit="saveCategory" class="space-y-4">
            <x-ui.field label="Name" name="categoryForm.name" required><input wire:model="categoryForm.name" class="input" placeholder="{{ $planType === \App\Enums\FitnessPlanType::Diet ? 'Weight Loss' : 'Chest & Triceps' }}"></x-ui.field>
            <x-ui.field label="Description" name="categoryForm.description"><textarea wire:model="categoryForm.description" rows="2" class="input"></textarea></x-ui.field>
            <x-ui.field label="Image" name="categoryImage">
                <input wire:model="categoryImage" type="file" accept="image/*" class="text-sm">
                @if ($categoryImage)<img src="{{ $categoryImage->temporaryUrl() }}" class="mt-2 h-24 rounded-lg object-cover" alt="">@endif
            </x-ui.field>
            @if ($categoryId)
                <label class="flex items-center gap-2 text-sm"><input wire:model="categoryForm.remove_image" type="checkbox" class="rounded border-gray-300"> Remove current image</label>
            @endif
            <div class="grid grid-cols-2 gap-4">
                <x-ui.field label="Sort order" name="categoryForm.sort_order"><input wire:model="categoryForm.sort_order" type="number" min="0" class="input"></x-ui.field>
                <label class="flex items-end gap-2 pb-2 text-sm"><input wire:model="categoryForm.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600"> Visible to members</label>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" wire:click="$set('showCategory', false)" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary" wire:loading.attr="disabled">Save</button>
            </div>
        </form>
    </x-ui.modal>

    {{-- Import default library modal --}}
    @if ($canManage)
        <x-ui.modal wire-model="showImport" :title="'Import default '.strtolower($planType->pluralLabel())" max-width="2xl">
            <p class="text-sm text-gray-600">
                Ready-made {{ strtolower($planType->pluralLabel()) }} prepared by Gymai, based on
                {{ $planType === \App\Enums\FitnessPlanType::Diet ? 'ICMR-NIN Dietary Guidelines for Indians (2024), ISSN protein recommendations and ICMR-INDIAB research' : 'ACSM resistance-training guidance and WHO physical activity guidelines' }}.
                Everything is editable after import, and you can add your own photos.
            </p>
            <p class="mt-1 text-xs text-gray-500">Library updated {{ $libraryVersion }} · importing again only adds what's missing, never duplicates.</p>

            @error('importKeys') <p class="mt-3 rounded-lg bg-red-50 p-2 text-sm text-red-700">{{ $message }}</p> @enderror

            <div class="mt-4 max-h-[22rem] space-y-2 overflow-y-auto pr-1">
                @foreach ($libraryPreview as $cat)
                    @php($done = $cat['imported'] >= $cat['plans'])
                    <label wire:key="lib-{{ $cat['key'] }}" class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 {{ $done ? 'border-gray-100 bg-gray-50' : 'border-gray-200 hover:border-indigo-300' }}">
                        <input type="checkbox" wire:model="importKeys" value="{{ $cat['key'] }}" class="mt-1 rounded border-gray-300 text-indigo-600">
                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-center gap-2">
                                <span class="font-medium text-gray-900">{{ $cat['name'] }}</span>
                                <x-ui.badge>{{ $cat['plans'] }} {{ $cat['plans'] === 1 ? 'plan' : 'plans' }}</x-ui.badge>
                                @if ($cat['photos'] > 0)<x-ui.badge color="purple"><span class="inline-flex items-center gap-1"><x-ui.icon name="camera" class="size-3.5" /> {{ $cat['photos'] }} photos</span></x-ui.badge>@endif
                                @if ($done)
                                    <x-ui.badge color="green">Imported{{ $cat['photos'] ? ' — re-import adds missing photos' : '' }}</x-ui.badge>
                                @elseif ($cat['imported'] > 0)
                                    <x-ui.badge color="amber">{{ $cat['imported'] }} already imported</x-ui.badge>
                                @endif
                            </span>
                            @if ($cat['description'])<span class="mt-0.5 block text-xs text-gray-500">{{ $cat['description'] }}</span>@endif
                            <span class="mt-1 block text-xs text-gray-600">{{ implode(' · ', $cat['titles']) }}</span>
                        </span>
                    </label>
                @endforeach
            </div>

            <label class="mt-4 flex items-start gap-2 text-sm">
                <input type="checkbox" wire:model="importPublish" class="mt-0.5 rounded border-gray-300 text-indigo-600">
                <span>Publish immediately so members can see them
                    <span class="block text-xs text-gray-500">Untick to import as drafts, review them, then publish.</span>
                </span>
            </label>

            <div class="mt-5 flex justify-end gap-2">
                <button type="button" wire:click="$set('showImport', false)" class="btn-secondary">Cancel</button>
                <button type="button" wire:click="runImport" class="btn-primary" wire:loading.attr="disabled" wire:target="runImport">
                    <span wire:loading.remove wire:target="runImport">Import selected</span>
                    <span wire:loading wire:target="runImport">Importing…</span>
                </button>
            </div>
            <p class="mt-3 text-xs text-gray-400">General fitness and nutrition guidance, not medical advice. Members with medical conditions should consult their doctor.</p>
        </x-ui.modal>
    @endif

    {{-- Send to member modal --}}
    <x-ui.modal wire-model="showSend" :title="$copyPlanId ? 'Copy to a member' : 'Custom '.strtolower($planType->label()).' for a member'">
        <p class="mb-3 text-sm text-gray-600">
            {{ $copyPlanId ? 'A personal copy is created that you can edit. The member sees it as their customised plan, next to the default plans.' : 'Pick the member. Only they will see this plan, alongside the default plans.' }}
        </p>
        <input wire:model.live.debounce.250ms="memberSearch" type="search" placeholder="Search member by name, phone or ID…" class="input" autofocus>
        <ul class="mt-3 max-h-72 divide-y divide-gray-100 overflow-y-auto rounded-lg border border-gray-200">
            @forelse ($memberResults as $m)
                <li wire:key="pick-{{ $m->id }}">
                    <button wire:click="sendTo({{ $m->id }})" class="flex w-full items-center gap-3 px-3 py-2 text-left hover:bg-gray-50">
                        <img src="{{ $m->photoUrl() }}" alt="" class="size-8 rounded-full object-cover">
                        <span class="flex-1 text-sm"><span class="font-medium">{{ $m->name }}</span> <span class="text-xs text-gray-500">{{ $m->member_code }} · {{ $m->phone }}</span></span>
                        <span class="inline-flex items-center gap-1 text-xs text-indigo-600">Select <x-ui.icon name="arrow-right" class="size-3.5" /></span>
                    </button>
                </li>
            @empty
                <li class="px-3 py-4 text-center text-sm text-gray-500">{{ $memberSearch === '' ? 'Start typing to find a member.' : 'No matching members.' }}</li>
            @endforelse
        </ul>
    </x-ui.modal>
</div>
