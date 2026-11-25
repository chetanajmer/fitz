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

        @unless (auth()->user()->isSuperAdmin() || auth()->user()->isMember())
            <div class="card">
                <div class="card-header"><h2 class="card-title">Sign in with mobile</h2></div>
                <div class="card-body space-y-4">
                    @if (auth()->user()->login_phone)
                        <p class="text-sm text-gray-600">You can sign in with <strong>{{ auth()->user()->login_phone }}</strong> and your password, or with a one-time code (OTP tab).</p>
                        <button type="button" wire:click="removeLoginPhone" wire:confirm="Stop signing in with this mobile number?" class="btn-secondary btn-sm">Remove</button>
                        <p class="hint">To change it, verify a new number below.</p>
                    @else
                        <p class="text-sm text-gray-600">Verify your mobile number to sign in with it instead of your email.</p>
                    @endif

                    @if (! $phoneCodeSent)
                        <form wire:submit="sendPhoneCode" class="flex flex-wrap items-start gap-2">
                            <x-ui.field name="loginPhone" class="flex-1"><input wire:model="loginPhone" type="tel" inputmode="numeric" placeholder="98765 43210" class="input"></x-ui.field>
                            <button class="btn-primary">Send OTP</button>
                        </form>
                    @else
                        <form wire:submit="verifyPhoneCode" class="flex flex-wrap items-start gap-2">
                            <x-ui.field name="phoneCode" class="flex-1"><input wire:model="phoneCode" inputmode="numeric" autocomplete="one-time-code" placeholder="OTP sent to {{ $loginPhone }}" class="input"></x-ui.field>
                            <button class="btn-primary">Verify</button>
                            <button type="button" wire:click="$set('phoneCodeSent', false)" class="btn-secondary">Change number</button>
                        </form>
                    @endif
                </div>
            </div>
        @endunless

        @if (auth()->user()->isMember() && ($supportGym = current_gym()))
            @php($whatsapp = $supportGym->setting('social.whatsapp'))
            <div class="card">
                <div class="card-header"><h2 class="card-title">Help &amp; support</h2><x-ui.icon name="phone" class="size-5 text-gray-400" /></div>
                <div class="card-body space-y-3 text-sm">
                    <p class="text-gray-600">Questions about your membership, payments or plans? Contact {{ $supportGym->name }}.</p>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @if ($supportGym->phone)
                            <a href="tel:{{ $supportGym->phone }}" class="flex items-center gap-3 rounded-xl border border-gray-200 px-3 py-2.5 transition hover:border-brand-600 dark:border-ink-700"><x-ui.icon name="phone" class="size-4 text-brand-600" /> {{ $supportGym->phone }}</a>
                        @endif
                        @if ($whatsapp)
                            <a href="https://wa.me/{{ preg_replace('/\D/', '', $whatsapp) }}" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-xl border border-gray-200 px-3 py-2.5 transition hover:border-brand-600 dark:border-ink-700"><x-ui.icon name="inbox" class="size-4 text-brand-600" /> WhatsApp</a>
                        @endif
                        @if ($supportGym->email)
                            <a href="mailto:{{ $supportGym->email }}" class="flex min-w-0 items-center gap-3 rounded-xl border border-gray-200 px-3 py-2.5 transition hover:border-brand-600 dark:border-ink-700"><x-ui.icon name="mail" class="size-4 shrink-0 text-brand-600" /> <span class="truncate">{{ $supportGym->email }}</span></a>
                        @endif
                        <a href="{{ $supportGym->directionsUrl() }}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-3 rounded-xl border border-gray-200 px-3 py-2.5 transition hover:border-brand-600 dark:border-ink-700"><x-ui.icon name="map-pin" class="size-4 text-brand-600" /> Directions</a>
                    </div>
                    <a href="{{ route('portal.notifications') }}" class="inline-flex items-center gap-1 font-medium text-brand-600 hover:underline">Notification settings <x-ui.icon name="arrow-right" class="size-4" /></a>
                </div>
            </div>
        @endif
    </div>
</div>
