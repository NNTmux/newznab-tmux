{{-- Icon-only button. `label` is required: it becomes the accessible name and the tooltip. --}}
@props([
    'label',
    'icon',
    'variant' => 'ghost',
    'size' => 'icon',
    'type' => 'button',
    'href' => null,
])

<x-button :href="$href" :type="$type" :variant="$variant" :size="$size" :icon="$icon" aria-label="{{ $label }}" title="{{ $label }}" {{ $attributes }} />
