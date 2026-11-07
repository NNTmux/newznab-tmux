{{-- Inline message box. Tones: info, success, warning, danger. Danger is announced immediately. --}}
@props([
    'tone' => 'info',
    'title' => null,
    'icon' => null,
])

@php
    $tones = [
        'info' => ['box' => 'border-info-200 bg-info-50 text-info-900 dark:border-info-800 dark:bg-info-950/50 dark:text-info-100', 'icon' => 'fa-circle-info text-info-600 dark:text-info-300'],
        'success' => ['box' => 'border-success-200 bg-success-50 text-success-900 dark:border-success-800 dark:bg-success-950/50 dark:text-success-100', 'icon' => 'fa-circle-check text-success-600 dark:text-success-300'],
        'warning' => ['box' => 'border-warning-200 bg-warning-50 text-warning-950 dark:border-warning-800 dark:bg-warning-950/50 dark:text-warning-100', 'icon' => 'fa-triangle-exclamation text-warning-600 dark:text-warning-300'],
        'danger' => ['box' => 'border-danger-200 bg-danger-50 text-danger-900 dark:border-danger-800 dark:bg-danger-950/50 dark:text-danger-100', 'icon' => 'fa-circle-exclamation text-danger-600 dark:text-danger-300'],
    ];
    $style = $tones[$tone] ?? $tones['info'];
@endphp

<div role="{{ $tone === 'danger' ? 'alert' : 'status' }}" {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-card border px-4 py-3 text-sm '.$style['box']]) }}>
    <i aria-hidden="true" class="fa-solid {{ $icon ?? $style['icon'] }} mt-0.5 shrink-0 text-base"></i>
    <div class="min-w-0 flex-1 leading-6">
        @if($title)
            <p class="font-semibold">{{ $title }}</p>
        @endif
        {{ $slot }}
    </div>
</div>
