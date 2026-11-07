<div class="max-w-4xl mx-auto">
    <x-page-header :title="ucfirst($type ?? 'add') . ' TV show'" eyebrow="Series watchlist" description="Choose how this show should be organized in your followed collection." icon="fas fa-tv">
        <x-slot:actions>
            <a href="{{ url('/myshows') }}" class="workspace-hero__action"><i class="fas fa-arrow-left" aria-hidden="true"></i>My shows</a>
        </x-slot:actions>
    </x-page-header>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
        <div class="p-6">
            <div class="mb-6">
                <div class="flex items-center gap-4 mb-4">
                    <img width="96" height="144" class="rounded-lg shadow-md w-24 h-auto aspect-[2/3] object-cover"
                         src="{{ getImageAssetUrl('tvshows', $video . '_thumb', url('/covers/tvshows/no-cover.jpg')) }}"
                         data-fallback-src="{{ url('/covers/tvshows/no-cover.jpg') }}"
                         loading="lazy"
                         alt="{{ e($show['title'] ?? '') }}" />

                    <div>
                        <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">
                            {{ ucfirst($type ?? 'add') }} "{{ e($show['title'] ?? '') }}" to watchlist
                        </h4>
                        <p class="text-gray-600 dark:text-gray-400 text-sm">Select categories below to organize this show in your collection.</p>
                    </div>
                </div>

                <div class="bg-info-50 dark:bg-info-950/40 border-l-4 border-info-500 rounded-lg p-4">
                    <div class="flex items-start">
                        <i aria-hidden="true" class="fa-solid fa-info-circle text-info-600 dark:text-info-400 mt-0.5 mr-3"></i>
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            Adding shows to your watchlist will notify you through your
                            <a href="{{ url("/rss/myshows?dl=1&i={$userdata->id}&api_token={$userdata->api_token}") }}"
                               class="font-semibold text-info-600 dark:text-info-400 hover:text-info-800 dark:hover:text-info-300 underline inline-flex items-center">
                                <i aria-hidden="true" class="fa-solid fa-rss mr-1"></i>RSS Feed
                            </a>
                            when new episodes become available.
                        </p>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ url("myshows?action=do{$type}") }}" id="myshows" class="space-y-6">
                @csrf
                <input type="hidden" name="id" value="{{ $video }}"/>
                @if(!empty($from))
                    <input type="hidden" name="from" value="{{ $from }}" />
                @endif

                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-3">Choose Categories:</label>
                    <div class="flex flex-wrap gap-3" id="category-container">
                        @foreach($cat_ids ?? [] as $index => $cat_id)
                            <label class="inline-flex items-center px-4 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800 transition-all duration-200 has-checked:bg-primary-50 has-checked:border-primary-500 has-checked:text-link">
                                <input aria-label="Select item" type="checkbox"
                                       id="category_{{ $cat_id }}"
                                       name="category[]"
                                       value="{{ $cat_id }}"
                                       class="mr-2 rounded text-link dark:text-primary-400 focus:ring-primary-500"
                                       @if(in_array($cat_id, $cat_selected ?? [])) checked @endif>
                                <span class="text-sm font-medium">{{ $cat_names[$cat_id] ?? '' }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="flex gap-3 pt-4 border-t border-gray-200">
                    <button class="px-6 py-3 bg-accent dark:bg-accent-hover text-white rounded-lg hover:bg-accent-hover dark:hover:bg-primary-800 shadow-md hover:shadow-lg transition-all duration-200 inline-flex items-center font-medium"
                            type="submit" name="{{ $type ?? 'add' }}">
                        <i aria-hidden="true" class="fa {{ ($type ?? 'add') == 'add' ? 'fa-plus' : 'fa-edit' }} mr-2"></i>{{ ucfirst($type ?? 'add') }} Show
                    </button>
                    <a href="{{ url('/myshows') }}"
                       class="px-6 py-3 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-2 border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-900 hover:border-gray-400 shadow-md hover:shadow-lg transition-all duration-200 inline-flex items-center font-medium">
                        <i aria-hidden="true" class="fa-solid fa-arrow-left mr-2"></i>Back to My Shows
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
