{{--
    Shared button. Renders <a> when href is given. Variants: primary, secondary, soft, muted, ghost,
    success, danger, warning. Sizes: sm, md, lg, icon. `loading` disables it and shows a spinner.
--}}
@blaze
@props([
    'type' => 'button',
    'variant' => 'primary',
    'size' => 'md',
    'icon' => null,
    'href' => null,
    'loading' => false,
])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-control border font-semibold transition duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-canvas disabled:cursor-not-allowed disabled:opacity-60 aria-disabled:pointer-events-none aria-disabled:opacity-60';

    $sizes = [
        'sm' => 'min-h-9 px-3 py-1.5 text-xs',
        'md' => 'min-h-11 px-4 py-2 text-sm',
        'lg' => 'min-h-12 px-6 py-3 text-base',
        'icon' => 'h-11 w-11 p-0 text-sm',
    ];

    $variants = [
        'primary' => 'border-transparent bg-accent text-on-accent shadow-raised hover:bg-accent-hover',
        'secondary' => 'border-line bg-surface text-fg shadow-raised hover:bg-surface-hover',
        'soft' => 'border-transparent bg-primary-50 text-link hover:bg-primary-100 dark:bg-primary-950/50 dark:hover:bg-primary-900/60',
        'muted' => 'border-transparent bg-surface-sunken text-fg hover:bg-surface-hover',
        'ghost' => 'border-transparent bg-transparent text-fg hover:bg-surface-hover',
        'success' => 'border-transparent bg-success-700 text-white shadow-raised hover:bg-success-800',
        'danger' => 'border-transparent bg-danger-600 text-white shadow-raised hover:bg-danger-700',
        'warning' => 'border-transparent bg-warning-400 text-warning-950 shadow-raised hover:bg-warning-500',
    ];

    $classes = $base.' '.($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['primary']);
    $iconClass = $loading ? 'fa-solid fa-spinner fa-spin' : $icon;
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }} @if($loading) aria-disabled="true" aria-busy="true" @endif>
        @if($iconClass)
            <i aria-hidden="true" class="{{ $iconClass }}"></i>
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }} @if($loading) disabled aria-busy="true" @endif>
        @if($iconClass)
            <i aria-hidden="true" class="{{ $iconClass }}"></i>
        @endif
        {{ $slot }}
    </button>
@endif
