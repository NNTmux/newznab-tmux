@extends('layouts.main')

@section('content')
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
    <!-- Breadcrumb -->
    <div class="px-6 py-4 border-b border-gray-200">
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="{{ url($site['home_link'] ?? '/') }}" class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:text-blue-400 inline-flex items-center">
                        <i class="fas fa-home mr-2"></i> Home
                    </a>
                </li>
                @if(!empty($catname) && is_object($catname) && !empty($catname->parent))
                    <li>
                        <div class="flex items-center">
                            <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                            <a href="{{ url('/browse/' . $catname->parent->title) }}" class="text-gray-700 dark:text-gray-300 hover:text-blue-600">{{ $catname->parent->title }}</a>
                        </div>
                    </li>
                    <li>
                        <div class="flex items-center">
                            <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                            <a href="{{ url('/browse/' . $catname->title) }}" class="text-gray-700 dark:text-gray-300 hover:text-blue-600">{{ $catname->title }}</a>
                        </div>
                    </li>
                @else
                    <li>
                        <div class="flex items-center">
                            <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                            <span class="text-gray-500">Console / {{ is_object($catname) ? $catname->title : $catname }}</span>
                        </div>
                    </li>
                @endif
            </ol>
        </nav>
    </div>

    @if($results->count() > 0)
        <form id="nzb_multi_operations_form" method="get">
            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-b border-gray-200">
                <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
                    <!-- Left Section -->
                    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                        <x-view-toggle
                            current-view="covers"
                            covgroup="console"
                            :category="$categorytitle ?? 'All'"
                            parentcat="Console"
                            :shows="false"
                        />

                        <div class="flex items-center gap-2">
                            <small class="text-gray-600">With Selected:</small>
                            <div class="flex gap-1">
                                <button type="button" class="nzb_multi_operations_download px-3 py-1 bg-green-600 dark:bg-green-700 text-white rounded hover:bg-green-700 dark:hover:bg-green-800 transition text-sm" title="Download NZBs">
                                    <i class="fa fa-cloud-download"></i>
                                </button>
                                <button type="button" class="nzb_multi_operations_cart px-3 py-1 bg-blue-600 dark:bg-blue-700 text-white rounded hover:bg-blue-700 dark:hover:bg-blue-800 transition text-sm" title="Send to Download Basket">
                                    <i class="fa fa-shopping-basket"></i>
                                </button>
                                @if(isset($isadmin) && $isadmin)
                                    <button type="button" class="nzb_multi_operations_edit px-3 py-1 bg-yellow-600 text-white rounded hover:bg-yellow-700 transition text-sm">Edit</button>
                                    <button type="button" class="nzb_multi_operations_delete px-3 py-1 bg-red-600 dark:bg-red-700 text-white rounded hover:bg-red-700 dark:hover:bg-red-800 transition text-sm">Delete</button>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div class="w-full lg:w-auto">
                        {{ $results->onEachSide(5)->links() }}
                    </div>
                </div>
            </div>

            <!-- Results Grid -->
            <div class="px-6 py-4">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    @foreach($resultsadd as $result)
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                            <div class="p-4">
                                <div class="grid grid-cols-12 gap-4">
                                    <!-- Console Cover Column -->
                                    <div class="col-span-12 sm:col-span-4">
                                        <div class="relative mb-3">
                                            <a href="{{ url('/details/' . $result->guid) }}">
                                                <img class="w-full rounded shadow"
                                                     src="{{ getReleaseCover($result) }}"
                                                     alt="{{ $result->title }}"/>
                                                @if(!empty($result->failed))
                                                    <div class="absolute top-2 right-2">
                                                        <span class="px-2 py-1 bg-red-600 dark:bg-red-700 text-white text-xs rounded-full">
                                                            <i class="fa fa-exclamation-circle mr-1"></i>Failed
                                                        </span>
                                                    </div>
                                                @endif
                                            </a>
                                        </div>

                                        <!-- External Links -->
                                        <div class="flex flex-wrap gap-1 mb-2">
                                            @if(!empty($result->url))
                                                <a target="_blank" href="{{ $site['dereferrer_link'] }}{{ $result->url }}"
                                                   title="View Game page" class="px-2 py-1 bg-gray-600 text-white text-xs rounded hover:bg-gray-700">
                                                   <i class="fa fa-shopping-cart mr-1"></i>Amazon
                                                </a>
                                            @endif

                                            @if($result->nfoid > 0)
                                                <a href="{{ url('/nfo/' . $result->guid) }}" data-guid="{{ $result->guid }}"
                                                   title="View NFO" class="modal_nfo px-2 py-1 bg-gray-600 text-white text-xs rounded hover:bg-gray-700">
                                                   <i class="fa fa-file-text mr-1"></i>NFO
                                                </a>
                                            @endif

                                            <a class="px-2 py-1 bg-gray-600 text-white text-xs rounded hover:bg-gray-700"
                                               href="{{ url('/browse/group?g=' . $result->group_name) }}"
                                               title="Browse releases in {{ str_replace('alt.binaries', 'a.b', $result->group_name) }}">
                                               <i class="fa fa-users mr-1"></i>Group
                                            </a>
                                        </div>
                                    </div>

                                    <!-- Console Info Column -->
                                    <div class="col-span-12 sm:col-span-8">
                                        <!-- Console Title -->
                                        <h5 class="text-lg font-semibold mb-2">
                                            <a class="text-gray-800 dark:text-gray-200 hover:text-blue-600" href="{{ url('/details/' . $result->guid) }}">
                                                {{ $result->title }}
                                            </a>
                                        </h5>

                                        <!-- Console Details -->
                                        <div class="mb-3 text-sm space-y-1">
                                            @if(isset($result->genre) && $result->genre != "")
                                                <div class="flex items-start">
                                                    <i class="fa fa-tags text-gray-400 mr-2 mt-1"></i>
                                                    <div class="flex flex-wrap gap-1">
                                                        @foreach(explode(', ', $result->genre) as $genre)
                                                            <span class="px-2 py-0.5 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded text-xs">{{ $genre }}</span>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif

                                            @if(isset($result->esrb) && $result->esrb != "")
                                                <div class="flex items-center text-gray-600">
                                                    <i class="fa fa-shield text-gray-400 mr-2"></i>
                                                    <span><strong>Rating:</strong> {{ $result->esrb }}</span>
                                                </div>
                                            @endif

                                            @if(isset($result->publisher) && $result->publisher != "")
                                                <div class="flex items-center text-gray-600">
                                                    <i class="fa fa-building text-gray-400 mr-2"></i>
                                                    <span><strong>Publisher:</strong> {{ $result->publisher }}</span>
                                                </div>
                                            @endif

                                            @if(isset($result->releasedate) && $result->releasedate != "")
                                                <div class="flex items-center text-gray-600">
                                                    <i class="fa fa-calendar text-gray-400 mr-2"></i>
                                                    <span><strong>Released:</strong> {{ \Carbon\Carbon::parse($result->releasedate)->format('M d, Y') }}</span>
                                                </div>
                                            @endif

                                            @if(isset($result->review) && $result->review != "")
                                                <div class="text-gray-600 dark:text-gray-400 line-clamp-3">
                                                    <i class="fa fa-quote-left text-gray-400 mr-2"></i>{{ $result->review }}
                                                </div>
                                            @endif
                                        </div>

                                        <!-- Release Info Card -->
                                        <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-3 border border-gray-200">
                                            <div class="flex justify-between items-start mb-2">
                                                <label class="inline-flex items-center">
                                                    <input type="checkbox" class="form-checkbox h-4 w-4 text-blue-600" value="{{ $result->guid }}" id="chksingle"/>
                                                </label>

                                                <div class="flex items-center text-sm">
                                                    <i class="fa fa-hdd-o text-gray-400 mr-2"></i>
                                                    <span class="px-2 py-1 bg-gray-600 text-white text-xs rounded">{{ formatBytes($result->size) }}</span>
                                                </div>
                                            </div>

                                            @if(!empty($result->adddate))
                                            <div class="flex items-center text-gray-500 text-sm mb-3">
                                                <i class="fa fa-clock-o mr-2"></i>
                                                <span>Added {{ userDateDiffForHumans($result->adddate) }}</span>
                                            </div>
                                            @endif

                                            <div class="flex flex-wrap gap-2 text-xs mb-3">
                                                @if(!empty($result->group_name))
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                                        <i class="fas fa-users mr-1"></i> {{ $result->group_name }}
                                                    </span>
                                                @endif
                                                @if(!empty($result->postdate))
                                                    <span>
                                                        <i class="fas fa-calendar mr-1"></i> Posted: {{ userDate($result->postdate, 'M d, Y H:i') }}
                                                    </span>
                                                @endif
                                                @if(!empty($result->fromname))
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200 font-mono">
                                                        <i class="fas fa-user mr-1"></i> {{ $result->fromname }}
                                                    </span>
                                                @endif
                                            </div>

                                            <!-- Action Buttons -->
                                            <div class="flex flex-wrap gap-2" id="guid{{ $result->guid }}">
                                                <a class="px-3 py-1.5 bg-blue-600 dark:bg-blue-700 text-white rounded hover:bg-blue-700 dark:hover:bg-blue-800 text-sm inline-flex items-center"
                                                   title="Download NZB"
                                                   href="{{ url('/getnzb?id=' . $result->guid) }}">
                                                    <i class="fa fa-cloud-download mr-1"></i>
                                                    <span class="px-1.5 py-0.5 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 text-xs rounded ml-1">{{ $result->grabs }}</span>
                                                </a>

                                                <a class="px-3 py-1.5 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-300 text-sm inline-flex items-center"
                                                   href="{{ url('/details/' . $result->guid . '#comments') }}">
                                                    <i class="fa fa-comment-o mr-1"></i>
                                                    <span class="px-1.5 py-0.5 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 text-xs rounded ml-1">{{ $result->comments }}</span>
                                                </a>

                                                <a href="#" class="add-to-cart px-3 py-1.5 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-300 text-sm"
                                                   data-guid="{{ $result->guid }}"
                                                   title="Send to my download basket">
                                                    <i class="icon_cart fa fa-shopping-basket"></i>
                                                </a>

                                                @if(!empty($result->failed))
                                                    <span class="px-3 py-1.5 bg-red-100 text-red-700 rounded text-sm">
                                                        <i class="fa fa-exclamation-triangle mr-1"></i>
                                                        {{ $result->failed }} Failed
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Footer -->
            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200">
                <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
                    <!-- Left Section -->
                    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                        <div class="text-sm">
                            <span class="text-gray-600">View: <strong>Covers</strong> |
                            <a href="{{ url('/browse/Console/' . ($categorytitle ?? '')) }}" class="text-blue-600 dark:text-blue-400 hover:text-blue-800">List</a></span>
                        </div>

                        <div class="flex items-center gap-2">
                            <small class="text-gray-600">With Selected:</small>
                            <div class="flex gap-1">
                                <button type="button" class="nzb_multi_operations_download px-3 py-1 bg-green-600 dark:bg-green-700 text-white rounded hover:bg-green-700 dark:hover:bg-green-800 transition text-sm" title="Download NZBs">
                                    <i class="fa fa-cloud-download"></i>
                                </button>
                                <button type="button" class="nzb_multi_operations_cart px-3 py-1 bg-blue-600 dark:bg-blue-700 text-white rounded hover:bg-blue-700 dark:hover:bg-blue-800 transition text-sm" title="Send to Download Basket">
                                    <i class="fa fa-shopping-basket"></i>
                                </button>
                                @if(isset($isadmin) && $isadmin)
                                    <button type="button" class="nzb_multi_operations_edit px-3 py-1 bg-yellow-600 text-white rounded hover:bg-yellow-700 transition text-sm">Edit</button>
                                    <button type="button" class="nzb_multi_operations_delete px-3 py-1 bg-red-600 dark:bg-red-700 text-white rounded hover:bg-red-700 dark:hover:bg-red-800 transition text-sm">Delete</button>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div class="w-full lg:w-auto">
                        {{ $results->onEachSide(5)->links() }}
                    </div>
                </div>
            </div>
        </form>
    @else
        <div class="px-6 py-8">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-blue-800">
                <i class="fa fa-info-circle mr-2"></i>
                No console releases with covers available!
            </div>
        </div>
    @endif
</div>
@endsection

@push('scripts')
@include('partials.cart-script')
@endpush

