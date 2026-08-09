@if(!empty($pagination['per_page']) && $pagination['per_page'] > 0 && ($pagination['total_pages'] ?? 1) > 1)
    <div class="series-stat-card mt-6 flex items-center justify-between bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-4" data-series-pagination>
        <div class="text-sm text-gray-600 dark:text-gray-300">
            Page {{ $pagination['current_page'] }} of {{ $pagination['total_pages'] }}
            <span class="ml-2 text-xs text-gray-500">({{ number_format($pagination['total_rows']) }} total releases)</span>
        </div>
        <div class="flex gap-2">
            @php
                $prevPage = max($pagination['current_page'] - 1, 1);
                $nextPage = min($pagination['current_page'] + 1, $pagination['total_pages']);
                $paginationQuery = request()->query();
                unset($paginationQuery['_fragment']);
                $paginationBaseUrl = url()->current();
                $prevUrl = $pagination['current_page'] > 1 ? $paginationBaseUrl . '?' . http_build_query(array_merge($paginationQuery, ['page' => $prevPage])) . '#series-episodes' : '#';
                $nextUrl = $pagination['current_page'] < $pagination['total_pages'] ? $paginationBaseUrl . '?' . http_build_query(array_merge($paginationQuery, ['page' => $nextPage])) . '#series-episodes' : '#';
            @endphp
            <a href="{{ $prevUrl }}"
               @if($pagination['current_page'] > 1) data-series-pagination-link @endif
               class="px-4 py-2 rounded-lg text-sm font-medium {{ $pagination['current_page'] > 1 ? 'bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700' : 'bg-gray-200 dark:bg-gray-700 text-gray-400 cursor-not-allowed' }}">
                <i class="fas fa-arrow-left mr-2"></i>Previous
            </a>
            <a href="{{ $nextUrl }}"
               @if($pagination['current_page'] < $pagination['total_pages']) data-series-pagination-link @endif
               class="px-4 py-2 rounded-lg text-sm font-medium {{ $pagination['current_page'] < $pagination['total_pages'] ? 'bg-primary-600 text-white hover:bg-primary-700' : 'bg-gray-200 dark:bg-gray-700 text-gray-400 cursor-not-allowed' }}">
                Next<i class="fas fa-arrow-right ml-2"></i>
            </a>
        </div>
    </div>
@endif
