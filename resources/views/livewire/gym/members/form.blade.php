<div>
    <x-ui.page-header :title="$member ? 'Edit member' : 'Add member'" :subtitle="$member ? $member->name.' · '.$member->member_code : 'Create a new member profile.'">
        <x-slot:actions>
            <a href="{{ $member ? route('gym.members.show', $member) : route('gym.members.index') }}" class="btn-secondary">Cancel</a>
        </x-slot:actions>
    </x-ui.page-header>

    <form wire:submit="save" class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Personal details</h2></div>
                <div class="card-body grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Full name" name="form.name" required><input wire:model="form.name" class="input"></x-ui.field>
                    <x-ui.field label="Phone" name="form.phone" required><input wire:model="form.phone" class="input"></x-ui.field>
                    <x-ui.field label="Email" name="form.email"><input wire:model="form.email" type="email" class="input"></x-ui.field>
                    <x-ui.field label="Gender" name="form.gender">
                        <select wire:model="form.gender" class="input">
                            <option value="">—</option><option value="male">Male</option><option value="female">Female</option><option value="other">Other</option>
                        </select>
                    </x-ui.field>
                    <x-ui.field label="Date of birth" name="form.date_of_birth"><input wire:model="form.date_of_birth" type="date" class="input"></x-ui.field>
                    <x-ui.field label="Blood group" name="form.blood_group">
                        <select wire:model="form.blood_group" class="input">
                            <option value="">—</option>
                            @foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg)<option>{{ $bg }}</option>@endforeach
                        </select>
                    </x-ui.field>
                    <x-ui.field label="Height (cm)" name="form.height_cm" hint="Used for BMI in weight tracking."><input wire:model="form.height_cm" type="number" step="0.1" min="50" max="250" class="input"></x-ui.field>
                    <x-ui.field label="Address" name="form.address" class="sm:col-span-2"><input wire:model="form.address" class="input"></x-ui.field>
                    <x-ui.field label="City" name="form.city"><input wire:model="form.city" class="input"></x-ui.field>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Emergency contact</h2></div>
                <div class="card-body grid gap-4 sm:grid-cols-3">
                    <x-ui.field label="Name" name="form.emergency_contact_name"><input wire:model="form.emergency_contact_name" class="input"></x-ui.field>
                    <x-ui.field label="Phone" name="form.emergency_contact_phone"><input wire:model="form.emergency_contact_phone" class="input"></x-ui.field>
                    <x-ui.field label="Relation" name="form.emergency_contact_relation"><input wire:model="form.emergency_contact_relation" class="input" placeholder="e.g. Spouse"></x-ui.field>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Fitness</h2></div>
                <div class="card-body grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Joining date" name="form.joining_date" required><input wire:model="form.joining_date" type="date" class="input"></x-ui.field>
                    <x-ui.field label="Fitness goal" name="form.fitness_goal"><input wire:model="form.fitness_goal" class="input" placeholder="Weight loss, muscle gain…"></x-ui.field>
                    <x-ui.field label="Diet preference" name="form.diet_preference" hint="Used to suggest diet programs.">
                        <select wire:model="form.diet_preference" class="input">
                            <option value="">—</option>
                            @foreach (\App\Enums\DietPreference::options() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                    </x-ui.field>
                    <x-ui.field label="Medical notes" name="form.medical_notes" class="sm:col-span-2"><textarea wire:model="form.medical_notes" rows="2" class="input" placeholder="Injuries, conditions, allergies"></textarea></x-ui.field>
                    <x-ui.field label="Internal notes" name="form.notes" class="sm:col-span-2"><textarea wire:model="form.notes" rows="2" class="input"></textarea></x-ui.field>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="card card-body space-y-4">
                <div class="flex items-center gap-4">
                    @if ($photo)
                        <img src="{{ $photo->temporaryUrl() }}" class="size-20 rounded-full object-cover" alt="">
                    @elseif ($member)
                        <img src="{{ $member->photoUrl() }}" class="size-20 rounded-full object-cover" alt="">
                    @else
                        <div class="flex size-20 items-center justify-center rounded-full bg-gray-100 text-gray-400"><x-ui.icon name="user" class="size-8" /></div>
                    @endif
                    <x-ui.field label="Profile photo" name="photo" class="flex-1">
                        <input wire:model="photo" type="file" accept="image/*" class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-indigo-700">
                        <div wire:loading wire:target="photo" class="hint">Uploading…</div>
                    </x-ui.field>
                </div>
                <x-ui.field label="Status" name="form.status">
                    <select wire:model="form.status" class="input"><option value="active">Active</option><option value="inactive">Inactive</option></select>
                </x-ui.field>
                @if ($canAssignTrainer)
                    <x-ui.field label="Assigned trainer" name="trainer_id">
                        <select wire:model="trainer_id" class="input">
                            <option value="">No trainer</option>
                            @foreach ($trainers as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
                        </select>
                    </x-ui.field>
                @elseif (! $member)
                    <p class="hint">This member will be assigned to you.</p>
                @endif
            </div>

            <div class="card card-body">
                <x-ui.field :label="$member?->user_id ? 'Reset portal password' : 'Member portal password'" name="portal_password"
                            :hint="$member?->user_id ? 'Leave blank to keep the current password.' : 'Optional. Lets the member log in with their email to see membership, payments and attendance.'">
                    <input wire:model="portal_password" type="password" class="input" autocomplete="new-password">
                </x-ui.field>
            </div>

            <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">{{ $member ? 'Save changes' : 'Create member' }}</button>
        </div>
    </form>
</div>
