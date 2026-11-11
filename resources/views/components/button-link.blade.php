{{-- Link styled as a button; kept for existing callers. Prefer <x-button href="…">. --}}
@props([
    'variant' => 'primary',
    'size' => 'md',
    'icon' => null,
    'href' => null,
])

<x-button :href="$href" :variant="$variant" :size="$size" :icon="$icon" {{ $attributes }}>
    {{ $slot }}
</x-button>
