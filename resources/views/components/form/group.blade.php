@props(['label', 'for', 'help' => null])

<div class="space-y-1">
    @if($label)
        <x-label :for="$for" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $label }}
        </x-label>
    @endif

    {{ $slot }}

    @if($help)
        <small class="text-gray-600 dark:text-gray-400 text-xs">{{ $help }}</small>
    @endif
</div>

