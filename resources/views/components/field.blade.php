{{--
    Form field wrapper: label, control (slot), help text and the validation error for `name`.
    Help id: "{for}-help", error id: "{for}-error" — <x-input>/<x-select>/<x-textarea> link to the error.
--}}
@props([
    'label' => null,
    'for' => null,
    'name' => null,
    'help' => null,
    'required' => false,
])

@php
    $errorKey = $name ? trim(str_replace(['[]', '[', ']'], ['', '.', ''], $name), '.') : null;
    $fieldError = $errorKey && isset($errors) ? $errors->first($errorKey) : null;
@endphp

<div {{ $attributes->merge(['class' => 'space-y-1.5']) }}>
    @if($label)
        <label @if($for) for="{{ $for }}" @endif class="block text-sm font-semibold text-fg">
            {{ $label }}
            @if($required)
                <span class="text-danger-600 dark:text-danger-400" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    {{ $slot }}

    @if($help)
        <p @if($for) id="{{ $for }}-help" @endif class="text-xs leading-5 text-fg-muted">{{ $help }}</p>
    @endif

    @if($fieldError)
        <p @if($for) id="{{ $for }}-error" @endif class="flex items-start gap-1.5 text-sm font-medium text-danger-600 dark:text-danger-400">
            <i aria-hidden="true" class="fa-solid fa-circle-exclamation mt-0.5"></i>
            {{ $fieldError }}
        </p>
    @endif
</div>
