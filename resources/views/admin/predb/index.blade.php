@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.page-header :title="$title" icon="fas fa-database">
        <x-slot:actions>
            <span class="inline-flex min-h-11 items-center rounded-xl border border-gray-200 bg-white/70 px-4 text-sm font-medium text-gray-700 dark:border-gray-600 dark:bg-gray-800/70 dark:text-gray-200">
                {{ number_format($results->total()) }} entries
            </span>
        </x-slot:actions>
    </x-admin.page-header>
    <x-admin.card>

        <!-- Search Form -->
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
            <form method="GET" action="{{ url('admin/predb') }}" class="flex items-center space-x-4">
                <div class="flex-1">
                    <label for="presearch" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Search PreDB
                    </label>
                    <input type="text"
                           id="presearch"
                           name="presearch"
                           value="{{ $lastSearch }}"
                           placeholder="Enter search term..."
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md focus:ring-primary-500 focus:border-primary-500">
                </div>
                <div class="flex items-end space-x-2">
                    <button type="submit" class="px-6 py-2 bg-accent text-white rounded-lg hover:bg-accent-hover">
                        <i aria-hidden="true" class="fas fa-search mr-2"></i>Search
                    </button>
                    @if($lastSearch)
                        <a href="{{ url('admin/predb') }}" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                            <i aria-hidden="true" class="fas fa-times mr-2"></i>Clear
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <!-- PreDB Table -->
        <x-admin.data-table>
            <x-slot:head>
                <x-admin.th>Title</x-admin.th>
                <x-admin.th>Category</x-admin.th>
                <x-admin.th>Size</x-admin.th>
                <x-admin.th>Files</x-admin.th>
                <x-admin.th>Pre Date</x-admin.th>
                <x-admin.th>Source</x-admin.th>
                <x-admin.th>Status</x-admin.th>
                <x-admin.th>Release</x-admin.th>
            </x-slot:head>

                    @forelse($results as $pre)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900 dark:text-gray-200">
                                    {{ $pre->title }}
                                </div>
                                @if($pre->filename)
                                    <div class="text-xs text-gray-500 dark:text-gray-400 font-mono mt-1">
                                        {{ $pre->filename }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                {{ $pre->category ?? '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                {{ $pre->size ?: '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                {{ $pre->files ?? '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                @if($pre->predate)
                                    <div>{{ date('Y-m-d', strtotime($pre->predate)) }}</div>
                                    <div class="text-xs">{{ date('H:i:s', strtotime($pre->predate)) }}</div>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                {{ $pre->source ?? '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($pre->nuked == 0)
                                    <x-admin.badge tone="success" icon="fas fa-check-circle">Not Nuked</x-admin.badge>
                                @elseif($pre->nuked == 1)
                                    <x-admin.badge tone="primary" icon="fas fa-undo">Un-Nuked</x-admin.badge>
                                @elseif($pre->nuked == 2)
                                    <x-admin.badge tone="danger" icon="fas fa-radiation">Nuked</x-admin.badge>
                                @elseif($pre->nuked == 3)
                                    <x-admin.badge tone="warning" icon="fas fa-exclamation-triangle">Mod Nuked</x-admin.badge>
                                @endif
                                @if($pre->nuked > 0 && $pre->nukereason)
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        {{ Str::limit($pre->nukereason, 50) }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                @if(isset($pre->guid))
                                    <a href="{{ url('details/' . $pre->guid) }}"
                                       target="_blank" rel="noopener noreferrer"
                                       class="text-link dark:text-primary-400 hover:text-primary-900 dark:hover:text-primary-300">
                                        <i aria-hidden="true" class="fas fa-external-link-alt mr-1"></i>View
                                    </a>
                                @else
                                    <span class="text-gray-400 dark:text-gray-500">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        @php
                            $predbEmptyTitle = $lastSearch ? 'No PreDB entries found for "'.$lastSearch.'"' : 'No PreDB entries available';
                        @endphp
                        <tr>
                            <td colspan="8" class="p-0">
                                <x-admin.empty-state :icon="$lastSearch ? 'fas fa-search' : 'fas fa-database'" :title="$predbEmptyTitle">
                                    @if($lastSearch)
                                        <a href="{{ url('admin/predb') }}" class="text-link dark:text-primary-400 hover:underline">
                                            Clear search and view all
                                        </a>
                                    @endif
                                </x-admin.empty-state>
                            </td>
                        </tr>
                    @endforelse
        </x-admin.data-table>

        <!-- Pagination -->
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    Showing {{ $results->firstItem() ?? 0 }} to {{ $results->lastItem() ?? 0 }} of {{ $results->total() }} entries
                </div>
                <div>
                    {{ $results->appends(['presearch' => $lastSearch])->links() }}
                </div>
            </div>
        </div>
    </x-admin.card>

    <!-- Info Box -->
    @if($lastSearch)
    <div class="mt-6 bg-info-50 dark:bg-info-900 border-l-4 border-info-500 dark:border-info-600 p-4 rounded">
        <div class="flex">
            <div class="shrink-0">
                <i aria-hidden="true" class="fas fa-info-circle text-info-600 dark:text-info-400"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-info-800 dark:text-info-200">
                    Showing search results for: <strong>{{ $lastSearch }}</strong>
                </p>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
