{{-- Select control; same error wiring as <x-input>. --}}
@php
    $controlName = (string) $attributes->get('name', '');
    $errorKey = trim(str_replace(['[]', '[', ']'], ['', '.', ''], $controlName), '.');
    $hasError = $errorKey !== '' && isset($errors) && $errors->has($errorKey);
    $controlId = $attributes->get('id');
@endphp
<select {{ $attributes->merge([
    'class' => 'min-h-11 w-full rounded-control border border-line bg-surface px-3.5 py-2.5 text-fg shadow-raised transition focus:border-primary-500 focus:ring-primary-500 aria-invalid:border-danger-500 dark:[color-scheme:dark] disabled:cursor-not-allowed disabled:opacity-60',
]) }} @if($hasError) aria-invalid="true" @if($controlId) aria-describedby="{{ $controlId }}-error" @endif @endif>
    {{ $slot }}
</select>
