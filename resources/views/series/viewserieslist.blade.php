@extends('layouts.main')

@section('content')
<div class="surface-panel rounded-xl shadow-sm">
    <!-- Header -->
    <div class="surface-panel-alt px-6 py-4 border-b">
        <div class="flex justify-between items-center">
            <h3 class="text-2xl font-bold text-gray-800 dark:text-gray-200 flex items-center">
                <i class="fa fa-tv mr-3 text-primary-600 dark:text-primary-400"></i>TV Series
            </h3>
            <nav aria-label="breadcrumb">
                <ol class="flex items-center space-x-2 text-sm text-gray-600 dark:text-gray-400">
                    <li><a href="{{ url($site['home_link'] ?? '/') }}" class="hover:text-primary-600 dark:hover:text-primary-400">Home</a></li>
                    <li><i class="fas fa-chevron-right text-xs mx-2"></i></li>
                    <li class="text-gray-500 dark:text-gray-400">TV Series List</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="px-6 py-4">
        <!-- Alphabet navigation -->
        <div class="mb-4">
            <div class="flex items-center flex-wrap gap-2">
                <span class="font-semibold mr-2 text-gray-800 dark:text-gray-200">Jump to:</span>
                <div class="flex gap-1">
                    <a href="{{ url('/series/0-9') }}" class="px-3 py-1 rounded {{ $seriesletter == '0-9' ? 'bg-primary-600 dark:bg-primary-700 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}">0-9</a>
                    @foreach($seriesrange as $range)
                        <a href="{{ url('/series/' . $range) }}" class="px-3 py-1 rounded {{ $range == $seriesletter ? 'bg-primary-600 dark:bg-primary-700 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}">{{ $range }}</a>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Action buttons and search -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-4">
            <div class="flex gap-2">
                <a href="{{ route('trending-tv') }}" class="inline-flex items-center px-4 py-2 bg-linear-to-r from-orange-500 to-red-600 text-white rounded-lg hover:from-orange-600 hover:to-red-700 transition shadow-md">
                    <i class="fas fa-fire mr-2"></i> View Trending TV Shows
                </a>
                <x-button-link icon="fa fa-list" href="{{ route('myshows') }}" title="List my watched shows">My Shows</x-button-link>
                <x-button-link variant="success" icon="fa fa-search" href="{{ url('/myshows/browse') }}" title="Browse your shows">Find My Shows</x-button-link>
            </div>

            <!-- Search form -->
            <form method="get" action="{{ url('/series') }}" class="flex gap-2">
                <input class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500"
                       type="text"
                       name="title"
                       value="{{ $showname ?? '' }}"
                       placeholder="Search series">
                <x-button type="submit" icon="fa fa-search" title="Search series" aria-label="Search series"></x-button>
            </form>
        </div>

        <!-- Series list -->
        @if(count($serieslist) > 0)
            @foreach($serieslist as $sletter => $series)
                <div class="mb-6">
                    <div class="surface-panel-alt px-4 py-2 rounded-t-lg border border-b-0">
                        <h4 class="text-xl font-bold text-gray-800 dark:text-gray-200 flex items-center">
                            <i class="fa fa-bookmark mr-2 text-primary-600 dark:text-primary-400"></i>{{ $sletter }}
                        </h4>
                    </div>

                    <!-- Desktop Table -->
                    <div class="hidden md:block overflow-x-auto">
                        <table class="min-w-full border border-gray-200 dark:border-gray-700">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-200">Name</th>
                                    <th class="px-4 py-3 text-center text-sm font-semibold text-gray-700 dark:text-gray-200 w-28">Network</th>
                                    <th class="px-4 py-3 text-center text-sm font-semibold text-gray-700 dark:text-gray-200 w-28">Country</th>
                                    <th class="px-4 py-3 text-center text-sm font-semibold text-gray-700 dark:text-gray-200 w-32">Actions</th>
                                    <th class="px-4 py-3 text-center text-sm font-semibold text-gray-700 dark:text-gray-200 w-48">External Links</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($series as $s)
                                    @php $sData = is_object($s) ? get_object_vars($s) : $s; @endphp
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-4 py-3">
                                            <div class="mb-1">
                                                <a class="font-semibold text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300"
                                                   title="View series details"
                                                   href="{{ url('/series/' . $sData['id']) }}">
                                                    {{ $sData['title'] ?? '' }}
                                                </a>
                                            </div>
                                            @if(!empty($sData['prevdate']))
                                                <span class="inline-block px-2 py-1 text-xs bg-primary-100 dark:bg-primary-900/50 text-primary-800 dark:text-primary-200 rounded">
                                                    <i class="fa fa-calendar mr-1"></i>Last: {{ $sData['previnfo'] ?? '' }} aired {{ \Carbon\Carbon::parse($sData['prevdate'])->format('M d, Y') }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            @if(!empty($sData['publisher']))
                                                <span class="inline-block px-2 py-1 text-xs bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded">{{ $sData['publisher'] }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            @if(!empty($sData['countries_id']))
                                                <span class="inline-block px-2 py-1 text-xs bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded">{{ $sData['countries_id'] }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            @if(!empty($sData['userseriesid']))
                                                <div class="flex justify-center gap-1">
                                                    <x-button-link href="{{ url('/myshows?action=edit&id=' . $sData['id'] . '&from=' . urlencode(request()->fullUrl())) }}"
                                                       variant="warning" size="sm" icon="fa fa-edit" title="Edit this show" aria-label="Edit this show"></x-button-link>
                                                    <x-button-link href="{{ url('/myshows?action=delete&id=' . $sData['id'] . '&from=' . urlencode(request()->fullUrl())) }}"
                                                       variant="danger" size="sm" icon="fa fa-trash" title="Remove from My Shows" aria-label="Remove from My Shows"></x-button-link>
                                                </div>
                                            @else
                                                <x-button-link href="{{ url('/myshows?action=add&id=' . $sData['id'] . '&from=' . urlencode(request()->fullUrl())) }}"
                                                   variant="success" size="sm" icon="fa fa-plus" title="Add to My Shows">Add</x-button-link>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex justify-center gap-2">
                                                <a class="px-2 py-1 bg-primary-100 dark:bg-primary-900/50 text-primary-700 dark:text-primary-300 rounded hover:bg-primary-200 dark:hover:bg-primary-800 text-sm"
                                                   title="View series details" href="{{ url('/series/' . $sData['id']) }}">
                                                    <i class="fa fa-tv"></i>
                                                </a>
                                                @if($sData['id'] > 0)
                                                    @if(!empty($sData['tvdb']) && $sData['tvdb'] > 0)
                                                        <a class="px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-200 dark:hover:bg-gray-600 text-xs"
                                                           title="View at TVDB" target="_blank" href="{{ $site['dereferrer_link'] }}http://thetvdb.com/?tab=series&id={{ $sData['tvdb'] }}">TVDB</a>
                                                    @endif
                                                    @if(!empty($sData['tvmaze']) && $sData['tvmaze'] > 0)
                                                        <a class="px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-200 dark:hover:bg-gray-600 text-xs"
                                                           title="View at TVMaze" target="_blank" href="{{ $site['dereferrer_link'] }}http://tvmaze.com/shows/{{ $sData['tvmaze'] }}">TVMaze</a>
                                                    @endif
                                                    @if(!empty($sData['trakt']) && $sData['trakt'] > 0)
                                                        <a class="px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-200 dark:hover:bg-gray-600 text-xs"
                                                           title="View at Trakt" target="_blank" href="{{ $site['dereferrer_link'] }}http://www.trakt.tv/shows/{{ $sData['trakt'] }}">Trakt</a>
                                                    @endif
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Cards -->
                    <div class="md:hidden border border-gray-200 dark:border-gray-700 rounded-b-lg divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($series as $s)
                            @php $sData = is_object($s) ? get_object_vars($s) : $s; @endphp
                            <div class="p-4 space-y-2">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0 flex-1">
                                        <a class="font-semibold text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300 text-sm"
                                           href="{{ url('/series/' . $sData['id']) }}">
                                            {{ $sData['title'] ?? '' }}
                                        </a>
                                        <div class="flex flex-wrap gap-1.5 mt-1">
                                            @if(!empty($sData['publisher']))
                                                <span class="px-2 py-0.5 text-xs bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded">{{ $sData['publisher'] }}</span>
                                            @endif
                                            @if(!empty($sData['countries_id']))
                                                <span class="px-2 py-0.5 text-xs bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded">{{ $sData['countries_id'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    @if(!empty($sData['userseriesid']))
                                        <div class="flex gap-1 shrink-0">
                                            <x-button-link href="{{ url('/myshows?action=edit&id=' . $sData['id'] . '&from=' . urlencode(request()->fullUrl())) }}"
                                               variant="warning" size="sm" icon="fa fa-edit" title="Edit" aria-label="Edit"></x-button-link>
                                            <x-button-link href="{{ url('/myshows?action=delete&id=' . $sData['id'] . '&from=' . urlencode(request()->fullUrl())) }}"
                                               variant="danger" size="sm" icon="fa fa-trash" title="Remove" aria-label="Remove"></x-button-link>
                                        </div>
                                    @else
                                        <x-button-link href="{{ url('/myshows?action=add&id=' . $sData['id'] . '&from=' . urlencode(request()->fullUrl())) }}"
                                           variant="success" size="sm" icon="fa fa-plus" class="shrink-0" title="Add to My Shows">Add</x-button-link>
                                    @endif
                                </div>
                                @if(!empty($sData['prevdate']))
                                    <span class="inline-block px-2 py-0.5 text-xs bg-primary-100 dark:bg-primary-900/50 text-primary-800 dark:text-primary-200 rounded">
                                        <i class="fa fa-calendar mr-1"></i>Last: {{ $sData['previnfo'] ?? '' }} aired {{ \Carbon\Carbon::parse($sData['prevdate'])->format('M d, Y') }}
                                    </span>
                                @endif
                                @if($sData['id'] > 0)
                                    <div class="flex flex-wrap gap-1.5">
                                        @if(!empty($sData['tvdb']) && $sData['tvdb'] > 0)
                                            <a class="px-2 py-0.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded text-xs" target="_blank"
                                               href="{{ $site['dereferrer_link'] }}http://thetvdb.com/?tab=series&id={{ $sData['tvdb'] }}">TVDB</a>
                                        @endif
                                        @if(!empty($sData['tvmaze']) && $sData['tvmaze'] > 0)
                                            <a class="px-2 py-0.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded text-xs" target="_blank"
                                               href="{{ $site['dereferrer_link'] }}http://tvmaze.com/shows/{{ $sData['tvmaze'] }}">TVMaze</a>
                                        @endif
                                        @if(!empty($sData['trakt']) && $sData['trakt'] > 0)
                                            <a class="px-2 py-0.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded text-xs" target="_blank"
                                               href="{{ $site['dereferrer_link'] }}http://www.trakt.tv/shows/{{ $sData['trakt'] }}">Trakt</a>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @else
            <div class="bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-700 rounded-lg p-4 text-yellow-800 dark:text-yellow-200">
                <i class="fa fa-info-circle mr-2"></i>
                No series found. Try a different search or letter.
            </div>
        @endif
    </div>
</div>
@endsection

