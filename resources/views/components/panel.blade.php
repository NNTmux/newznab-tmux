@props([
    'variant' => 'default',
    'padding' => 'md',
])

@php
    $surfaces = [
        'default' => 'surface-panel border shadow-sm',
        'alt' => 'surface-panel-alt border',
        'plain' => 'surface-panel',
    ];

    $paddings = [
        'none' => '',
        'sm' => 'p-4',
        'md' => 'p-6',
        'lg' => 'p-8',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-card '.($surfaces[$variant] ?? $surfaces['default']).' '.($paddings[$padding] ?? $paddings['md'])]) }}>
    {{ $slot }}
</div>
