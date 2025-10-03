@extends('layouts.main')

@section('content')
<div class="bg-white rounded-lg shadow-sm">
    <!-- Breadcrumb -->
    <div class="px-6 py-4 border-b border-gray-200">
        <nav aria-label="breadcrumb">
            <ol class="flex items-center space-x-2 text-sm text-gray-600">
                <li><a href="{{ url($site->home_link ?? '/') }}" class="hover:text-blue-600">Home</a></li>
                <li><i class="fas fa-chevron-right text-xs mx-2"></i></li>
                <li><a href="{{ route('series') }}" class="hover:text-blue-600">TV Series</a></li>
                <li><i class="fas fa-chevron-right text-xs mx-2"></i></li>
                <li class="text-gray-500">{{ $seriestitles ?? '' }}</li>
            </ol>
        </nav>
    </div>

    <div class="px-6 py-4">
        @if(!empty($nodata))
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-yellow-800">
                <i class="fa fa-exclamation-circle mr-2"></i><strong>Sorry!</strong> {{ $nodata }}
            </div>
        @else
            <!-- Series Info Card -->
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm mb-4">
                <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                    <h5 class="text-lg font-semibold text-gray-800">
                        <i class="fa fa-tv mr-2"></i>{{ $seriestitles ?? '' }}
                        @if(!empty($show['publisher']))
                            ({{ $show['publisher'] }})
                        @endif
                    </h5>
                </div>
                <div class="p-4">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <!-- Series Image -->
                        <div class="text-center">
                            @if(!empty($show['image']) && $show['image'] != 0)
                                <img class="w-full rounded shadow max-h-[300px] object-cover"
                                     alt="{{ $seriestitles ?? '' }} Poster"
                                     src="{{ url('/covers/tvshows/' . $show['id'] . '.jpg') }}"/>
                            @endif

                            <!-- My Shows Controls -->
                            <div class="mt-3">
                                <div class="flex justify-center items-center gap-2">
                                    <span class="text-gray-600 mr-2">My Shows:</span>
                                    <div class="flex gap-1">
                                        @if(!empty($myshows) && !empty($myshows['id']))
                                            <a class="px-3 py-1 bg-yellow-500 text-white rounded hover:bg-yellow-600"
                                               title="Edit Categories"
                                               href="{{ url('/myshows?action=edit&id=' . $show['id'] . '&from=' . urlencode(request()->fullUrl())) }}">
                                                <i class="fa fa-pencil-alt"></i>
                                            </a>
                                            <a class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700"
                                               title="Remove"
                                               href="{{ url('/myshows?action=delete&id=' . $show['id'] . '&from=' . urlencode(request()->fullUrl())) }}">
                                                <i class="fa fa-minus"></i>
                                            </a>
                                        @else
                                            <a class="px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700"
                                               title="Add to My Shows"
                                               href="{{ url('/myshows?action=add&id=' . $show['id'] . '&from=' . urlencode(request()->fullUrl())) }}">
                                                <i class="fa fa-plus"></i>
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Series Info -->
                        <div class="md:col-span-3">
                            <p class="mb-4 text-gray-700">{{ $seriessummary ?? '' }}</p>

                            <!-- External Links -->
                            <div class="flex flex-wrap gap-2 mb-4">
                                <a class="px-3 py-2 bg-green-600 text-white rounded hover:bg-green-700 inline-flex items-center text-sm"
                                   href="{{ url('/rss/full-feed?show=' . $show['id'] . (isset($category) && $category != '' ? '&t=' . $category : '') . '&dl=1&i=' . auth()->id() . '&api_token=' . auth()->user()->api_token) }}">
                                    <i class="fa fa-rss mr-2"></i> RSS Feed
                                </a>

                                @if(!empty($show['tvdb']) && $show['tvdb'] > 0)
                                    <a class="px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 inline-flex items-center text-sm"
                                       target="_blank"
                                       href="{{ $site->dereferrer_link }}http://thetvdb.com/?tab=series&id={{ $show['tvdb'] }}"
                                       title="View at TheTVDB">
                                        <i class="fa fa-database mr-2"></i> TheTVDB
                                    </a>
                                @endif

                                @if(!empty($show['tvmaze']) && $show['tvmaze'] > 0)
                                    <a class="px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 inline-flex items-center text-sm"
                                       target="_blank"
                                       href="{{ $site->dereferrer_link }}http://tvmaze.com/shows/{{ $show['tvmaze'] }}"
                                       title="View at TVMaze">
                                        <i class="fa fa-tv mr-2"></i> TVMaze
                                    </a>
                                @endif

                                @if(!empty($show['trakt']) && $show['trakt'] > 0)
                                    <a class="px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 inline-flex items-center text-sm"
                                       target="_blank"
                                       href="{{ $site->dereferrer_link }}http://www.trakt.tv/shows/{{ $show['trakt'] }}"
                                       title="View at TraktTv">
                                        <i class="fa fa-film mr-2"></i> Trakt
                                    </a>
                                @endif

                                @if(!empty($show['tvrage']) && $show['tvrage'] > 0)
                                    <a class="px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 inline-flex items-center text-sm"
                                       target="_blank"
                                       href="{{ $site->dereferrer_link }}http://www.tvrage.com/shows/id-{{ $show['tvrage'] }}"
                                       title="View at TV Rage">
                                        <i class="fa fa-external-link-alt mr-2"></i> TV Rage
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Episodes by Season -->
            @if(!empty($seasons))
                @foreach($seasons as $seasonNumber => $episodes)
                    <div class="bg-white border border-gray-200 rounded-lg shadow-sm mb-4">
                        <div class="px-4 py-3 bg-gradient-to-r from-blue-50 to-indigo-50 border-b border-gray-200">
                            <h5 class="text-lg font-semibold text-gray-800">
                                <i class="fa fa-folder-open mr-2 text-blue-600"></i>
                                Season {{ $seasonNumber }}
                            </h5>
                        </div>
                        <div class="p-4">
                            @foreach($episodes as $episodeNumber => $releases)
                                <div class="mb-4 pb-4 border-b border-gray-200 last:border-b-0">
                                    <h6 class="font-semibold text-gray-700 mb-2">
                                        Episode {{ $episodeNumber }}
                                    </h6>
                                    <div class="space-y-2">
                                        @foreach($releases as $release)
                                            <div class="flex items-center justify-between bg-gray-50 rounded p-3 hover:bg-gray-100">
                                                <div class="flex-1">
                                                    <a href="{{ url('/details/' . $release->guid) }}"
                                                       class="text-blue-600 hover:text-blue-800 font-medium">
                                                        {{ $release->searchname }}
                                                    </a>
                                                    <div class="text-xs text-gray-500 mt-1">
                                                        <span class="mr-3">
                                                            <i class="fa fa-hdd-o mr-1"></i>{{ \App\Support\Helpers::formatBytes($release->size) }}
                                                        </span>
                                                        <span>
                                                            <i class="fa fa-clock-o mr-1"></i>{{ \Carbon\Carbon::parse($release->postdate)->diffForHumans() }}
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="flex gap-2">
                                                    <a href="{{ url('/getnzb?id=' . $release->guid) }}"
                                                       class="px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 text-sm"
                                                       title="Download NZB"
                                                       onclick="showToast('Downloading NZB...', 'success')">
                                                        <i class="fa fa-download"></i>
                                                    </a>
                                                    <a href="{{ url('/details/' . $release->guid) }}"
                                                       class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm"
                                                       title="View Details">
                                                        <i class="fa fa-info-circle"></i>
                                                    </a>
                                                    <a href="#" class="add-to-cart px-3 py-1 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 text-sm"
                                                       data-guid="{{ $release->guid }}"
                                                       title="Add to cart">
                                                        <i class="icon_cart fa fa-shopping-basket"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @else
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-blue-800">
                    <i class="fa fa-info-circle mr-2"></i>
                    No episodes/releases found for this series.
                </div>
            @endif
        @endif
    </div>
</div>
@endsection

@push('scripts')
@include('partials.cart-script')
@endpush

