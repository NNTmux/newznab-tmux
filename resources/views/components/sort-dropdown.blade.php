<div class="relative inline-block sort-dropdown">
    <button
        type="button"
        class="sort-dropdown-toggle inline-flex items-center gap-2 px-3 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 transition shadow-sm"
    >
        <i class="fas {{ $currentIcon }} text-gray-500 dark:text-gray-300"></i>
        <span>Sort: {{ $currentLabel }}</span>
        <i class="fas fa-chevron-down text-xs text-gray-400 dark:text-gray-300 transition-transform sort-dropdown-chevron"></i>
    </button>

    <div class="sort-dropdown-menu hidden absolute right-0 z-50 mt-2 w-56 origin-top-right rounded-lg bg-white dark:bg-gray-700 shadow-lg border border-gray-200 dark:border-gray-600 focus:outline-none">
        <div class="py-1 max-h-80 overflow-y-auto">
            @foreach($sortOptions as $sortKey => $sortData)
                <a
                    href="{{ $sortUrls[$sortKey] }}"
                    class="flex items-center gap-3 px-4 py-2 text-sm transition {{ $currentSort === $sortKey ? 'bg-blue-100 dark:bg-blue-600 text-blue-800 dark:text-white font-medium' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600' }}"
                >
                    <i class="fas {{ $sortData['icon'] }} w-4 text-center {{ $currentSort === $sortKey ? 'text-blue-600 dark:text-blue-200' : 'text-gray-400 dark:text-gray-400' }}"></i>
                    <span class="flex-1">{{ $sortData['label'] }}</span>
                    @if($currentSort === $sortKey)
                        <i class="fas fa-check text-blue-600 dark:text-blue-200"></i>
                    @endif
                </a>
            @endforeach
        </div>
    </div>
</div>
