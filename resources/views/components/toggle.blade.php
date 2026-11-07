{{--
    On/off switch for boolean settings. Always submits a value: `off` when unchecked
    (hidden input) and `on` when checked. Works without JavaScript.
--}}
@props([
    'name',
    'checked' => false,
    'label' => null,
    'id' => null,
    'on' => '1',
    'off' => '0',
])

@php
    $toggleId = $id ?? 'toggle-'.\Illuminate\Support\Str::slug(str_replace(['[', ']'], '-', $name));
@endphp

<label for="{{ $toggleId }}" class="inline-flex cursor-pointer items-center gap-3">
    <input type="hidden" name="{{ $name }}" value="{{ $off }}">
    <input type="checkbox" role="switch" id="{{ $toggleId }}" name="{{ $name }}" value="{{ $on }}" @checked($checked)
        {{ $attributes->merge(['class' => 'peer sr-only']) }}>
    <span aria-hidden="true" class="relative inline-flex h-6 w-11 shrink-0 rounded-full bg-gray-300 transition peer-checked:bg-accent peer-focus-visible:ring-2 peer-focus-visible:ring-primary-500 peer-focus-visible:ring-offset-2 peer-focus-visible:ring-offset-canvas peer-disabled:opacity-60 after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-5 dark:bg-gray-600"></span>
    @if($label)
        <span class="text-sm font-medium text-fg">{{ $label }}</span>
    @endif
</label>
