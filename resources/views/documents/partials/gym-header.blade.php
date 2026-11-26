<div class="flex items-start justify-between gap-6 border-b border-gray-200 pb-6">
    <div class="flex items-center gap-4">
        @if ($gym->logoUrl())
            <img src="{{ $gym->logoUrl() }}" alt="" class="size-16 object-contain">
        @else
            <div class="flex size-16 items-center justify-center rounded-xl text-xl font-bold text-white" style="background: {{ $gym->primaryColor() }}">{{ $gym->initials() }}</div>
        @endif
        <div>
            <p class="text-xl font-bold">{{ $gym->name }}</p>
            <p class="text-sm text-gray-500">{{ trim($gym->address.', '.$gym->city, ', ') }}</p>
            <p class="text-sm text-gray-500">{{ $gym->phone }} @if($gym->email) · {{ $gym->email }} @endif</p>
            @if ($gstin ?? null)<p class="text-sm font-medium text-gray-700">GSTIN: {{ $gstin }}</p>@endif
        </div>
    </div>
    <div class="text-right">
        <p class="text-2xl font-bold tracking-wide uppercase" style="color: {{ $gym->primaryColor() }}">{{ $docTitle }}</p>
        {{ $slot ?? '' }}
    </div>
</div>
