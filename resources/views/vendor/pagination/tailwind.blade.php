{{-- Length-aware pagination: compact "Page X of Y" on phones, numbered pages from sm. --}}
@if ($paginator->hasPages())
    @php
        $pageButton = 'inline-flex h-9 min-w-9 items-center justify-center rounded-control px-3 text-sm font-medium tabular-nums transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500';
        $pageIdle = $pageButton.' border border-line bg-surface text-fg hover:bg-surface-hover';
        $pageDisabled = $pageButton.' border border-line bg-surface text-fg-muted opacity-50 cursor-not-allowed';
    @endphp
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="pagination-nav">
        <div class="flex items-center justify-between gap-2 sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="{{ $pageDisabled }}" aria-disabled="true"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i><span class="sr-only">{{ __('Previous') }}</span></span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $pageIdle }}"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i><span class="sr-only">{{ __('Previous') }}</span></a>
            @endif

            <span class="text-sm text-fg-muted" aria-current="page">
                {{ __('Page') }} <span class="font-semibold text-fg tabular-nums">{{ $paginator->currentPage() }}</span>
                {{ __('of') }} <span class="font-semibold text-fg tabular-nums">{{ $paginator->lastPage() }}</span>
            </span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $pageIdle }}"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i><span class="sr-only">{{ __('Next') }}</span></a>
            @else
                <span class="{{ $pageDisabled }}" aria-disabled="true"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i><span class="sr-only">{{ __('Next') }}</span></span>
            @endif
        </div>

        <div class="hidden sm:flex sm:items-center sm:justify-between sm:gap-4">
            <p class="text-sm text-fg-muted">
                {{ __('Showing') }}
                <span class="font-semibold text-fg tabular-nums">{{ $paginator->firstItem() ?? 0 }}</span>–<span class="font-semibold text-fg tabular-nums">{{ $paginator->lastItem() ?? 0 }}</span>
                {{ __('of') }}
                <span class="font-semibold text-fg tabular-nums">{{ number_format($paginator->total()) }}</span>
            </p>

            <ul class="flex flex-wrap items-center gap-1">
                <li>
                    @if ($paginator->onFirstPage())
                        <span class="{{ $pageDisabled }}" aria-disabled="true"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i><span class="sr-only">{{ __('Previous') }}</span></span>
                    @else
                        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $pageIdle }}"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i><span class="sr-only">{{ __('Previous') }}</span></a>
                    @endif
                </li>

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <li><span class="inline-flex h-9 min-w-9 items-center justify-center text-sm text-fg-muted" aria-hidden="true">{{ $element }}</span></li>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            <li>
                                @if ($page == $paginator->currentPage())
                                    <span aria-current="page" class="{{ $pageButton }} bg-accent text-on-accent shadow-raised">{{ $page }}</span>
                                @else
                                    <a href="{{ $url }}" class="{{ $pageIdle }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                                @endif
                            </li>
                        @endforeach
                    @endif
                @endforeach

                <li>
                    @if ($paginator->hasMorePages())
                        <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $pageIdle }}"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i><span class="sr-only">{{ __('Next') }}</span></a>
                    @else
                        <span class="{{ $pageDisabled }}" aria-disabled="true"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i><span class="sr-only">{{ __('Next') }}</span></span>
                    @endif
                </li>
            </ul>
        </div>
    </nav>
@endif
