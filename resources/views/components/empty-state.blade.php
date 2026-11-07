@props([
    'icon' => 'fas fa-search',
    'title' => 'No results found',
    'message' => 'Try adjusting your search criteria or browse other categories.',
    'actionUrl' => null,
    'actionLabel' => null,
    'actionIcon' => null,
])

<div class="empty-state px-6 py-14 text-center">
    <div class="empty-state__icon mb-6 inline-flex h-20 w-20 items-center justify-center rounded-2xl">
        <i class="{{ $icon }} text-3xl" aria-hidden="true"></i>
    </div>
    <h3 class="mb-2 text-xl font-semibold tracking-tight text-gray-900 dark:text-gray-100">{{ $title }}</h3>
    <p class="mx-auto mb-5 max-w-md text-sm leading-6 text-gray-500 dark:text-gray-400">{{ $message }}</p>
    @if($actionUrl && $actionLabel)
        <a href="{{ $actionUrl }}" class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-accent px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-accent-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:bg-accent-hover dark:hover:bg-accent">
            @if($actionIcon)
                <i class="{{ $actionIcon }}" aria-hidden="true"></i>
            @endif
            {{ $actionLabel }}
        </a>
    @endif
</div>
