{{-- Admin pagination wrapper --}}
@props([
    'paginator',
    'position' => 'bottom',
    'onEachSide' => 5,
])

@if($paginator->hasPages())
    @php
        $borderClass = $position === 'top'
            ? 'border-b border-gray-200 dark:border-gray-700'
            : 'border-t border-gray-200 dark:border-gray-700';
    @endphp
    <div class="px-6 py-4 {{ $borderClass }} bg-gray-50 dark:bg-gray-900">
        {{ $paginator->onEachSide((int) $onEachSide)->links() }}
    </div>
@endif

