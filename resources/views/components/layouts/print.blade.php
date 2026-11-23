@props(['title' => null, 'back' => null])
<x-layouts.base :title="$title" body-class="bg-gray-100 print:bg-white">
    <div class="mx-auto max-w-3xl px-4 py-6 print:max-w-none print:p-0">
        <div class="mb-4 flex items-center justify-between no-print">
            <a href="{{ $back ?? url()->previous() }}" class="btn-secondary btn-sm"><x-ui.icon name="arrow-left" class="size-4" /> Back</a>
            <button onclick="window.print()" class="btn-primary btn-sm"><x-ui.icon name="printer" class="size-4" /> Print / Save PDF</button>
        </div>
        <div class="rounded-xl bg-white p-8 shadow-sm print:rounded-none print:p-0 print:shadow-none">
            {{ $slot }}
        </div>
    </div>
</x-layouts.base>
