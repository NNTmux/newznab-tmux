<div class="p-4" data-series-season-content>
    @forelse($seasons as $seasonNumber => $episodes)
        <div class="season-content" data-season="{{ $seasonNumber }}">
            @foreach($episodes as $episodeNumber => $releases)
                <div class="mb-4 pb-4 border-b border-gray-200 dark:border-gray-700 last:border-b-0">
                    <h6 class="font-semibold text-gray-700 dark:text-gray-300 mb-2">
                        Episode {{ $episodeNumber }}
                    </h6>
                    <div class="space-y-2">
                        @foreach($releases as $release)
                            <div class="series-episode-card flex items-center gap-3 bg-gray-50 dark:bg-gray-900 rounded-lg p-3 hover:bg-gray-100 dark:hover:bg-gray-800">
                                <div class="shrink-0">
                                    <input type="checkbox" class="chkRelease rounded border-gray-300 dark:border-gray-600 text-primary-600 dark:text-primary-500 focus:ring-primary-500 dark:focus:ring-primary-400 dark:bg-gray-700" name="release[]" value="{{ $release->guid }}" @change="onCheckboxChange()">
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <a href="{{ url('/details/' . $release->guid) }}"
                                           class="text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300 font-medium wrap-break-word break-all">
                                            {{ $release->searchname }}
                                        </a>
                                        @if(($release->failed_count ?? 0) > 0)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800"
                                                  title="{{ $release->failed_count }} user(s) reported download failure">
                                                <i class="fas fa-exclamation-triangle mr-1"></i> Failed ({{ $release->failed_count }})
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1 flex flex-wrap gap-2">
                                        <span class="mr-3">
                                            <i class="fa fa-hdd-o mr-1"></i>{{ formatBytes($release->size) }}
                                        </span>
                                        <span>
                                            <i class="fa fa-clock-o mr-1"></i>Added: {{ userDateDiffForHumans($release->adddate) }}
                                        </span>
                                        @if(!empty($release->postdate))
                                            <span>
                                                <i class="fas fa-calendar mr-1"></i> Posted: {{ userDate($release->postdate, 'M d, Y H:i') }}
                                            </span>
                                        @endif
                                        @if(!empty($release->fromname))
                                            <span class="inline-flex items-center px-2 py-0.5 rounded bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200 font-mono">
                                                <i class="fas fa-user mr-1"></i>{{ $release->fromname }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <x-button-link href="{{ url('/getnzb?id=' . $release->guid) }}"
                                       variant="success"
                                       size="sm"
                                       icon="fa fa-download"
                                       class="download-nzb"
                                       title="Download NZB">
                                    </x-button-link>
                                    <x-button-link href="{{ url('/details/' . $release->guid) }}"
                                       size="sm"
                                       icon="fa fa-info-circle"
                                       title="View Details">
                                    </x-button-link>
                                    <x-button-link href="#"
                                       variant="muted"
                                       size="sm"
                                       icon="icon_cart fa fa-shopping-basket"
                                       class="add-to-cart"
                                       data-guid="{{ $release->guid }}"
                                       title="Add to cart">
                                    </x-button-link>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @empty
        <div class="bg-primary-50 border border-primary-200 rounded-lg p-4 text-primary-800">
            <i class="fa fa-info-circle mr-2"></i>
            No releases found on this page for the selected season.
        </div>
    @endforelse
</div>
