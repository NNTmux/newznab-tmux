{{-- Simple (previous / next) pagination. --}}
@if ($paginator->hasPages())
    @php
        $pageButton = 'inline-flex min-h-9 items-center gap-2 rounded-control border border-line bg-surface px-3 text-sm font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500';
    @endphp
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="pagination-nav flex items-center justify-between gap-2">
        @if ($paginator->onFirstPage())
            <span class="{{ $pageButton }} cursor-not-allowed text-fg-muted opacity-50" aria-disabled="true"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i>{!! __('pagination.previous') !!}</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $pageButton }} text-fg hover:bg-surface-hover"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i>{!! __('pagination.previous') !!}</a>
        @endif

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $pageButton }} text-fg hover:bg-surface-hover">{!! __('pagination.next') !!}<i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
        @else
            <span class="{{ $pageButton }} cursor-not-allowed text-fg-muted opacity-50" aria-disabled="true">{!! __('pagination.next') !!}<i class="fa-solid fa-chevron-right" aria-hidden="true"></i></span>
        @endif
    </nav>
@endif
