<div>
    @if ($submitted)
        <div class="py-8 text-center">
            <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-600"><x-ui.icon name="check" class="size-7" /></div>
            <h3 class="mt-4 text-lg font-semibold">Thank you{{ $name ? ', '.\Illuminate\Support\Str::of($name)->before(' ') : '' }}!</h3>
            <p class="mt-1 text-sm text-gray-600">{{ $gym->name }} has received your details and will contact you shortly.</p>
        </div>
    @else
        <form wire:submit="submit" class="space-y-4">
            <x-ui.field label="Full name" name="name" required><input wire:model="name" class="input" autocomplete="name"></x-ui.field>
            <x-ui.field label="Mobile number" name="phone" required><input wire:model="phone" type="tel" class="input" autocomplete="tel"></x-ui.field>
            <x-ui.field label="Email" name="email"><input wire:model="email" type="email" class="input" autocomplete="email"></x-ui.field>
            <x-ui.field label="Fitness goal" name="fitness_goal">
                <select wire:model="fitness_goal" class="input">
                    <option value="">Select…</option>
                    @foreach (['Weight loss', 'Muscle gain', 'General fitness', 'Strength training', 'Flexibility & mobility', 'Sports performance'] as $goal)<option>{{ $goal }}</option>@endforeach
                </select>
            </x-ui.field>
            @if ($plans->isNotEmpty())
                <x-ui.field label="Interested plan" name="interested_plan_id">
                    <select wire:model="interested_plan_id" class="input"><option value="">Not sure yet</option>@foreach ($plans as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select>
                </x-ui.field>
            @endif
            <div class="hidden" aria-hidden="true"><input wire:model="website" tabindex="-1" autocomplete="off"></div>
            <button type="submit" class="btn w-full text-white" style="background: {{ $gym->primaryColor() }}" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="submit">Send enquiry</span>
                <span wire:loading wire:target="submit">Sending…</span>
            </button>
            <p class="text-center text-xs text-gray-500">Your details are shared only with {{ $gym->name }}.</p>
        </form>
    @endif
</div>
