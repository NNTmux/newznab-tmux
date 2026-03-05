@props([
    'items' => []
])

<div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
    <nav class="flex" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-2 text-sm">
            @foreach($items as $index => $item)
                @if($index > 0)
                    <li aria-hidden="true">
                        <i class="fas fa-chevron-right text-xs text-gray-400 dark:text-gray-500 mx-1"></i>
                    </li>
                @endif
                <li class="inline-flex items-center">
                    @if(!empty($item['url']))
                        <a href="{{ $item['url'] }}" class="text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 inline-flex items-center transition">
                            @if(!empty($item['icon']))
                                <i class="{{ $item['icon'] }} mr-1.5"></i>
                            @endif
                            {{ $item['label'] }}
                        </a>
                    @else
                        <span class="text-gray-500 dark:text-gray-400 inline-flex items-center">
                            @if(!empty($item['icon']))
                                <i class="{{ $item['icon'] }} mr-1.5"></i>
                            @endif
                            {{ $item['label'] }}
                        </span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
</div>

