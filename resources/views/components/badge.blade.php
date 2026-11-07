{{-- Status pill. Tones: neutral, primary, info, success, warning, danger (`type` kept as an alias). --}}
@blaze
@props([
    'tone' => null,
    'type' => 'primary',
    'size' => 'sm',
    'icon' => null,
])

@php
    $sizes = [
        'xs' => 'px-2 py-0.5 text-xs',
        'sm' => 'px-2.5 py-0.5 text-xs',
        'md' => 'px-3 py-1 text-sm',
    ];

    $tones = [
        'neutral' => 'bg-gray-100 text-gray-700 ring-gray-500/15 dark:bg-gray-800 dark:text-gray-200 dark:ring-white/10',
        'primary' => 'bg-primary-50 text-primary-800 ring-primary-600/20 dark:bg-primary-950/60 dark:text-primary-200 dark:ring-primary-400/25',
        'info' => 'bg-info-50 text-info-800 ring-info-600/20 dark:bg-info-950/60 dark:text-info-200 dark:ring-info-400/25',
        'success' => 'bg-success-50 text-success-800 ring-success-600/20 dark:bg-success-950/60 dark:text-success-200 dark:ring-success-400/25',
        'warning' => 'bg-warning-50 text-warning-800 ring-warning-600/25 dark:bg-warning-950/60 dark:text-warning-200 dark:ring-warning-400/25',
        'danger' => 'bg-danger-50 text-danger-800 ring-danger-600/20 dark:bg-danger-950/60 dark:text-danger-200 dark:ring-danger-400/25',
    ];
    $aliases = ['default' => 'primary', 'gray' => 'neutral'];
    $resolved = $aliases[$tone ?? $type] ?? ($tone ?? $type);

    $classes = 'inline-flex items-center gap-1 rounded-full font-semibold ring-1 ring-inset '.($sizes[$size] ?? $sizes['sm']).' '.($tones[$resolved] ?? $tones['neutral']);
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    @if($icon)
        <i aria-hidden="true" class="{{ $icon }}"></i>
    @endif
    {{ $slot }}
</span>
