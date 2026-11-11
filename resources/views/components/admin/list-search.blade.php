{{--
    Search form for an admin list page. Submits `name` by GET to `action` and offers Clear while a
    term is active, so every list filters the same way.
--}}
@props([
    'action',
    'name' => 'search',
    'value' => '',
    'placeholder' => 'Search',
    'label' => 'Search this list',
])

<x-admin.filter-panel>
    <form method="GET" action="{{ $action }}" role="search" class="flex flex-col gap-2 sm:flex-row sm:items-center">
        <label for="{{ $name }}-input" class="sr-only">{{ $label }}</label>
        <x-input type="search" id="{{ $name }}-input" name="{{ $name }}" value="{{ $value }}" placeholder="{{ $placeholder }}" class="sm:max-w-md" />
        <div class="flex gap-2">
            <x-admin.button type="submit" icon="fas fa-search">Search</x-admin.button>
            @if((string) $value !== '')
                <x-admin.button :href="$action" tone="gray" icon="fas fa-xmark">Clear</x-admin.button>
            @endif
        </div>
    </form>
</x-admin.filter-panel>
