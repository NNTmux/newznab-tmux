{{-- Consistent table header cell --}}
@props([
    'align' => 'left',
    'width' => null,
])

@php
    $alignClass = match($align) {
        'center' => 'text-center',
        'right' => 'text-right',
        default => 'text-left',
    };
    $widthClass = $width ? "w-{$width}" : '';
@endphp

<th {{ $attributes->merge(['class' => "px-6 py-3 {$alignClass} text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider {$widthClass}"]) }}>
    {{ $slot }}
</th>

