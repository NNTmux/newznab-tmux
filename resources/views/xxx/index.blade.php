@extends('layouts.main')

@push('modals')
    @include('partials.release-modals')
@endpush

@section('content')
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
    @php
        $crumbs = [['label' => 'Home', 'url' => url($site['home_link'] ?? '/'), 'icon' => 'fas fa-home'], ['label' => 'XXX', 'url' => url('/XXX')]];
        if (!empty($categorytitle) && $categorytitle !== 'All') {
            $crumbs[] = ['label' => $categorytitle];
        }
    @endphp
    <x-breadcrumb :items="$crumbs" />

    <div class="px-6 py-4">
        <!-- Category and order -->
        <div class="mb-4 flex flex-wrap items-center gap-4">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Category:</span>
                @foreach($catlist ?? [] as $c)
                    <a href="{{ url('/XXX/' . $c['title']) }}?t={{ $c['id'] }}"
                       class="px-3 py-1 rounded-lg text-sm {{ (string)($category ?? '') === (string)$c['id'] ? 'bg-primary-600 dark:bg-primary-700 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}">
                        {{ $c['title'] }}
                    </a>
                @endforeach
            </div>
            <div class="flex items-center gap-2 flex-wrap ml-auto">
                <x-inline-search placeholder="Search in XXX{{ !empty($categorytitle) && $categorytitle !== 'All' ? ' ' . $categorytitle : '' }}..." :category="$category ?? null" />
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Order by:</span>
                @foreach($ordering ?? [] as $ob)
                    <a href="{{ $orderByUrls['orderby'.$ob] ?? url('/XXX/' . ($categorytitle ?: 'All') . '?ob=' . $ob) }}"
                       class="px-2 py-1 rounded text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600">
                        {{ str_replace('_', ' ', $ob) }}
                    </a>
                @endforeach
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
                                            <i class="fas fa-film text-gray-400 text-lg"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <a href="{{ url('/details/' . $result->guid) }}" class="text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300 font-medium break-all block">
                                                {{ $result->searchname }}
                                            </a>
                                            @if(!empty($result->failed_count) && $result->failed_count > 0)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 mt-1">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i> Failed ({{ $result->failed_count }})
                                                </span>
                                            @endif
                                            @if(!empty($result->group_name))
                                                <span class="text-xs text-gray-500 dark:text-gray-400 mt-1 block">{{ $result->group_name }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 dark:bg-primary-900/50 text-primary-800 dark:text-primary-200">
                                        {{ $result->category_name ?? 'Other' }}
                                    </span>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                    {{ userDateDiffForHumans($result->adddate ?? null) }}
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                    {{ isset($result->size) ? number_format($result->size / 1073741824, 2) . ' GB' : '-' }}
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-1">
                                        <a href="{{ url('/getnzb/' . $result->guid) }}" class="px-2 py-1 bg-green-600 dark:bg-green-700 text-white rounded text-sm hover:bg-green-700 dark:hover:bg-green-800" title="Download NZB">
                                            <i class="fa fa-download"></i>
                                        </a>
                                        <a href="{{ url('/details/' . $result->guid) }}" class="px-2 py-1 bg-primary-600 dark:bg-primary-700 text-white rounded text-sm hover:bg-primary-700 dark:hover:bg-primary-800" title="Details">
                                            <i class="fa fa-info"></i>
                                        </a>
                                        <a href="#" class="add-to-cart px-2 py-1 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded text-sm hover:bg-gray-300 dark:hover:bg-gray-600" data-guid="{{ $result->guid }}" title="Add to Cart">
                                            <i class="fa fa-shopping-cart"></i>
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
