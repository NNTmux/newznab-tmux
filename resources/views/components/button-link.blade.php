{{-- Link styled as a button; kept for existing callers. Prefer <x-button href="…">. --}}
@props([
    'variant' => 'primary',
    'size' => 'md',
    'icon' => null,
])

<x-button :href="$attributes->get('href')" :variant="$variant" :size="$size" :icon="$icon" {{ $attributes->except('href') }}>
    {{ $slot }}
</x-button>
