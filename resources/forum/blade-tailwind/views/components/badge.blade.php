@props([
    'type' => 'default'
])

@php
switch($type) {
    case('info'):
        $color = 'bg-blue-500 dark:bg-blue-600';
        break;

    case('danger'):
        $color = 'bg-red-500 dark:bg-red-600';
        break;

    case('warning'):
        $color = 'bg-orange-500 dark:bg-orange-600';
        break;

    default:
        $color = 'bg-gray-400 dark:bg-gray-500';
        break;
}
@endphp

<span {{ $attributes->merge(['class' => "$color rounded-full px-2 py-1 text-white text-xs font-semibold"]) }}>
    {{ $slot }}
</span>
