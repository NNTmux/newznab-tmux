@props([
    'placeholder' => 'Search releases...',
    'name' => 'search',
    'value' => '',
    'categorySelect' => false,
    'categories' => [],
    'selectedCategory' => '-1',
    'submitButton' => true,
    'formId' => 'search-form-' . uniqid(),
    'inputId' => 'search-input-' . uniqid(),
    'size' => 'normal', // 'small', 'normal', 'large'
    'showSuggestion' => false,
    'suggestion' => null,
])

@php
    $sizeClasses = match($size) {
        'small' => 'text-sm px-3 py-1.5',
        'large' => 'text-lg px-4 py-3',
        default => 'text-base px-4 py-2',
    };
@endphp

<form method="GET" action="{{ route('search') }}" class="relative" id="{{ $formId }}" data-autocomplete-form>
    <div class="flex items-center">
        @if($categorySelect && !empty($categories))
            <select name="t"
                    class="bg-gray-700 text-white {{ $sizeClasses }} rounded-l border-r border-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="-1">All</option>
                @foreach($categories as $category)
                    @php
                        $catId = is_object($category) ? $category->id : ($category['id'] ?? '');
                        $catTitle = is_object($category) ? $category->title : ($category['title'] ?? '');
                        $subcats = is_object($category) ? ($category->categories ?? []) : ($category['categories'] ?? []);
                    @endphp
                    <option value="{{ $catId }}" {{ $selectedCategory == $catId ? 'selected' : '' }} class="font-semibold">
                        {{ $catTitle }}
                    </option>
                    @foreach($subcats as $subcat)
                        @php
                            $subcatId = is_object($subcat) ? $subcat->id : ($subcat['id'] ?? '');
                            $subcatTitle = is_object($subcat) ? $subcat->title : ($subcat['title'] ?? '');
                        @endphp
                        <option value="{{ $subcatId }}" {{ $selectedCategory == $subcatId ? 'selected' : '' }}>
                            &nbsp;&nbsp;{{ $subcatTitle }}
                        </option>
                    @endforeach
                @endforeach
            </select>
        @endif

        <div class="relative flex-1">
            <input type="search"
                   name="{{ $name }}"
                   id="{{ $inputId }}"
                   value="{{ $value }}"
                   placeholder="{{ $placeholder }}"
                   autocomplete="off"
                   data-autocomplete-input="{{ $inputId }}-dropdown"
                   data-autocomplete-form="{{ $formId }}"
                   class="w-full bg-white dark:bg-gray-700 text-gray-900 dark:text-white {{ $sizeClasses }}
                          {{ $categorySelect ? '' : 'rounded-l' }}
                          {{ $submitButton ? '' : 'rounded-r' }}
                          border border-gray-300 dark:border-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500">

            <!-- Autocomplete Dropdown -->
            <div id="{{ $inputId }}-dropdown"
                 class="hidden absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg shadow-lg max-h-60 overflow-y-auto">
            </div>
        </div>

        @if($submitButton)
            <button type="submit"
                    class="bg-blue-600 dark:bg-blue-700 hover:bg-blue-700 dark:hover:bg-blue-800 text-white {{ $sizeClasses }} rounded-r transition font-medium">
                <i class="fas fa-search"></i>
                <span class="sr-only">Search</span>
            </button>
        @endif
    </div>

    @if($showSuggestion && !empty($suggestion))
        <div class="mt-2 text-sm">
            <span class="text-gray-600 dark:text-gray-400">Did you mean: </span>
            <a href="{{ route('search', ['search' => $suggestion]) }}"
               class="text-blue-600 dark:text-blue-400 hover:underline font-medium">
                {{ $suggestion }}
            </a>
            <span class="text-gray-500 dark:text-gray-500">?</span>
        </div>
    @endif
</form>
