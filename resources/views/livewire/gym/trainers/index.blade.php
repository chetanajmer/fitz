<div>
    <x-ui.page-header title="Trainers" subtitle="Trainers can log in to see and check in only their assigned members.">
        <x-slot:actions><button wire:click="create" class="btn-primary"><x-ui.icon name="plus" class="size-4" /> Add trainer</button></x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($trainers as $t)
            <div wire:key="t-{{ $t->id }}" class="card card-body">
                <div class="flex items-start gap-4">
                    <img src="{{ $t->photoUrl() }}" alt="" class="size-14 rounded-full object-cover">
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('gym.trainers.show', $t->id) }}" class="font-semibold text-gray-900 hover:text-indigo-600">{{ $t->name }}</a>
                        <p class="truncate text-sm text-gray-500">{{ $t->specialization ?: 'Trainer' }}</p>
                        <p class="truncate text-xs text-gray-500">{{ $t->email }} @if($t->phone)· {{ $t->phone }}@endif</p>
                    </div>
                    <x-ui.badge :color="$t->status === 'active' ? 'green' : 'gray'">{{ ucfirst($t->status) }}</x-ui.badge>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <a href="{{ route('gym.trainers.show', $t->id) }}#members" class="group flex items-center gap-2.5 rounded-lg border border-indigo-100 bg-indigo-50 px-3 py-2 transition hover:border-indigo-300 hover:bg-indigo-100">
                        <x-ui.icon name="users" class="size-5 shrink-0 text-indigo-600" />
                        <span class="min-w-0 leading-tight"><span class="block text-sm font-semibold text-indigo-900">{{ $t->assigned_members_count }} {{ Str::plural('member', $t->assigned_members_count) }}</span><span class="block text-xs text-indigo-700/80">Assign members <x-ui.icon name="arrow-right" class="inline size-3" /></span></span>
                    </a>
                    <a href="{{ route('gym.trainers.show', $t->id) }}#access" class="group flex items-center gap-2.5 rounded-lg border border-amber-100 bg-amber-50 px-3 py-2 transition hover:border-amber-300 hover:bg-amber-100">
                        <x-ui.icon name="cog" class="size-5 shrink-0 text-amber-600" />
                        <span class="min-w-0 leading-tight"><span class="block text-sm font-semibold text-amber-900">{{ $t->permissions_count }} {{ Str::plural('section', $t->permissions_count) }}</span><span class="block text-xs text-amber-700/80">Manage access <x-ui.icon name="arrow-right" class="inline size-3" /></span></span>
                    </a>
                </div>
                <div class="mt-3 flex items-center justify-end border-t border-gray-100 pt-3 text-sm">
                    <div class="flex gap-1">
                        <button wire:click="toggleStatus({{ $t->id }})" class="btn-ghost btn-sm">{{ $t->status === 'active' ? 'Deactivate' : 'Activate' }}</button>
                        <button wire:click="edit({{ $t->id }})" class="btn-secondary btn-sm">Edit</button>
                        <button wire:click="delete({{ $t->id }})" data-confirm-title="Remove trainer?" wire:confirm="Remove {{ $t->name }}? Their members will be unassigned." class="btn-ghost btn-sm text-red-600">Remove</button>
                    </div>
                </div>
            </div>
        @empty
            <div class="card sm:col-span-2 xl:col-span-3"><x-ui.empty message="No trainers yet." icon="whistle"><button wire:click="create" class="btn-primary btn-sm">Add trainer</button></x-ui.empty></div>
        @endforelse
    </div>

    <x-ui.modal wire-model="showForm" :title="$editingId ? 'Edit trainer' : 'Add trainer'" max-width="xl">
        <form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
            <x-ui.field label="Name" name="form.name" required><input wire:model="form.name" class="input"></x-ui.field>
            <x-ui.field label="Phone" name="form.phone"><input wire:model="form.phone" class="input"></x-ui.field>
            <x-ui.field label="Login email" name="form.email" required><input wire:model="form.email" type="email" class="input"></x-ui.field>
            <x-ui.field :label="$editingId ? 'New password' : 'Password'" name="form.password" :required="! $editingId" :hint="$editingId ? 'Leave blank to keep current.' : null"><input wire:model="form.password" type="password" class="input" autocomplete="new-password"></x-ui.field>
            <x-ui.field label="Specialization" name="form.specialization"><input wire:model="form.specialization" class="input" placeholder="Strength, Yoga, CrossFit…"></x-ui.field>
            <x-ui.field label="Status" name="form.status"><select wire:model="form.status" class="input"><option value="active">Active</option><option value="inactive">Inactive</option></select></x-ui.field>
            <x-ui.field label="Bio" name="form.bio" class="sm:col-span-2"><textarea wire:model="form.bio" rows="2" class="input"></textarea></x-ui.field>
            <x-ui.field label="Photo" name="photo" class="sm:col-span-2"><input wire:model="photo" type="file" accept="image/*" class="text-sm"></x-ui.field>
            <div class="flex justify-end gap-2 sm:col-span-2">
                <button type="button" wire:click="$set('showForm', false)" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary" wire:loading.attr="disabled">Save</button>
            </div>
        </form>
    </x-ui.modal>
</div>
