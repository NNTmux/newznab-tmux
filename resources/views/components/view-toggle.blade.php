@blaze
@props([
    'currentView' => 'list',
    'covgroup' => null,
    'category' => null,
    'parentcat' => null,
    'shows' => false
])

@php
    // Build URLs for toggling views while preserving current query parameters
    $currentParams = request()->query();

    // Map covgroup to correct cover view route
    $coverRouteMap = [
        'movies' => 'Movies',
        'console' => 'Console',
        'games' => 'Games',
        'music' => 'Audio',
        'books' => 'Books',
        'xxx' => 'XXX',
    ];

    // Map covgroup to correct list browse parent category
    $listParentMap = [
        'movies' => 'Movies',
        'console' => 'Console',
        'games' => 'PC',
        'music' => 'Audio',
        'books' => 'Books',
        'xxx' => 'XXX',
    ];

    // Parent category IDs - these should not be appended to URLs
    $parentCategoryIds = [
        1000, // Console/Game
        2000, // Movies
        3000, // Audio/Music
        4000, // PC
        5000, // TV
        6000, // XXX
        7000, // Books
        8000, // Other
    ];

    // Check if category is a parent category ID (should not be in URL path)
    $isParentCategory = is_numeric($category) && in_array((int)$category, $parentCategoryIds);

    // Build cover view URL
    if ($covgroup && isset($coverRouteMap[$covgroup])) {
        $coverRoute = $coverRouteMap[$covgroup];
        // Only append category if it's not a parent category ID and not 'All' or -1
        $catParam = ($category && $category !== -1 && $category !== 'All' && !$isParentCategory) ? $category : '';
        $coverUrl = url('/' . $coverRoute . ($catParam ? '/' . $catParam : ''));
    } elseif ($shows) {
        $coverUrl = route('series');
    } else {
        $coverUrl = '#';
    }

    // Build list view URL - preserve query params except view and thumbs for clean URL
    $listParams = $currentParams;
    unset($listParams['view']);

    // Determine the correct parent category for list view
    $listParent = $parentcat;
    if ($covgroup && isset($listParentMap[$covgroup])) {
        $listParent = $listParentMap[$covgroup];
    }

    // Only append category to list URL if it's not a parent category ID
    if ($listParent && $category && $category !== -1 && $category !== 'All' && !$isParentCategory) {
        $listUrl = url('/browse/' . $listParent . '/' . $category);
    } elseif ($listParent) {
        $listUrl = url('/browse/' . $listParent);
    } else {
        $listUrl = request()->url();
    }

    // Add query string if there are params
    if (!empty($listParams)) {
        $listUrl .= '?' . http_build_query($listParams);
    }

    $showThumbnails = request()->query('thumbs', '0') === '1';
@endphp

<div class="inline-flex min-h-11 flex-wrap items-center gap-1 rounded-xl border border-gray-200 bg-white/80 p-1 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800/80" aria-label="Result view options">
    <span class="px-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">View</span>

    @if($currentView === 'covers')
        {{-- Currently in cover view --}}
        <span class="inline-flex min-h-9 items-center rounded-lg bg-accent px-3 font-semibold text-white shadow-sm">Covers</span>
        <a href="{{ $listUrl }}" class="inline-flex min-h-9 items-center rounded-lg px-3 font-medium text-gray-600 transition hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white">List</a>
    @else
        {{-- Currently in list view --}}
        @if($covgroup || $shows)
            <a href="{{ $coverUrl }}" class="inline-flex min-h-9 items-center rounded-lg px-3 font-medium text-gray-600 transition hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white">Covers</a>
        @endif
        <span class="inline-flex min-h-9 items-center rounded-lg bg-accent px-3 font-semibold text-white shadow-sm">List</span>

        {{-- Thumbnail toggle in list view - client-side via Alpine --}}
        @if($covgroup || $shows)
            <button type="button"
               @click="toggleThumbs()"
               class="inline-flex min-h-9 cursor-pointer items-center gap-1.5 rounded-lg px-3 font-medium transition hover:bg-gray-100 dark:hover:bg-gray-700"
               x-bind:class="showThumbs ? 'text-success-700 dark:text-success-300' : 'text-gray-600 dark:text-gray-300'"
               x-bind:title="showThumbs ? 'Hide thumbnails' : 'Show thumbnails'">
                <i class="fas fa-image text-xs" aria-hidden="true"></i>
                <span x-text="showThumbs ? 'Hide Thumbs' : 'Show Thumbs'"></span>
            </button>
        @endif
    @endif
</div>
