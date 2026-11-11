@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.page-header :title="$title" icon="fas fa-dragon">
        <x-slot:actions>
            <span class="inline-flex min-h-11 items-center rounded-xl border border-gray-200 bg-white/70 px-4 text-sm font-medium text-gray-700 dark:border-gray-600 dark:bg-gray-800/70 dark:text-gray-200">
                {{ number_format($anidblist->total()) }} anime
            </span>
        </x-slot:actions>
    </x-admin.page-header>
    <x-admin.card>

        <!-- Search Form -->
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
            <form method="GET" action="{{ url('admin/anidb-list') }}" class="flex items-center space-x-4">
                <div class="flex-1">
                    <label for="animetitle" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Search Anime
                    </label>
                    <input type="text"
                           id="animetitle"
                           name="animetitle"
                           value="{{ $animetitle }}"
                           placeholder="Enter anime title..."
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md focus:ring-primary-500 focus:border-primary-500">
                </div>
                <div class="flex items-end space-x-2">
                    <button type="submit" class="px-6 py-2 bg-accent text-white rounded-lg hover:bg-accent-hover">
                        <i aria-hidden="true" class="fas fa-search mr-2"></i>Search
                    </button>
                    @if($animetitle)
                        <a href="{{ url('admin/anidb-list') }}" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                            <i aria-hidden="true" class="fas fa-times mr-2"></i>Clear
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <!-- AniDB Table -->
        <x-admin.data-table>
            <x-slot:head>
                <x-admin.th>Cover</x-admin.th>
                <x-admin.th>Title</x-admin.th>
                <x-admin.th>Type</x-admin.th>
                <x-admin.th>Start Date</x-admin.th>
                <x-admin.th>End Date</x-admin.th>
                <x-admin.th>Rating</x-admin.th>
                <x-admin.th>Actions</x-admin.th>
            </x-slot:head>

                    @forelse($anidblist as $anime)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php
                                    $hasCover = $anime->anidbid > 0 && (file_exists(storage_path('covers/anime/' . $anime->anidbid . '-cover.webp')) || file_exists(storage_path('covers/anime/' . $anime->anidbid . '-cover.jpg')));
                                @endphp
                                @if($hasCover)
                                    <img width="48" height="64" src="{{ getImageAssetUrl('anime', $anime->anidbid . '-cover', url('/covers/anime/no-cover.jpg'), [(string) $anime->anidbid]) }}"
                                         alt="{{ $anime->title }}"
                                         class="h-16 w-12 object-cover rounded shadow"
                                         loading="lazy">
                                @else
                                    <div class="h-16 w-12 bg-gray-200 dark:bg-gray-700 rounded flex items-center justify-center">
                                        <i aria-hidden="true" class="fas fa-dragon text-gray-400 text-sm"></i>
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900 dark:text-gray-200">
                                    {{ $anime->title }}
                                </div>
                                @if(!empty($anime->description))
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 max-w-md line-clamp-2">
                                        {{ Str::limit(strip_tags($anime->description), 100) }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($anime->type)
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-200">
                                        {{ $anime->type }}
                                    </span>
                                @else
                                    <span class="text-gray-500 dark:text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $anime->startdate ?? '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $anime->enddate ?? '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($anime->rating)
                                    <div class="flex items-center">
                                        <i aria-hidden="true" class="fas fa-star text-amber-400 mr-1"></i>
                                        <span class="text-sm text-gray-900 dark:text-gray-200">{{ number_format($anime->rating / 100, 1) }}</span>
                                    </div>
                                @else
                                    <span class="text-gray-500 dark:text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                <a aria-label="Edit" href="{{ url('admin/anidb-edit/' . $anime->anidbid) }}"
                                   class="text-link dark:text-primary-400 hover:text-primary-900 dark:hover:text-primary-300"
                                   title="Edit">
                                    <i aria-hidden="true" class="fas fa-edit"></i>
                                </a>
                                <form action="{{ route('admin.anidb-delete', $anime->anidbid) }}" method="post" class="inline"
                                      x-data="confirmForm"
                                      data-message="Remove this AniDB ID from all of its releases?"
                                      data-title="Remove from Releases"
                                      data-type="danger"
                                      data-confirm-text="Remove"
                                      @submit.prevent="submit()">
                                    @csrf
                                    <button type="submit" aria-label="Remove from Releases" title="Remove from Releases"
                                            class="text-danger-600 dark:text-danger-400 hover:text-danger-900 dark:hover:text-danger-300">
                                        <i aria-hidden="true" class="fas fa-unlink"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        @php
                            $anidbEmptyTitle = $animetitle ? 'No anime found for "'.$animetitle.'"' : 'No anime available';
                        @endphp
                        <tr>
                            <td colspan="7" class="p-0">
                                <x-admin.empty-state :icon="$animetitle ? 'fas fa-search' : 'fas fa-dragon'" :title="$anidbEmptyTitle">
                                    @if($animetitle)
                                        <a href="{{ url('admin/anidb-list') }}" class="text-link dark:text-primary-400 hover:underline">
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
                    Showing {{ $anidblist->firstItem() ?? 0 }} to {{ $anidblist->lastItem() ?? 0 }} of {{ $anidblist->total() }} anime
                </div>
                <div>
                    {{ $anidblist->appends(['animetitle' => $animetitle])->links() }}
                </div>
            </div>
        </div>
    </x-admin.card>
</div>

@endsection
