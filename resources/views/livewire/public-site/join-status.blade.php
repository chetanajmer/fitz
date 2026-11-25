<div class="card card-body sm:p-8">
    <h1 class="text-xl font-semibold">Registration status</h1>
    <p class="mt-1 text-sm text-gray-500">See whether your gym has approved your registration.</p>

    @if ($step === 1)
        <form wire:submit="sendOtp" class="mt-6 space-y-4">
            <x-ui.field label="Mobile number you signed up with" name="phone" required hint="We'll send an OTP to confirm it's you.">
                <input wire:model="phone" type="tel" class="input" autocomplete="tel" autofocus>
            </x-ui.field>
            <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="sendOtp">Send OTP</span>
                <span wire:loading wire:target="sendOtp">Sending OTP…</span>
            </button>
        </form>
    @endif

    @if ($step === 2)
        <form wire:submit="verify" class="mt-6 space-y-4">
            <p class="text-sm text-gray-500">Enter the {{ (int) config('gymai.otp.length', 6) }}-digit OTP sent to {{ \App\Services\MemberOtpService::maskPhone($phone) }}.</p>
            <x-ui.field label="OTP" name="otp" required>
                <input wire:model="otp" inputmode="numeric" autocomplete="one-time-code" maxlength="{{ (int) config('gymai.otp.length', 6) }}" class="input text-center text-lg tracking-[0.5em]">
            </x-ui.field>
            <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="verify">Show my status</span>
                <span wire:loading wire:target="verify">Checking…</span>
            </button>
            <div class="flex justify-between text-sm">
                <button type="button" wire:click="startOver" class="text-gray-500 hover:text-brand-600">← Change number</button>
                <button type="button" wire:click="sendOtp" class="text-gray-500 hover:text-brand-600">Resend OTP</button>
            </div>
        </form>
    @endif

    @if ($step === 3)
        <div class="mt-6 space-y-3">
            @forelse ($registrations as $i => $r)
                <div wire:key="reg-{{ $i }}" class="rounded-lg border border-gray-200 p-4 dark:border-ink-700">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="font-medium">{{ $r['gym'] }}</p>
                            <p class="text-xs text-gray-500">Member ID {{ $r['member_code'] }} · {{ $r['date']->format('d M Y') }}</p>
                        </div>
                    </div>
                    <x-ui.registration-status :status="$r['status']" class="mt-3" />
                </div>
            @empty
                <p class="py-4 text-center text-sm text-gray-500">We couldn't find a registration for {{ \App\Services\MemberOtpService::maskPhone((string) $verifiedPhone) }}.</p>
                <a href="{{ route('member.join') }}" class="btn-primary w-full">Join a gym</a>
            @endforelse
            <button type="button" wire:click="startOver" class="w-full text-sm text-gray-500 hover:text-brand-600">Check another number</button>
        </div>
    @endif
</div>
