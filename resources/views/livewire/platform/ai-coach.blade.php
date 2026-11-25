<div>
    <x-ui.page-header title="AI Coach" subtitle="AI progress reports through OpenRouter. Included in the per-member price, so these limits control what AI costs Gymai." />

    @unless ($configured)
        <div class="card card-body mb-6 flex items-start gap-3 border-amber-200 bg-amber-50 text-sm text-amber-800">
            <x-ui.icon name="warning" class="size-5 shrink-0" />
            <p>OPENROUTER_API_KEY is not set in .env: every AI report will fail (no credits are used).</p>
        </div>
    @endunless

    <div class="space-y-6">
        <form wire:submit="save" class="card max-w-3xl">
            <div class="card-header"><h2 class="card-title">Platform settings</h2></div>
            <div class="card-body space-y-4">
                <x-ui.field label="AI coach" name="settings.ai_enabled">
                    <select wire:model="settings.ai_enabled" class="input"><option value="1">On — gyms you allow can use it</option><option value="0">Off for every gym</option></select>
                </x-ui.field>
                <x-ui.field label="OpenRouter model" name="settings.ai_model" hint="Must read images, support structured output and have a zero-data-retention provider; otherwise reports fail.">
                    <input wire:model="settings.ai_model" class="input" placeholder="openai/gpt-6-luna">
                </x-ui.field>
                <div class="grid grid-cols-2 gap-3">
                    <x-ui.field label="Credits: progress report" name="settings.ai_credits_progress"><input wire:model="settings.ai_credits_progress" type="number" min="1" class="input"></x-ui.field>
                    <x-ui.field label="Credits: photo comparison" name="settings.ai_credits_photo_comparison"><input wire:model="settings.ai_credits_photo_comparison" type="number" min="1" class="input"></x-ui.field>
                    <x-ui.field label="New gym: monthly pool" name="settings.ai_default_gym_credits"><input wire:model="settings.ai_default_gym_credits" type="number" min="0" class="input"></x-ui.field>
                    <x-ui.field label="New gym: per member" name="settings.ai_default_member_ceiling"><input wire:model="settings.ai_default_member_ceiling" type="number" min="1" class="input"></x-ui.field>
                </div>
            </div>
            <div class="border-t border-gray-100 px-5 py-4"><button type="submit" class="btn-primary">Save</button></div>
        </form>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Gyms · {{ now()->format('F Y') }}</h2>
                <span class="text-sm text-gray-500">AI cost this month: <strong>${{ number_format($totalCost, 2) }}</strong></span>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Gym</th><th>AI</th><th>Credits used</th><th>Per member</th><th>Photos</th><th>Reports</th><th>Cost</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($gyms as $gym)
                            @php $s = $gymSettings->get($gym->id); $u = $usage->get($gym->id); @endphp
                            <tr wire:key="g-{{ $gym->id }}">
                                <td><span class="font-medium">{{ $gym->name }}</span> <span class="text-gray-500">{{ $gym->city }}</span></td>
                                <td>
                                    @if ($s?->allowed)
                                        <x-ui.badge :color="$s->enabled ? 'green' : 'amber'">{{ $s->enabled ? 'On' : 'Allowed, off at gym' }}</x-ui.badge>
                                    @else
                                        <x-ui.badge color="gray">Not allowed</x-ui.badge>
                                    @endif
                                </td>
                                <td>{{ (int) ($u->credits ?? 0) }} / {{ $s->monthly_credits ?? $settings['ai_default_gym_credits'] }}</td>
                                <td>{{ $s?->memberMonthlyLimit() ?? $settings['ai_default_member_ceiling'] }}</td>
                                <td>{{ ($s?->photos_allowed ?? true) ? 'Yes' : 'No' }}</td>
                                <td>{{ (int) ($u->reports ?? 0) }}</td>
                                <td>${{ number_format((float) ($u->cost_usd ?? 0), 2) }}</td>
                                <td class="text-right"><button wire:click="editGym({{ $gym->id }})" class="btn-secondary btn-sm">Edit limits</button></td>
                            </tr>
                            @if ($editingGymId === $gym->id)
                                <tr wire:key="ge-{{ $gym->id }}">
                                    <td colspan="8" class="bg-gray-50">
                                        <form wire:submit="saveGym" class="flex flex-wrap items-end gap-4">
                                            <label class="flex items-center gap-2 text-sm"><input wire:model="gymForm.allowed" type="checkbox" class="rounded border-gray-300 text-indigo-600"> Allow AI coach</label>
                                            <x-ui.field label="Monthly credits (pool)" name="gymForm.monthly_credits"><input wire:model="gymForm.monthly_credits" type="number" min="0" class="input w-32"></x-ui.field>
                                            <x-ui.field label="Max credits per member" name="gymForm.member_ceiling"><input wire:model="gymForm.member_ceiling" type="number" min="1" class="input w-32"></x-ui.field>
                                            <label class="flex items-center gap-2 text-sm"><input wire:model="gymForm.photos_allowed" type="checkbox" class="rounded border-gray-300 text-indigo-600"> Photo comparison</label>
                                            <button type="submit" class="btn-primary btn-sm">Save</button>
                                            <button type="button" wire:click="$set('editingGymId', null)" class="btn-secondary btn-sm">Cancel</button>
                                        </form>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
