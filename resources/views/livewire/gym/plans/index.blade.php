<div>
    <x-ui.page-header title="Membership plans" subtitle="Monthly, quarterly, half-yearly, yearly or custom-duration plans.">
        <x-slot:actions><button wire:click="create" class="btn-primary"><x-ui.icon name="plus" class="size-4" /> New plan</button></x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($plans as $plan)
            <div wire:key="plan-{{ $plan->id }}" class="card card-body flex flex-col {{ $plan->is_active ? '' : 'opacity-60' }}">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <h3 class="font-semibold text-gray-900">{{ $plan->name }}</h3>
                        <p class="text-sm text-gray-500">{{ $plan->durationLabel() }} · {{ $plan->duration_days }} days{{ $plan->max_freeze_days ? ' · '.$plan->max_freeze_days.' freeze days' : '' }}</p>
                    </div>
                    <div class="flex flex-col items-end gap-1">
                        <x-ui.badge :color="$plan->is_active ? 'green' : 'gray'">{{ $plan->is_active ? 'Active' : 'Disabled' }}</x-ui.badge>
                        @if ($plan->is_public)<x-ui.badge color="indigo">Public</x-ui.badge>@endif
                    </div>
                </div>
                <p class="mt-4 text-3xl font-bold text-gray-900">{{ money($plan->price, false) }}</p>
                @if ($plan->description)<p class="mt-2 text-sm text-gray-600">{{ $plan->description }}</p>@endif
                @if ($plan->features)
                    <ul class="mt-3 space-y-1 text-sm text-gray-600">
                        @foreach ($plan->features as $f)<li class="flex gap-2"><x-ui.icon name="check" class="size-4 text-emerald-500" /> {{ $f }}</li>@endforeach
                    </ul>
                @endif
                <div class="mt-auto flex items-center justify-between border-t border-gray-100 pt-4 text-sm">
                    <span class="text-gray-500">{{ $plan->active_count }} active</span>
                    <div class="flex gap-1">
                        <button wire:click="toggle({{ $plan->id }})" class="btn-ghost btn-sm">{{ $plan->is_active ? 'Disable' : 'Enable' }}</button>
                        <button wire:click="edit({{ $plan->id }})" class="btn-secondary btn-sm">Edit</button>
                        <button wire:click="delete({{ $plan->id }})" wire:confirm="Archive this plan? Members keep their current memberships." class="btn-ghost btn-sm text-red-600">Archive</button>
                    </div>
                </div>
            </div>
        @empty
            <div class="card sm:col-span-2 xl:col-span-3">
                <x-ui.empty message="No plans yet. Create your first membership plan." icon="tag">
                    <button wire:click="create" class="btn-primary btn-sm">Create plan</button>
                </x-ui.empty>
            </div>
        @endforelse
    </div>

    <x-ui.modal wire-model="showForm" :title="$editingId ? 'Edit plan' : 'New plan'">
        <form wire:submit="save" class="space-y-4">
            <x-ui.field label="Plan name" name="form.name" required><input wire:model="form.name" class="input" placeholder="Gold Quarterly"></x-ui.field>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Duration" name="form.duration_type" required>
                    <select wire:model.live="form.duration_type" class="input">@foreach (\App\Enums\PlanDuration::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
                </x-ui.field>
                @if (($form['duration_type'] ?? '') === 'custom')
                    <x-ui.field label="Days" name="form.duration_days" required><input wire:model="form.duration_days" type="number" min="1" class="input"></x-ui.field>
                @endif
                <x-ui.field label="Price (₹)" name="form.price" required><input wire:model="form.price" type="number" min="0" step="0.01" class="input"></x-ui.field>
                <x-ui.field label="Freeze days allowed" name="form.max_freeze_days" hint="Days a member can pause this plan. 0 = no freezing."><input wire:model="form.max_freeze_days" type="number" min="0" max="365" class="input"></x-ui.field>
                <x-ui.field label="Sort order" name="form.sort_order"><input wire:model="form.sort_order" type="number" min="0" class="input"></x-ui.field>
            </div>
            <x-ui.field label="Description" name="form.description"><input wire:model="form.description" class="input"></x-ui.field>
            <x-ui.field label="Features" name="form.features" hint="One per line — shown on your public page."><textarea wire:model="form.features" rows="3" class="input"></textarea></x-ui.field>
            <div class="flex gap-6 text-sm">
                <label class="flex items-center gap-2"><input wire:model="form.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600"> Active</label>
                <label class="flex items-center gap-2"><input wire:model="form.is_public" type="checkbox" class="rounded border-gray-300 text-indigo-600"> Show on public page</label>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" wire:click="$set('showForm', false)" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">Save plan</button>
            </div>
        </form>
    </x-ui.modal>
</div>
