@props([
    'icon' => 'fas fa-search',
    'title' => 'No results found',
    'message' => 'Try adjusting your search criteria or browse other categories.',
    'actionUrl' => null,
    'actionLabel' => null,
    'actionIcon' => null,
])

<div class="px-6 py-12 text-center">
    <div class="inline-flex items-center justify-center w-20 h-20 bg-gray-100 dark:bg-gray-700 rounded-full mb-6">
        <i class="{{ $icon }} text-gray-400 dark:text-gray-500 text-4xl"></i>
    </div>
    <h3 class="text-xl font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ $title }}</h3>
    <p class="text-gray-500 dark:text-gray-400 max-w-md mx-auto mb-4">{{ $message }}</p>
    @if($actionUrl && $actionLabel)
        <a href="{{ $actionUrl }}" class="inline-flex items-center px-4 py-2 bg-primary-600 dark:bg-primary-700 text-white rounded-lg hover:bg-primary-700 dark:hover:bg-primary-800 transition shadow-sm">
            @if($actionIcon)
                <i class="{{ $actionIcon }} mr-2"></i>
            @endif
            {{ $actionLabel }}
        </a>
    @endif
</div>

