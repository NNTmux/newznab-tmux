{{-- Release Item Partial --}}
{{-- Props: $releaseName, $releaseGuid, $releaseId, $releaseSize, $releasePostDate, $releaseAddDate, $hasPreview, $jpgStatus, $nfoStatus, $fromName, $layout, $index --}}

@props([
    'releaseName' => '',
    'releaseGuid' => '',
    'releaseId' => null,
    'releaseSize' => 0,
    'releasePostDate' => null,
    'releaseAddDate' => null,
    'hasPreview' => 0,
    'jpgStatus' => 0,
    'nfoStatus' => 0,
    'fromName' => '',
    'layout' => 2,
    'index' => 0,
])

@if($releaseName && $releaseGuid)
    <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
        <div class="release-card-container {{ $layout == 1 ? 'flex flex-row items-start justify-between gap-4' : 'flex flex-col space-y-3' }}">
            <div class="release-info-wrapper {{ $layout == 1 ? 'flex-1 min-w-0' : '' }}">
                {{-- Release Name --}}
                <a href="{{ url('/details/' . $releaseGuid) }}"
                   class="text-sm text-gray-800 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400 font-medium block break-all"
                   title="{{ $releaseName }}">
                    {{ $releaseName }}
                </a>

                {{-- Info Badges --}}
                <div class="flex flex-wrap items-center gap-2 mt-2">
                    @if($releaseSize)
                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                            <i class="fas fa-hdd mr-1"></i>{{ number_format($releaseSize / 1073741824, 2) }} GB
                        </span>
                    @endif

                    @if($releasePostDate)
                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                            <i class="fas fa-calendar-alt mr-1"></i>{{ userDate($releasePostDate, 'M d, Y H:i') }}
                        </span>
                    @endif

                    @if($releaseAddDate)
                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                            <i class="fas fa-plus-circle mr-1"></i>{{ userDateDiffForHumans($releaseAddDate) }}
                        </span>
                    @endif

                    @if($hasPreview == 1)
                        <button type="button"
                                class="preview-badge inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200 hover:bg-purple-200 dark:hover:bg-purple-800 transition cursor-pointer"
                                data-guid="{{ $releaseGuid }}"
                                title="View preview image">
                            <i class="fas fa-image mr-1"></i> Preview
                        </button>
                    @endif

                    @if($jpgStatus == 1)
                        <button type="button"
                                class="sample-badge inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 hover:bg-green-200 dark:hover:bg-green-800 transition cursor-pointer"
                                data-guid="{{ $releaseGuid }}"
                                title="View sample image">
                            <i class="fas fa-images mr-1"></i> Sample
                        </button>
                    @endif

                    @if($nfoStatus == 1)
                        <button type="button"
                                class="nfo-badge inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 hover:bg-yellow-200 dark:hover:bg-yellow-800 transition cursor-pointer"
                                data-guid="{{ $releaseGuid }}"
                                title="View NFO file">
                            <i class="fas fa-file-alt mr-1"></i> NFO
                        </button>
                    @endif

                    @if(!empty($fromName))
                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200"
                              title="Poster/Uploader">
                            <i class="fas fa-user mr-1"></i> {{ $fromName }}
                        </span>
                    @endif
                </div>
            </div>

            {{-- Action Buttons --}}
            <div class="release-actions flex flex-wrap items-center gap-2 {{ $layout == 1 ? 'shrink-0' : 'mt-2' }}">
                <a href="{{ url('/getnzb/' . $releaseGuid) }}"
                   class="inline-flex items-center px-3 py-1 rounded text-xs font-medium bg-green-600 dark:bg-green-700 text-white hover:bg-green-700 dark:hover:bg-green-800 transition">
                    <i class="fas fa-download mr-1"></i> Download
                </a>

                <button type="button"
                        class="add-to-cart inline-flex items-center px-3 py-1 rounded text-xs font-medium bg-blue-600 dark:bg-blue-700 text-white hover:bg-blue-700 dark:hover:bg-blue-800 transition"
                        data-guid="{{ $releaseGuid }}">
                    <i class="fas fa-shopping-cart mr-1"></i> Cart
                </button>

                <a href="{{ url('/details/' . $releaseGuid) }}"
                   class="inline-flex items-center px-3 py-1 rounded text-xs font-medium bg-gray-600 dark:bg-gray-700 text-white hover:bg-gray-700 dark:hover:bg-gray-800 transition">
                    <i class="fas fa-info-circle mr-1"></i> Details
                </a>

                @if($releaseId)
                    <x-report-button :release-id="(int)$releaseId" variant="icon" />
                @endif
            </div>
        </div>
    </div>
@endif


