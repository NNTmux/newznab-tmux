<div class="relative inline-block" x-data="sortDropdown" @click.outside="close()">
    <button
        type="button"
        @click="toggle"
        class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:border-primary-300 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-primary-600 dark:hover:bg-gray-700 dark:focus:ring-primary-400"
        aria-haspopup="menu"
        x-bind:aria-expanded="open"
    >
        <i class="fas {{ $currentIcon }} text-link dark:text-primary-400" aria-hidden="true"></i>
        <span>Sort: {{ $currentLabel }}</span>
        <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform dark:text-gray-300" :class="chevronClass()" aria-hidden="true"></i>
    </button>

    <div x-show="open"
         x-cloak
         x-transition:enter="transition ease-out duration-100"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="absolute right-0 z-50 mt-2 w-56 origin-top-right overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl focus:outline-none dark:border-gray-600 dark:bg-gray-800"
         role="menu">
        <div class="py-1 max-h-80 overflow-y-auto">
            @foreach($sortOptions as $sortKey => $sortData)
                <a
                    href="{{ $sortUrls[$sortKey] }}"
                    class="flex min-h-10 items-center gap-3 px-4 py-2 text-sm transition {{ $currentSort === $sortKey ? 'bg-primary-100 dark:bg-accent-hover text-primary-800 dark:text-white font-semibold' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700' }}"
                    role="menuitem"
                >
                    <i aria-hidden="true" class="fas {{ $sortData['icon'] }} w-4 text-center {{ $currentSort === $sortKey ? 'text-link dark:text-primary-200' : 'text-gray-400 dark:text-gray-400' }}"></i>
                    <span class="flex-1">{{ $sortData['label'] }}</span>
                    @if($currentSort === $sortKey)
                        <i aria-hidden="true" class="fas fa-check text-link dark:text-primary-200"></i>
                    @endif
                </a>
            @endforeach
        </div>
    </div>
</div>
