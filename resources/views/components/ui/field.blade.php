@props(['label' => null, 'name' => null, 'hint' => null, 'required' => false])
<div {{ $attributes }}>
    @if ($label)
        <label @if($name) for="{{ $name }}" @endif class="label">{{ $label }} @if($required)<span class="text-red-500">*</span>@endif</label>
    @endif
    {{ $slot }}
    @if ($hint)
        <p class="hint">{{ $hint }}</p>
    @endif
    @if ($name)
        @error($name) <p class="input-error">{{ $message }}</p> @enderror
    @endif
</div>
