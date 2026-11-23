@props(['title' => null, 'wide' => false])
<x-layouts.base :title="$title" body-class="bg-gradient-to-br from-indigo-50 via-white to-sky-50">
    <div class="flex min-h-full flex-col items-center justify-center px-4 py-10">
        <a href="{{ route('home') }}" class="mb-6 flex items-center gap-2">
            <div class="flex size-10 items-center justify-center rounded-xl bg-indigo-600 text-lg font-bold text-white">G</div>
            <span class="text-xl font-semibold tracking-tight">Gymai</span>
        </a>
        <div class="w-full {{ $wide ? 'max-w-2xl' : 'max-w-md' }}">
            {{ $slot }}
        </div>
    </div>
</x-layouts.base>
