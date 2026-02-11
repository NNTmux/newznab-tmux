{{-- Release Item Partial --}}
{{-- Props: $release (object with ->id, ->guid, ->searchname, ->size, ->postdate, ->adddate, ->haspreview), $layout, $index --}}

@props([
    'release',
    'layout' => 2,
    'index' => 0,
])

@if(($release->searchname ?? null) && ($release->guid ?? null))
    <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
        <div class="release-card-container {{ $layout == 1 ? 'flex flex-row items-start justify-between gap-4' : 'flex flex-col space-y-3' }}">
            <div class="release-info-wrapper {{ $layout == 1 ? 'flex-1 min-w-0' : '' }}">
                {{-- Release Name --}}
                <a href="{{ url('/details/' . $release->guid) }}"
                   class="text-sm text-gray-800 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400 font-medium block break-all"
                   title="{{ $release->searchname }}">
                    {{ $release->searchname }}
                </a>

                {{-- Info Badges --}}
                <div class="flex flex-wrap items-center gap-2 mt-2">
                    @if($release->size)
                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                            <i class="fas fa-hdd mr-1"></i>{{ number_format($release->size / 1073741824, 2) }} GB
                        </span>
                    @endif

                    @if($release->postdate)
                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                            <i class="fas fa-calendar-alt mr-1"></i>{{ userDate($release->postdate, 'M d, Y H:i') }}
                        </span>
                    @endif

                    @if($release->adddate)
                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                            <i class="fas fa-plus-circle mr-1"></i>{{ userDateDiffForHumans($release->adddate) }}
                        </span>
                    @endif

                    @if(($release->haspreview ?? 0) == 1)
                        <button type="button"
                                class="preview-badge inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200 hover:bg-purple-200 dark:hover:bg-purple-800 transition cursor-pointer"
                                data-guid="{{ $release->guid }}"
                                title="View preview image">
                            <i class="fas fa-image mr-1"></i> Preview
                        </button>
                    @endif
                </div>
            </div>

            {{-- Action Buttons --}}
            <div class="release-actions flex flex-wrap items-center gap-2 {{ $layout == 1 ? 'shrink-0' : 'mt-2' }}">
                <a href="{{ url('/getnzb/' . $release->guid) }}"
                   class="inline-flex items-center px-3 py-1 rounded text-xs font-medium bg-green-600 dark:bg-green-700 text-white hover:bg-green-700 dark:hover:bg-green-800 transition">
                    <i class="fas fa-download mr-1"></i> Download
                </a>

                <button type="button"
                        class="add-to-cart inline-flex items-center px-3 py-1 rounded text-xs font-medium bg-blue-600 dark:bg-blue-700 text-white hover:bg-blue-700 dark:hover:bg-blue-800 transition"
                        data-guid="{{ $release->guid }}">
                    <i class="fas fa-shopping-cart mr-1"></i> Cart
                </button>

                <a href="{{ url('/details/' . $release->guid) }}"
                   class="inline-flex items-center px-3 py-1 rounded text-xs font-medium bg-gray-600 dark:bg-gray-700 text-white hover:bg-gray-700 dark:hover:bg-gray-800 transition">
                    <i class="fas fa-info-circle mr-1"></i> Details
                </a>

                @if($release->id)
                    <x-report-button :release-id="(int)$release->id" variant="icon" />
                @endif
            </div>
        </div>
    </div>
@endif
