<div class="max-w-4xl mx-auto">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                <h3 class="text-xl font-bold text-white flex items-center">
                    <i class="fa fa-tv mr-2"></i>{{ ucfirst($type ?? 'add') }} Show to Watchlist
                </h3>
                <nav aria-label="breadcrumb">
                    <ol class="flex items-center space-x-2 text-sm text-blue-100">
                        <li><a href="{{ url($site['home_link']) }}" class="hover:text-white transition">Home</a></li>
                        <li><i class="fas fa-chevron-right text-xs"></i></li>
                        <li><a href="{{ url('/myshows') }}" class="hover:text-white transition">My Shows</a></li>
                        <li><i class="fas fa-chevron-right text-xs"></i></li>
                        <li class="text-white font-medium">{{ ucfirst($type ?? 'add') }} Show</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="p-6">
            <div class="mb-6">
                <div class="flex items-center gap-4 mb-4">
                    <img class="rounded-lg shadow-md w-24 h-auto"
                         src="{{ url("/covers/tvshows/{$video}_thumb.jpg") }}"
                         data-fallback-src="{{ url('/covers/tvshows/no-cover.jpg') }}"
                         alt="{{ e($show['title'] ?? '') }}" />

                    <div>
                        <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">
                            {{ ucfirst($type ?? 'add') }} "{{ e($show['title'] ?? '') }}" to watchlist
                        </h4>
                        <p class="text-gray-600 dark:text-gray-400 text-sm">Select categories below to organize this show in your collection.</p>
                    </div>
                </div>

                <div class="bg-blue-50 border-l-4 border-blue-500 rounded-lg p-4">
                    <div class="flex items-start">
                        <i class="fa fa-info-circle text-blue-600 dark:text-blue-400 mt-0.5 mr-3"></i>
                        <p class="text-sm text-gray-700">
                            Adding shows to your watchlist will notify you through your
                            <a href="{{ url("/rss/myshows?dl=1&i={$userdata->id}&api_token={$userdata->api_token}") }}"
                               class="font-semibold text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 underline inline-flex items-center">
                                <i class="fa fa-rss mr-1"></i>RSS Feed
                            </a>
                            when new episodes become available.
                        </p>
                    </div>
                </div>
            </div>

            {{ html()->form()->method('POST')->action(url("myshows?action=do{$type}"))->id('myshows')->class('space-y-6')->open() }}
                <input type="hidden" name="id" value="{{ $video }}"/>
                @if(!empty($from))
                    <input type="hidden" name="from" value="{{ $from }}" />
                @endif

                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-3">Choose Categories:</label>
                    <div class="flex flex-wrap gap-3" id="category-container">
                        @foreach($cat_ids ?? [] as $index => $cat_id)
                            <label class="inline-flex items-center px-4 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg cursor-pointer hover:bg-gray-100 dark:bg-gray-800 transition-all duration-200 has-[:checked]:bg-blue-50 has-[:checked]:border-blue-500 has-[:checked]:text-blue-700">
                                <input type="checkbox"
                                       id="category_{{ $cat_id }}"
                                       name="category[]"
                                       value="{{ $cat_id }}"
                                       class="mr-2 rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500"
                                       @if(in_array($cat_id, $cat_selected ?? [])) checked @endif>
                                <span class="text-sm font-medium">{{ $cat_names[$cat_id] ?? '' }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="flex gap-3 pt-4 border-t border-gray-200">
                    <button class="px-6 py-3 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-800 shadow-md hover:shadow-lg transition-all duration-200 inline-flex items-center font-medium"
                            type="submit" name="{{ $type ?? 'add' }}">
                        <i class="fa {{ ($type ?? 'add') == 'add' ? 'fa-plus' : 'fa-edit' }} mr-2"></i>{{ ucfirst($type ?? 'add') }} Show
                    </button>
                    <a href="{{ url('/myshows') }}"
                       class="px-6 py-3 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-2 border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:bg-gray-900 hover:border-gray-400 shadow-md hover:shadow-lg transition-all duration-200 inline-flex items-center font-medium">
                        <i class="fa fa-arrow-left mr-2"></i>Back to My Shows
                    </a>
                </div>
            {{ html()->form()->close() }}
        </div>
    </div>
</div>


