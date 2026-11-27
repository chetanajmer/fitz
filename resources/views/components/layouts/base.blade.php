@props(['title' => null, 'bodyClass' => 'bg-gray-50'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name', 'Fitnix') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{ $head ?? '' }}
</head>
<body class="h-full font-sans text-gray-900 antialiased {{ $bodyClass }}">
    @if (auth()->check() && session()->has(\App\Services\ImpersonationService::SESSION_KEY))
        @php($imp = session(\App\Services\ImpersonationService::SESSION_KEY))
        <div class="sticky top-0 z-[70] flex flex-wrap items-center justify-center gap-x-4 gap-y-1 bg-amber-400 px-4 py-2 text-sm font-medium text-amber-950 no-print">
            <span class="inline-flex items-center gap-1.5"><x-ui.icon name="eye" class="size-4" /> Logged in as <strong>{{ auth()->user()->name }}</strong> ({{ str_replace('_', ' ', auth()->user()->getRoleNames()->first()) }}) via Super Admin magic link</span>
            <form method="POST" action="{{ route('impersonate.leave') }}">
                @csrf
                <button class="rounded-md bg-amber-950 px-3 py-1 text-xs font-semibold text-white hover:bg-black">
                    {{ $imp['return_to'] ? 'Return to Super Admin' : 'End session' }}
                </button>
            </form>
        </div>
    @endif
    {{ $slot }}

    <x-ui.confirm-dialog />
    <x-ui.toasts />

</body>
</html>
