@props(['label', 'for', 'help' => null])

<div class="space-y-1">
    @if($label)
        <x-label :for="$for" class="block text-sm font-medium text-gray-700 mb-1">
            {{ $label }}
        </x-label>
    @endif

    {{ $slot }}

    @if($help)
        <small class="text-gray-600 text-xs">{{ $help }}</small>
    @endif
</div>

