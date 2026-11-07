{{-- Decorative Font Awesome icon: <x-icon name="fa-film" /> or <x-icon name="fa-clock" style="regular" />. --}}
@blaze
@props([
    'name',
    'style' => 'solid',
])

<i aria-hidden="true" {{ $attributes->merge(['class' => 'fa-'.$style.' '.$name]) }}></i>
