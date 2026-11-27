<x-layouts.base :title="'Register at '.$gym->name" body-class="bg-gray-50">
    <div class="min-h-full" style="background: linear-gradient(160deg, {{ $gym->primaryColor() }} 0%, {{ $gym->secondaryColor() }} 100%)">
        <div class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4 py-10">
            <div class="mb-6 text-center text-white">
                @if ($gym->logoUrl())
                    <img src="{{ $gym->logoUrl() }}" alt="" class="mx-auto size-20 rounded-2xl bg-white object-contain p-1 shadow-lg">
                @else
                    <div class="mx-auto flex size-20 items-center justify-center rounded-2xl bg-white/20 text-2xl font-bold">{{ $gym->initials() }}</div>
                @endif
                <h1 class="mt-4 text-2xl font-bold">{{ $gym->name }}</h1>
                @if ($gym->setting('tagline'))<p class="mt-1 text-white/80">{{ $gym->setting('tagline') }}</p>@endif
            </div>
            <div class="rounded-2xl bg-white p-6 shadow-xl">
                <h2 class="text-lg font-semibold">Member registration</h2>
                <p class="mb-5 text-sm text-gray-500">Fill in your details to become a member.</p>
                <livewire:public-site.member-registration-form :gym="$gym" />
            </div>
            @if ($showMemberLogin ?? false)
                <a href="{{ route('login') }}" class="mt-4 flex items-center justify-center gap-2 rounded-2xl bg-white/15 px-4 py-3 text-sm font-semibold text-white ring-1 ring-white/30 backdrop-blur transition hover:bg-white/25">
                    <x-ui.icon name="qr" class="size-4" /> Already a member? Log in to check in
                </a>
            @endif
            <a href="{{ $gym->publicUrl() }}" class="mt-6 text-center text-sm text-white/90 hover:underline">View {{ $gym->name }} →</a>
            <p class="mt-4 text-center text-xs text-white/60">Powered by Fitnix</p>
        </div>
    </div>
</x-layouts.base>
