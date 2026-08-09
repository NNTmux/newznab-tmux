@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.card>
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">
                    <i class="fas fa-database mr-2"></i>{{ $title }}
                </h1>
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    Total: {{ $results->total() }} entries
                </div>
            </div>
        </div>

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
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="flex items-end space-x-2">
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i class="fas fa-search mr-2"></i>Search
                    </button>
                    @if($lastSearch)
                        <a href="{{ url('admin/predb') }}" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                            <i class="fas fa-times mr-2"></i>Clear
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
                                {{ $pre->size ? \App\Models\Release::bytesToSizeString($pre->size) : '—' }}
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
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                        <i class="fas fa-check-circle mr-1"></i>Not Nuked
                                    </span>
                                @elseif($pre->nuked == 1)
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                        <i class="fas fa-undo mr-1"></i>Un-Nuked
                                    </span>
                                @elseif($pre->nuked == 2)
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                        <i class="fas fa-radiation mr-1"></i>Nuked
                                    </span>
                                @elseif($pre->nuked == 3)
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>Mod Nuked
                                    </span>
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
                                       target="_blank"
                                       class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300">
                                        <i class="fas fa-external-link-alt mr-1"></i>View
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
                                        <a href="{{ url('admin/predb') }}" class="text-blue-600 dark:text-blue-400 hover:underline">
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
    <div class="mt-6 bg-blue-50 dark:bg-blue-900 border-l-4 border-blue-500 dark:border-blue-600 p-4 rounded">
        <div class="flex">
            <div class="shrink-0">
                <i class="fas fa-info-circle text-blue-600 dark:text-blue-400"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-blue-800 dark:text-blue-200">
                    Showing search results for: <strong>{{ $lastSearch }}</strong>
                </p>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection

