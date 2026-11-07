{{-- Admin status pill: wrapper around the shared <x-badge>. Legacy color names map to tones. --}}
@props([
    'tone' => 'neutral',
    'icon' => null,
])

@php
    $legacy = ['blue' => 'primary', 'green' => 'success', 'gray' => 'neutral', 'orange' => 'warning', 'yellow' => 'warning', 'red' => 'danger', 'purple' => 'info'];
@endphp

<x-badge :tone="$legacy[$tone] ?? $tone" :icon="$icon" {{ $attributes }}>{{ $slot }}</x-badge>
