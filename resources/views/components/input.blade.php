{{--
    Text-style input. When `name` has a validation error it gets aria-invalid and points
    aria-describedby at "{id}-error" (rendered by <x-field>). Not folded: it reads $errors.
--}}
@php
    $controlName = (string) $attributes->get('name', '');
    $errorKey = trim(str_replace(['[]', '[', ']'], ['', '.', ''], $controlName), '.');
    $hasError = $errorKey !== '' && isset($errors) && $errors->has($errorKey);
    $controlId = $attributes->get('id');
@endphp
<input {{ $attributes->merge([
    'type' => 'text',
    'class' => 'min-h-11 w-full rounded-control border border-line bg-surface px-3.5 py-2.5 text-fg shadow-raised transition placeholder:text-fg-muted focus:border-primary-500 focus:ring-primary-500 aria-invalid:border-danger-500 aria-invalid:focus:ring-danger-500 disabled:cursor-not-allowed disabled:opacity-60',
]) }} @if($hasError) aria-invalid="true" @if($controlId) aria-describedby="{{ $controlId }}-error" @endif @endif>
