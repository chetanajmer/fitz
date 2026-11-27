<div>
    @if ($submitted)
        <div class="py-8 text-center">
            <div class="gp-pop mx-auto flex size-16 items-center justify-center rounded-full bg-brand-600 text-white shadow-[0_0_36px_rgb(255_107_0/0.55)] ring-8 ring-brand-600/15"><x-ui.icon name="check" class="size-8" /></div>
            <h3 class="mt-4 text-lg font-semibold">Thank you{{ $name ? ', '.\Illuminate\Support\Str::of($name)->before(' ') : '' }}!</h3>
            <p class="mt-1 text-sm text-gray-600">{{ $gym->name }} has received your details and will contact you shortly.</p>
        </div>
    @else
        <form class="space-y-4"
            @if ($recaptchaSiteKey)
                x-data="{
                    busy: false,
                    captchaError: '',
                    siteKey: @js($recaptchaSiteKey),
                    loadRecaptcha() {
                        return window.enquiryRecaptchaReady ??= new Promise((resolve, reject) => {
                            const script = document.createElement('script');
                            script.src = 'https://www.google.com/recaptcha/api.js?render=' + encodeURIComponent(this.siteKey);
                            script.async = true;
                            script.onload = () => grecaptcha.ready(resolve);
                            script.onerror = () => { window.enquiryRecaptchaReady = null; script.remove(); reject(); };
                            document.head.appendChild(script);
                        });
                    },
                    init() { this.loadRecaptcha().catch(() => {}); },
                    async send() {
                        if (this.busy) { return; }
                        this.busy = true;
                        this.captchaError = '';
                        try {
                            await this.loadRecaptcha();
                            const token = await Promise.race([
                                grecaptcha.execute(this.siteKey, { action: @js($captchaAction) }),
                                new Promise((resolve, reject) => setTimeout(() => reject(new Error('reCAPTCHA timed out')), 15000)),
                            ]);
                            this.$refs.recaptchaToken.value = token;
                            $wire.captchaToken = token;
                        } catch (error) {
                            console.error('reCAPTCHA could not produce a token.', error);
                            this.captchaError = 'Security check could not load. Check your connection or turn off ad-blockers, then try again.';
                            this.busy = false;
                            return;
                        }
                        try { await $wire.submit(); } finally { this.busy = false; }
                    },
                }"
                x-on:submit.prevent="send"
            @else
                wire:submit="submit"
            @endif
        >
            <x-ui.field label="Full name" name="name" required><input wire:model="name" class="input h-12 rounded-xl" autocomplete="name"></x-ui.field>
            <x-ui.field label="Mobile number" name="phone" required><input wire:model="phone" type="tel" class="input h-12 rounded-xl" autocomplete="tel"></x-ui.field>
            <x-ui.field label="Email" name="email"><input wire:model="email" type="email" class="input h-12 rounded-xl" autocomplete="email"></x-ui.field>
            <x-ui.field label="Fitness goal" name="fitness_goal">
                <select wire:model="fitness_goal" class="input h-12 rounded-xl">
                    <option value="">Select…</option>
                    @foreach (['Weight loss', 'Muscle gain', 'General fitness', 'Strength training', 'Flexibility & mobility', 'Sports performance'] as $goal)<option>{{ $goal }}</option>@endforeach
                </select>
            </x-ui.field>
            @if ($plans->isNotEmpty())
                <x-ui.field label="Interested plan" name="interested_plan_id">
                    <select wire:model="interested_plan_id" class="input h-12 rounded-xl"><option value="">Not sure yet</option>@foreach ($plans as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select>
                </x-ui.field>
            @endif
            <div class="hidden" aria-hidden="true"><input wire:model="website" tabindex="-1" autocomplete="off"></div>
            @if ($recaptchaSiteKey)
                <input type="hidden" name="recaptcha_token" id="recaptcha_token" x-ref="recaptchaToken" wire:ignore>
                <button type="submit" class="btn-primary h-12 w-full rounded-full text-base" x-bind:disabled="busy">
                    <span x-show="! busy">Send enquiry</span>
                    <span x-show="busy" x-cloak>Sending…</span>
                </button>
                <p x-show="captchaError" x-text="captchaError" x-cloak class="text-center text-sm text-red-500" role="alert"></p>
                @error('captchaToken')<p class="text-center text-sm text-red-500" role="alert">{{ $message }}</p>@enderror
            @else
                <button type="submit" class="btn-primary h-12 w-full rounded-full text-base" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="submit">Send enquiry</span>
                    <span wire:loading wire:target="submit">Sending…</span>
                </button>
            @endif
            <p class="text-center text-xs text-gray-500">Your details are shared only with {{ $gym->name }}.</p>
        </form>
    @endif
</div>
