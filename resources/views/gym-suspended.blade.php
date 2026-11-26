@php($gym = auth()->user()->gym)
<x-layouts.guest title="Gym unavailable">
    <div class="card card-body text-center sm:p-8">
        <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-amber-100 text-amber-600"><x-ui.icon name="clock" class="size-7" /></div>
        <h1 class="mt-4 text-xl font-semibold">{{ $gym?->name ?? 'This gym' }} is {{ $gym?->status?->label() ?? 'unavailable' }}</h1>
        <p class="mt-2 text-sm text-gray-600">
            Access to this gym has been paused by the Fitnix platform team.
            @if ($gym?->status_reason)<br>Reason: <strong>{{ $gym->status_reason }}</strong>@endif
        </p>
        <p class="mt-4 text-sm text-gray-600">Please contact <a href="mailto:{{ \App\Models\PlatformSetting::get('support_email') }}" class="text-indigo-600 underline">{{ \App\Models\PlatformSetting::get('support_email') }}</a>.</p>
        @if ($gym?->isOperational())
            <a href="{{ auth()->user()->homeRoute() }}" class="btn-primary mt-6">Go to dashboard</a>
        @endif
        <form method="POST" action="{{ route('logout') }}" class="mt-4">@csrf<button class="btn-secondary">Log out</button></form>
    </div>
</x-layouts.guest>
