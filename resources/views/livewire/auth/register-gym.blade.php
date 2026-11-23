<div class="card card-body sm:p-8">
    <h1 class="text-xl font-semibold">Register your gym</h1>
    <p class="mt-1 text-sm text-gray-500">
        No setup fee. No registration fee. All features included — just <strong>{{ money($price, false) }} per active member / month</strong>.
    </p>

    @unless ($open)
        <div class="mt-6 rounded-lg bg-amber-50 p-4 text-sm text-amber-800">New gym registrations are temporarily closed. Please check back soon.</div>
    @else
        <form wire:submit="register" class="mt-6 space-y-6">
            <fieldset>
                <legend class="mb-3 text-xs font-semibold tracking-wide text-gray-500 uppercase">Gym details</legend>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Gym name" name="gym_name" required class="sm:col-span-2">
                        <input wire:model="gym_name" id="gym_name" class="input" placeholder="Iron Paradise Fitness">
                    </x-ui.field>
                    <x-ui.field label="City" name="city" required>
                        <input wire:model="city" id="city" class="input">
                    </x-ui.field>
                    <x-ui.field label="Gym phone" name="gym_phone">
                        <input wire:model="gym_phone" id="gym_phone" class="input">
                    </x-ui.field>
                    <x-ui.field label="Address" name="address" class="sm:col-span-2">
                        <input wire:model="address" id="address" class="input">
                    </x-ui.field>
                </div>
            </fieldset>

            <fieldset>
                <legend class="mb-3 text-xs font-semibold tracking-wide text-gray-500 uppercase">Owner account</legend>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Your name" name="owner_name" required>
                        <input wire:model="owner_name" id="owner_name" class="input" autocomplete="name">
                    </x-ui.field>
                    <x-ui.field label="Mobile number" name="phone" required>
                        <input wire:model="phone" id="phone" class="input" autocomplete="tel">
                    </x-ui.field>
                    <x-ui.field label="Email" name="email" required class="sm:col-span-2">
                        <input wire:model="email" id="email" type="email" class="input" autocomplete="email">
                    </x-ui.field>
                    <x-ui.field label="Password" name="password" required>
                        <input wire:model="password" id="password" type="password" class="input" autocomplete="new-password">
                    </x-ui.field>
                    <x-ui.field label="Confirm password" name="password_confirmation" required>
                        <input wire:model="password_confirmation" id="password_confirmation" type="password" class="input" autocomplete="new-password">
                    </x-ui.field>
                </div>
            </fieldset>

            <x-ui.field name="terms">
                <label class="flex items-start gap-2 text-sm text-gray-600">
                    <input wire:model="terms" type="checkbox" class="mt-0.5 rounded border-gray-300 text-indigo-600">
                    I agree to be billed {{ money($price, false) }} per active member per month. Enquiries, expired and inactive members are never billed.
                </label>
            </x-ui.field>

            <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="register">Create my gym</span>
                <span wire:loading wire:target="register">Setting up…</span>
            </button>
        </form>
    @endunless

    <p class="mt-6 text-center text-sm text-gray-500">
        Already registered? <a href="{{ route('login') }}" class="font-medium text-indigo-600 hover:underline">Sign in</a>
    </p>
</div>
