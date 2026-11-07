@extends('layouts.main')

@push('modals')
    @include('partials.release-modals')
@endpush

@section('content')
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
    <x-page-header title="Adult" :current="!empty($categorytitle) ? $categorytitle : 'All releases'" eyebrow="Browse the catalog" description="Explore adult releases by category, search the current collection, and choose your preferred ordering." icon="fas fa-user-lock">
        <x-slot:stats>
            <span class="workspace-hero__stat"><i class="fas fa-film" aria-hidden="true"></i>{{ number_format($results->total()) }} releases</span>
            <span class="workspace-hero__stat"><i class="fas fa-folder-open" aria-hidden="true"></i>{{ $categorytitle ?: 'All releases' }}</span>
        </x-slot:stats>
    </x-page-header>

    <div class="px-6 py-4">
        <!-- Category and order -->
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <x-view-toggle current-view="covers" covgroup="xxx" :category="!empty($categorytitle) ? $categorytitle : 'All'" parentcat="XXX" :shows="false" />
            <x-link-dropdown label="Category" icon="fas fa-folder-open" :items="\App\Support\Navigation\ListFilters::categoryItems($catlist ?? [], $category ?? null, url('/XXX'), 'All categories')" />
            <x-sort-dropdown :ordering="$ordering ?? []" />
            <div class="ml-auto">
                <x-inline-search placeholder="Search in XXX{{ !empty($categorytitle) && $categorytitle !== 'All' ? ' ' . $categorytitle : '' }}..." :category="$category ?? null" />
            </div>
        </div>

        @if($results->count() > 0)
            <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                {{ $results->total() }} results found
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-100 dark:bg-gray-900">
                        <tr>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">Name</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">Category</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">Added</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">Size</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($results as $result)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                <td class="px-3 py-4">
                                    <div class="flex items-start gap-3">
                                        <div class="shrink-0 w-12 h-16 bg-gray-200 dark:bg-gray-700 rounded flex items-center justify-center">
                                            <i aria-hidden="true" class="fas fa-film text-gray-400 text-lg"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <a href="{{ url('/details/' . $result->guid) }}" class="text-link dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300 font-medium break-all block">
                                                {{ $result->searchname }}
                                            </a>
                                            @if(!empty($result->failed_count) && $result->failed_count > 0)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 mt-1">
                                                    <i aria-hidden="true" class="fas fa-exclamation-triangle mr-1"></i> Failed ({{ $result->failed_count }})
                                                </span>
                                            @endif
                                            @if(!empty($result->group_name))
                                                <span class="text-xs text-gray-500 dark:text-gray-400 mt-1 block">{{ $result->group_name }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <x-release-category-link
                                        :category-name="$result->category_name ?? null"
                                        :parent-category="$result->parent_category ?? null"
                                        :sub-category="$result->sub_category ?? null"
                                    />
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                    {{ userDateDiffForHumans($result->adddate ?? null) }}
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                    {{ isset($result->size) ? number_format($result->size / 1073741824, 2) . ' GB' : '-' }}
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-1">
                                        <a aria-label="Download NZB" href="{{ url('/getnzb/' . $result->guid) }}" class="px-2 py-1 bg-success-600 dark:bg-success-700 text-white rounded text-sm hover:bg-success-700 dark:hover:bg-success-800" title="Download NZB">
                                            <i aria-hidden="true" class="fa-solid fa-download"></i>
                                        </a>
                                        <a aria-label="Details" href="{{ url('/details/' . $result->guid) }}" class="px-2 py-1 bg-accent dark:bg-accent-hover text-white rounded text-sm hover:bg-accent-hover dark:hover:bg-primary-800" title="Details">
                                            <i aria-hidden="true" class="fa-solid fa-info"></i>
                                        </a>
                                        <a aria-label="Add to Cart" href="#" class="add-to-cart px-2 py-1 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded text-sm hover:bg-gray-300 dark:hover:bg-gray-600" data-guid="{{ $result->guid }}" title="Add to Cart">
                                            <i aria-hidden="true" class="fa-solid fa-shopping-cart"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex justify-center mt-6">
                {{ $results->links() }}
            </div>
        @else
            <x-empty-state
                icon="fas fa-film"
                title="No content found"
                message="Try a different category or browse all XXX."
                action-url="{{ url('/XXX/All') }}"
                action-label="Browse All XXX"
                action-icon="fas fa-film"
            />
        @endif
    </div>
</div>
@endsection
