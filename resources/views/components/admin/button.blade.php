{{-- Admin button: thin wrapper around the shared <x-button>. Tones map to button variants. --}}
@props([
    'href' => null,
    'type' => 'button',
    'tone' => 'primary',
    'icon' => null,
])

<x-button :href="$href" :type="$type" :variant="$tone === 'gray' ? 'secondary' : $tone" :icon="$icon" {{ $attributes }}>
    {{ $slot }}
</x-button>
