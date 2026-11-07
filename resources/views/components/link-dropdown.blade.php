{{--
    Dropdown menu of plain links (works without JavaScript beyond the open/close toggle).
    Items: [['label' => …, 'url' => …, 'active' => bool, 'icon' => 'fa-…' (optional)], …]
--}}
@props([
    'label',
    'items' => [],
    'icon' => null,
    'placeholder' => null,
    'align' => 'left',
])

@php
    $activeItem = collect($items)->firstWhere('active', true);
    $menuId = 'link-dropdown-'.\Illuminate\Support\Str::slug($label).'-'.substr(md5(json_encode($items)), 0, 6);
@endphp

<div {{ $attributes->merge(['class' => 'relative inline-block']) }} x-data="sortDropdown" @click.outside="close()" @keydown.escape="close()">
    <button
        type="button"
        @click="toggle"
        class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:border-primary-300 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-primary-600 dark:hover:bg-gray-700 dark:focus:ring-primary-400"
        aria-haspopup="menu"
        aria-controls="{{ $menuId }}"
        x-bind:aria-expanded="open"
    >
        @if($icon)
            <i class="{{ $icon }} text-link dark:text-primary-400" aria-hidden="true"></i>
        @endif
        <span>{{ $label }}: {{ $activeItem['label'] ?? $placeholder }}</span>
        <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform dark:text-gray-300" :class="chevronClass()" aria-hidden="true"></i>
    </button>

    <div x-show="open"
         x-cloak
         id="{{ $menuId }}"
         x-transition:enter="transition ease-out duration-100"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         @class([
             'absolute z-50 mt-2 w-56 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl focus:outline-none dark:border-gray-600 dark:bg-gray-800',
             'right-0 origin-top-right' => $align === 'right',
             'left-0 origin-top-left' => $align !== 'right',
         ])
         role="menu"
         aria-label="{{ $label }}">
        <div class="max-h-80 overflow-y-auto py-1">
            @foreach($items as $item)
                <a href="{{ $item['url'] }}"
                   role="menuitem"
                   @if($item['active'] ?? false) aria-current="true" @endif
                   class="flex min-h-10 items-center gap-3 px-4 py-2 text-sm transition {{ ($item['active'] ?? false) ? 'bg-primary-100 dark:bg-accent-hover text-primary-800 dark:text-white font-semibold' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                    @isset($item['icon'])
                        <i aria-hidden="true" class="fas {{ $item['icon'] }} w-4 text-center {{ ($item['active'] ?? false) ? 'text-link dark:text-primary-200' : 'text-gray-400 dark:text-gray-400' }}"></i>
                    @endisset
                    <span class="flex-1">{{ $item['label'] }}</span>
                    @if($item['active'] ?? false)
                        <i aria-hidden="true" class="fas fa-check text-link dark:text-primary-200"></i>
                    @endif
                </a>
            @endforeach
        </div>
    </div>
</div>
