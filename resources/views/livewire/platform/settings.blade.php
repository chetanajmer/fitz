<div>
    <x-ui.page-header title="Platform settings" subtitle="Company details printed on SaaS invoices and global switches." />

    <form wire:submit="save" class="card max-w-3xl">
        <div class="card-body grid gap-4 sm:grid-cols-2">
            <x-ui.field label="Platform name" name="settings.platform_name" required><input wire:model="settings.platform_name" class="input"></x-ui.field>
            <x-ui.field label="Support email" name="settings.support_email" required><input wire:model="settings.support_email" type="email" class="input"></x-ui.field>
            <x-ui.field label="Support phone" name="settings.support_phone"><input wire:model="settings.support_phone" class="input"></x-ui.field>
            <x-ui.field label="GST number" name="settings.gst_number"><input wire:model="settings.gst_number" class="input"></x-ui.field>
            <x-ui.field label="Company address" name="settings.company_address" class="sm:col-span-2"><textarea wire:model="settings.company_address" rows="2" class="input"></textarea></x-ui.field>
            <x-ui.field label="Invoice number prefix" name="settings.invoice_prefix" required><input wire:model="settings.invoice_prefix" class="input uppercase"></x-ui.field>
            <x-ui.field label="Invoice due in (days)" name="settings.invoice_due_days" required><input wire:model="settings.invoice_due_days" type="number" min="1" class="input"></x-ui.field>
            <x-ui.field label="New gym registrations" name="settings.allow_registrations" class="sm:col-span-2">
                <select wire:model="settings.allow_registrations" class="input"><option value="1">Open — anyone can register a gym</option><option value="0">Closed</option></select>
            </x-ui.field>
        </div>

        <div class="border-t border-gray-100 px-5 pt-5">
            <h2 class="card-title">Member mobile app</h2>
            <p class="hint">Used by <code>GET /api/v1/app/version</code>. Raise <strong>latest</strong> after publishing a new build; raise <strong>minimum</strong> to force older apps to update.</p>
        </div>
        <div class="card-body grid gap-4 sm:grid-cols-2">
            @foreach (['android' => 'Android (Play Store)', 'ios' => 'iOS (App Store)'] as $p => $label)
                <div wire:key="app-{{ $p }}" class="space-y-3 rounded-lg border border-gray-200 p-4">
                    <p class="text-sm font-semibold">{{ $label }}</p>
                    <div class="grid grid-cols-2 gap-3">
                        <x-ui.field label="Latest version" :name="'settings.'.$p.'_latest_version'"><input wire:model="settings.{{ $p }}_latest_version" class="input font-mono" placeholder="1.0.0"></x-ui.field>
                        <x-ui.field label="Minimum version" :name="'settings.'.$p.'_min_version'"><input wire:model="settings.{{ $p }}_min_version" class="input font-mono" placeholder="1.0.0"></x-ui.field>
                    </div>
                    <x-ui.field label="Store URL" :name="'settings.'.$p.'_store_url'"><input wire:model="settings.{{ $p }}_store_url" class="input"></x-ui.field>
                    <x-ui.field label="Release notes" :name="'settings.'.$p.'_release_notes'"><textarea wire:model="settings.{{ $p }}_release_notes" rows="2" class="input"></textarea></x-ui.field>
                </div>
            @endforeach
            <x-ui.field label="App maintenance mode" name="settings.app_maintenance_mode">
                <select wire:model="settings.app_maintenance_mode" class="input"><option value="0">Off</option><option value="1">On — app shows maintenance message</option></select>
            </x-ui.field>
            <x-ui.field label="Maintenance message" name="settings.app_maintenance_message"><input wire:model="settings.app_maintenance_message" class="input"></x-ui.field>
        </div>
        <div class="flex justify-end border-t border-gray-100 px-5 py-4"><button type="submit" class="btn-primary">Save settings</button></div>
    </form>
</div>
