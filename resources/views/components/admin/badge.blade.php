@props([
    'tone' => 'gray',
    'icon' => null,
])

@php
    $classes = [
        'blue' => 'bg-primary-100 dark:bg-primary-900/40 text-primary-800 dark:text-primary-200',
        'green' => 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-200',
        'gray' => 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200',
        'orange' => 'bg-orange-100 dark:bg-orange-900/40 text-orange-800 dark:text-orange-200',
        'purple' => 'bg-purple-100 dark:bg-purple-900/40 text-purple-800 dark:text-purple-200',
        'red' => 'bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-200',
        'yellow' => 'bg-yellow-100 dark:bg-yellow-900/40 text-yellow-800 dark:text-yellow-200',
    ][$tone] ?? 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200';
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold '.$classes]) }}>
    @if($icon)
        <i class="{{ $icon }}" aria-hidden="true"></i>
    @endif
    {{ $slot }}
</span>
