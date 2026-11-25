<div>
    @if ($memberCode)
        <div class="py-8 text-center">
            <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-600"><x-ui.icon name="check" class="size-7" /></div>
            <h3 class="mt-4 text-lg font-semibold">Welcome{{ $name ? ', '.\Illuminate\Support\Str::of($name)->before(' ') : '' }}!</h3>
            <p class="mt-1 text-sm text-gray-600">You are registered with {{ $gym->name }}.</p>
            @if ($memberCode !== '—')
                <p class="mt-4 text-sm text-gray-500">Your member ID</p>
                <p class="text-2xl font-bold tracking-wider">{{ $memberCode }}</p>
            @endif
            <p class="mt-4 text-sm text-gray-600">Please visit the front desk to choose a membership plan and complete payment.</p>
        </div>
    @else
        <form wire:submit="submit" class="space-y-4">
            <x-ui.field label="Full name" name="name" required><input wire:model="name" class="input" autocomplete="name"></x-ui.field>
            <x-ui.field label="Mobile number" name="phone" required><input wire:model="phone" type="tel" class="input" autocomplete="tel"></x-ui.field>
            <x-ui.field label="Email" name="email"><input wire:model="email" type="email" class="input" autocomplete="email"></x-ui.field>
            <div class="grid grid-cols-2 gap-3">
                <x-ui.field label="Gender" name="gender">
                    <select wire:model="gender" class="input">
                        <option value="">Select…</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </select>
                </x-ui.field>
                <x-ui.field label="Date of birth" name="date_of_birth"><input wire:model="date_of_birth" type="date" class="input" max="{{ now()->subDay()->toDateString() }}"></x-ui.field>
            </div>
            <x-ui.field label="Address" name="address"><input wire:model="address" class="input" autocomplete="street-address"></x-ui.field>
            <x-ui.field label="Fitness goal" name="fitness_goal">
                <select wire:model="fitness_goal" class="input">
                    <option value="">Select…</option>
                    @foreach (['Weight loss', 'Muscle gain', 'General fitness', 'Strength training', 'Flexibility & mobility', 'Sports performance'] as $goal)<option>{{ $goal }}</option>@endforeach
                </select>
            </x-ui.field>
            <div class="grid grid-cols-2 gap-3">
                <x-ui.field label="Emergency contact" name="emergency_contact_name"><input wire:model="emergency_contact_name" class="input" placeholder="Name"></x-ui.field>
                <x-ui.field label="Emergency phone" name="emergency_contact_phone"><input wire:model="emergency_contact_phone" type="tel" class="input"></x-ui.field>
            </div>
            <x-ui.field label="Medical conditions / injuries" name="medical_notes"><textarea wire:model="medical_notes" rows="2" class="input" placeholder="Optional"></textarea></x-ui.field>
            <div class="hidden" aria-hidden="true"><input wire:model="website" tabindex="-1" autocomplete="off"></div>
            <button type="submit" class="btn w-full text-white" style="background: {{ $gym->primaryColor() }}" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="submit">Register</span>
                <span wire:loading wire:target="submit">Registering…</span>
            </button>
            <p class="text-center text-xs text-gray-500">Your details are shared only with {{ $gym->name }}.</p>
        </form>
    @endif
</div>
