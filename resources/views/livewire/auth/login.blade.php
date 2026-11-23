<div class="card card-body sm:p-8">
    <h1 class="text-xl font-semibold">Welcome back</h1>
    <p class="mt-1 text-sm text-gray-500">Sign in to your Gymai account.</p>

    <form wire:submit="login" class="mt-6 space-y-4">
        <x-ui.field label="Email" name="email">
            <input wire:model="email" id="email" type="email" autocomplete="email" autofocus class="input">
        </x-ui.field>
        <x-ui.field label="Password" name="password">
            <input wire:model="password" id="password" type="password" autocomplete="current-password" class="input">
        </x-ui.field>
        <label class="flex items-center gap-2 text-sm text-gray-600">
            <input wire:model="remember" type="checkbox" class="rounded border-gray-300 text-indigo-600"> Remember me
        </label>
        <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="login">Sign in</span>
            <span wire:loading wire:target="login">Signing in…</span>
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-gray-500">
        Own a gym? <a href="{{ route('register.gym') }}" class="font-medium text-indigo-600 hover:underline">Register it free</a>
    </p>
    <p class="mt-2 text-center text-sm text-gray-500">
        Gym member? <a href="{{ route('member.join') }}" class="font-medium text-indigo-600 hover:underline">Join your gym</a>
    </p>
</div>
