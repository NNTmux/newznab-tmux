{{-- Admin page header with title, icon, optional subtitle and action buttons --}}
@props([
    'title',
    'icon' => null,
    'subtitle' => null,
])

<div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
    <div class="flex flex-wrap justify-between items-center gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">
                @if($icon)
                    <i class="{{ $icon }} mr-2"></i>
                @endif
                {{ $title }}
            </h1>
            @if($subtitle)
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $subtitle }}</p>
            @endif
        </div>
        @if(isset($actions))
            <div class="flex flex-wrap gap-2">
                {{ $actions }}
            </div>
        @endif
    </div>
</div>

