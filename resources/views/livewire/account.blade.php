<div>
    <x-ui.page-header title="My account" subtitle="Update your profile and password." />

    <div class="grid gap-6 lg:grid-cols-2">
        <form wire:submit="saveProfile" class="card">
            <div class="card-header"><h2 class="card-title">Profile</h2></div>
            <div class="card-body space-y-4">
                <x-ui.field label="Name" name="name"><input wire:model="name" id="name" class="input"></x-ui.field>
                <x-ui.field label="Email" name="email"><input wire:model="email" id="email" type="email" class="input"></x-ui.field>
                <x-ui.field label="Phone" name="phone"><input wire:model="phone" id="phone" class="input"></x-ui.field>
                <button class="btn-primary">Save profile</button>
            </div>
        </form>

        <form wire:submit="savePassword" class="card">
            <div class="card-header"><h2 class="card-title">Change password</h2></div>
            <div class="card-body space-y-4">
                <x-ui.field label="Current password" name="current_password"><input wire:model="current_password" id="current_password" type="password" class="input" autocomplete="current-password"></x-ui.field>
                <x-ui.field label="New password" name="password"><input wire:model="password" id="password" type="password" class="input" autocomplete="new-password"></x-ui.field>
                <x-ui.field label="Confirm new password" name="password_confirmation"><input wire:model="password_confirmation" id="password_confirmation" type="password" class="input" autocomplete="new-password"></x-ui.field>
                <button class="btn-primary">Update password</button>
            </div>
        </form>
    </div>
</div>
