            <!-- Cover Image and Title -->
            <div class="border-b border-gray-200 dark:border-gray-700 pb-4">
                <div class="flex gap-4 mb-4">
                    <!-- Cover Image -->
                    <div class="shrink-0">
                        <img src="{{ getReleaseCover($release) }}"
                             alt="{{ $release->searchname }}"
                             class="detail-cover-image w-48 h-72 object-cover max-w-[192px] max-h-[288px]"
                             data-fallback-src="{{ asset('assets/images/no-cover.png') }}">
                    </div>

                    <!-- Title and Actions -->
                    <div class="flex-1">
                        <div class="mb-3">
                            <h2 class="text-xl font-bold text-gray-800 dark:text-gray-200 mb-2 wrap-break-word break-all">{{ $release->searchname }}</h2>
                            <div class="flex flex-wrap gap-2">
                                @if(!empty($totalReportCount) && $totalReportCount > 0)
                                    <div class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium bg-orange-100 dark:bg-orange-900 text-orange-800 dark:text-orange-200 border border-orange-200 dark:border-orange-800"
                                         title="Reported: {{ $allReportReasons ?? 'Unknown' }}">
                                        <i class="fas fa-flag mr-2"></i>
                                        <span>Reported ({{ $totalReportCount }}): {{ $allReportReasons ?? 'Unknown' }}</span>
                                    </div>
                                @endif
                                @if(!empty($failed) && $failed > 0)
                                    <div class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium bg-red-100 text-red-800 border border-red-200">
                                        <i class="fas fa-exclamation-triangle mr-2"></i>
                                        <span>{{ $failed }} user{{ $failed > 1 ? 's' : '' }} reported download failure</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ url('/getnzb/' . $release->guid) }}" class="download-nzb release-action release-action-download px-4 py-2">
                                <i class="fas fa-download mr-2"></i> Download NZB
                            </a>
                            <a href="#" class="add-to-cart release-action release-action-primary px-4 py-2" data-guid="{{ $release->guid }}">
                                <i class="icon_cart fas fa-shopping-basket mr-2"></i> Add to Cart
                            </a>
                            @if(isset($release->nfostatus) && $release->nfostatus == 1)
                                <button type="button" class="nfo-badge release-action release-action-primary px-4 py-2" data-guid="{{ $release->guid }}" title="View NFO file">
                                    <i class="fas fa-file-alt mr-2"></i> View NFO
                                </button>
                            @endif
                            @if(($release->totalpart ?? 0) > 0)
                                <button type="button" class="filelist-badge release-action release-action-muted px-4 py-2" data-guid="{{ $release->guid }}" title="View file list">
                                    <i class="fas fa-list mr-2"></i> View Files
                                </button>
                            @endif
                            @auth
                                @if(auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Moderator'))
                                    <a href="{{ route('admin.release-edit', ['id' => $release->guid]) }}" class="release-action release-action-primary px-4 py-2" title="Edit Release">
                                        <i class="fas fa-edit mr-2"></i> Edit Release
                                    </a>
                                @endif
                            @endauth
                            <x-report-button :release-id="$release->id" :reported-count="$totalReportCount ?? 0" variant="button-lg" />
                        </div>
                        @if(!empty($originalReportData) && $originalReportData->count() > 0)
                            <div class="mt-4 rounded-lg border border-orange-200 dark:border-orange-800 bg-orange-50 dark:bg-orange-900/20 p-4">
                                <h3 class="text-sm font-semibold text-orange-800 dark:text-orange-200 mb-3 flex items-center">
                                    <i class="fas fa-flag mr-2"></i> Original report
                                </h3>
                                <div class="space-y-3">
                                    @foreach($originalReportData as $originalReport)
                                        <div class="text-sm text-orange-900 dark:text-orange-100">
                                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 dark:bg-orange-900 text-orange-800 dark:text-orange-200 border border-orange-200 dark:border-orange-800">
                                                    {{ $originalReport->reason_label }}
                                                </span>
                                                <span class="text-xs text-orange-700 dark:text-orange-300">
                                                    {{ ucfirst($originalReport->status) }} · Reported {{ $originalReport->created_at->format('M d, Y H:i') }}
                                                </span>
                                            </div>
                                            <div class="whitespace-pre-wrap break-words">
                                                {{ $originalReport->description ?: 'No additional report details were provided.' }}
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        @if(!empty($publicReportResponses) && $publicReportResponses->count() > 0)
                            <div class="mt-4 rounded-lg border border-primary-200 dark:border-primary-800 bg-primary-50 dark:bg-primary-900/20 p-4">
                                <h3 class="text-sm font-semibold text-primary-800 dark:text-primary-200 mb-3 flex items-center">
                                    <i class="fas fa-reply mr-2"></i> Staff response
                                </h3>
                                <div class="space-y-3">
                                    @foreach($publicReportResponses as $responseReport)
                                        <div class="text-sm text-primary-900 dark:text-primary-100">
                                            <div class="whitespace-pre-wrap break-words">{{ $responseReport->response }}</div>
                                            <div class="mt-2 text-xs text-primary-700 dark:text-primary-300">
                                                {{ $responseReport->responded_at ? 'Responded ' . $responseReport->responded_at->format('M d, Y H:i') : 'Staff response' }}
                                                @if($responseReport->responder)
                                                    by {{ $responseReport->responder->username }}
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
