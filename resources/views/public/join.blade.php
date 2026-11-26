<x-layouts.base :title="'Join '.$gym->name" body-class="bg-gray-50">
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
                <h2 class="text-lg font-semibold">Interested in joining?</h2>
                <p class="mb-5 text-sm text-gray-500">Share your details and our team will get in touch.</p>
                <livewire:public-site.enquiry-form :gym="$gym" source="qr" />
            </div>
            <a href="{{ $gym->publicUrl() }}" class="mt-6 text-center text-sm text-white/90 hover:underline"><span class="inline-flex items-center gap-1">View {{ $gym->name }} <x-ui.icon name="arrow-right" class="size-4" /></span></a>
            <p class="mt-4 text-center text-xs text-white/60">Powered by Fitnix</p>
        </div>
    </div>
</x-layouts.base>
