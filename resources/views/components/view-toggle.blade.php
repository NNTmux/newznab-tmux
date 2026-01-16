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

    // Build thumbnail toggle URL for list view
    $thumbParams = $currentParams;
    $showThumbnails = request()->query('thumbs', '0') === '1';
    $thumbParams['thumbs'] = $showThumbnails ? '0' : '1';
    $thumbUrl = request()->url() . '?' . http_build_query($thumbParams);
@endphp

<div class="flex items-center gap-2 text-sm">
    <span class="text-gray-600 dark:text-gray-400">View:</span>

    @if($currentView === 'covers')
        {{-- Currently in cover view --}}
        <span class="font-semibold text-gray-800 dark:text-gray-200">Covers</span>
        <span class="text-gray-400 dark:text-gray-500">|</span>
        <a href="{{ $listUrl }}" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">List</a>
    @else
        {{-- Currently in list view --}}
        @if($covgroup || $shows)
            <a href="{{ $coverUrl }}" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">Covers</a>
            <span class="text-gray-400 dark:text-gray-500">|</span>
        @endif
        <span class="font-semibold text-gray-800 dark:text-gray-200">List</span>

        {{-- Thumbnail toggle in list view --}}
        @if($covgroup || $shows)
            <span class="text-gray-400 dark:text-gray-500 ml-2">|</span>
            <a href="{{ $thumbUrl }}"
               class="inline-flex items-center gap-1 {{ $showThumbnails ? 'text-green-600 dark:text-green-400' : 'text-blue-600 dark:text-blue-400' }} hover:text-blue-800 dark:hover:text-blue-300"
               title="{{ $showThumbnails ? 'Hide thumbnails' : 'Show thumbnails' }}">
                <i class="fas fa-image text-xs"></i>
                <span>{{ $showThumbnails ? 'Hide Thumbs' : 'Show Thumbs' }}</span>
            </a>
        @endif
    @endif
</div>
