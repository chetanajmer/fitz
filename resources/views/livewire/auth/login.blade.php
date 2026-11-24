<div class="card card-body sm:p-8">
    <h1 class="text-xl font-semibold">Welcome back</h1>
    <p class="mt-1 text-sm text-gray-500">Sign in to your Gymai account.</p>

    <div class="mt-6 grid grid-cols-2 gap-1 rounded-lg bg-gray-100 p-1 text-sm font-medium">
        <button type="button" wire:click="$set('mode', 'password')" class="rounded-md px-3 py-2 {{ $mode === 'password' ? 'bg-white shadow-sm' : 'text-gray-500' }}">Password</button>
        <button type="button" wire:click="$set('mode', 'otp')" class="rounded-md px-3 py-2 {{ $mode === 'otp' ? 'bg-white shadow-sm' : 'text-gray-500' }}">OTP</button>
    </div>

    @if ($mode === 'password')
        <form wire:submit="login" class="mt-6 space-y-4">
            <x-ui.field label="Email or mobile number" name="email" hint="Gym staff can use their verified mobile number.">
                <input wire:model="email" id="email" type="text" autocomplete="username" autofocus class="input">
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
    @elseif (! $codeSent)
        <form wire:submit="sendCode" class="mt-6 space-y-4">
            <x-ui.field label="Email or mobile number" name="identifier" hint="We send a one-time code to your email or by SMS to your mobile.">
                <input wire:model="identifier" id="identifier" type="text" autocomplete="username" autofocus class="input">
            </x-ui.field>
            @if (count($accounts) > 1)
                <x-ui.field label="Sign in to" name="account">
                    <div class="space-y-2">
                        @foreach ($accounts as $a)
                            <label wire:key="acc-{{ $a['key'] }}" class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm">
                                <input wire:model="account" type="radio" value="{{ $a['key'] }}" class="border-gray-300 text-indigo-600"> {{ $a['label'] }}
                            </label>
                        @endforeach
                    </div>
                </x-ui.field>
            @endif
            <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="sendCode">Send code</span>
                <span wire:loading wire:target="sendCode">Sending…</span>
            </button>
        </form>
    @else
        <form wire:submit="verifyCode" class="mt-6 space-y-4">
            <p class="text-sm text-gray-600">We sent a code to <strong>{{ $this->sentTo() }}</strong>.
                <button type="button" wire:click="changeIdentifier" class="font-medium text-indigo-600 hover:underline">Change</button></p>
            <x-ui.field label="Code" name="code">
                <input wire:model="code" id="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="{{ config('gymai.otp.length', 6) }}" autofocus class="input tracking-widest">
            </x-ui.field>
            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input wire:model="remember" type="checkbox" class="rounded border-gray-300 text-indigo-600"> Remember me
            </label>
            <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="verifyCode">Verify & sign in</span>
                <span wire:loading wire:target="verifyCode">Checking…</span>
            </button>
            <button type="button" wire:click="sendCode" class="w-full text-center text-sm text-gray-500 hover:underline">Resend code</button>
        </form>
    @endif

    <p class="mt-6 text-center text-sm text-gray-500">
        Own a gym? <a href="{{ route('register.gym') }}" class="font-medium text-indigo-600 hover:underline">Register it free</a>
    </p>
    <p class="mt-2 text-center text-sm text-gray-500">
        Gym member? <a href="{{ route('member.join') }}" class="font-medium text-indigo-600 hover:underline">Join your gym</a>
    </p>
</div>
