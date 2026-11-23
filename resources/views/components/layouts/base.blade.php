@props(['title' => null, 'bodyClass' => 'bg-gray-50'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name', 'Gymai') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{ $head ?? '' }}
</head>
<body class="h-full font-sans text-gray-900 antialiased {{ $bodyClass }}">
    @if (auth()->check() && session()->has(\App\Services\ImpersonationService::SESSION_KEY))
        @php($imp = session(\App\Services\ImpersonationService::SESSION_KEY))
        <div class="sticky top-0 z-[70] flex flex-wrap items-center justify-center gap-x-4 gap-y-1 bg-amber-400 px-4 py-2 text-sm font-medium text-amber-950 no-print">
            <span>👁️ Logged in as <strong>{{ auth()->user()->name }}</strong> ({{ str_replace('_', ' ', auth()->user()->getRoleNames()->first()) }}) via Super Admin magic link</span>
            <form method="POST" action="{{ route('impersonate.leave') }}">
                @csrf
                <button class="rounded-md bg-amber-950 px-3 py-1 text-xs font-semibold text-white hover:bg-black">
                    {{ $imp['return_to'] ? 'Return to Super Admin' : 'End session' }}
                </button>
            </form>
        </div>
    @endif
    {{ $slot }}

    {{-- Toasts: fired by session flashes or Livewire $this->dispatch('notify', message: '...', type: 'success') --}}
    <div x-data="{
            toasts: [],
            add(message, type = 'success') {
                const id = Date.now() + Math.random();
                this.toasts.push({ id, message, type });
                setTimeout(() => this.toasts = this.toasts.filter(t => t.id !== id), 4500);
            }
        }"
        x-init="
            @if (session('success')) add(@js(session('success')), 'success'); @endif
            @if (session('error')) add(@js(session('error')), 'error'); @endif
        "
        @notify.window="add($event.detail.message, $event.detail.type ?? 'success')"
        class="pointer-events-none fixed inset-x-0 bottom-4 z-[60] flex flex-col items-center gap-2 px-4 sm:items-end sm:pr-6 no-print">
        <template x-for="toast in toasts" :key="toast.id">
            <div x-transition.opacity
                 class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-lg px-4 py-3 text-sm font-medium text-white shadow-lg"
                 :class="toast.type === 'error' ? 'bg-red-600' : 'bg-gray-900'">
                <span x-text="toast.type === 'error' ? '⚠' : '✓'"></span>
                <span x-text="toast.message" class="flex-1"></span>
            </div>
        </template>
    </div>
</body>
</html>
