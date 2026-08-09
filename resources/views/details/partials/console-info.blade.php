            <!-- Console Information -->
            @if(!empty($con))
                @php
                    $conData = is_object($con) ? get_object_vars($con) : $con;
                    $conTitle = $conData['title'] ?? ($con->title ?? null);
                    $conPublisher = $conData['publisher'] ?? ($con->publisher ?? null);
                    $conReleaseDate = $conData['releasedate'] ?? ($con->releasedate ?? null);
                @endphp
                <div class="surface-panel-alt rounded-lg p-6 border">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                        <i class="fas fa-gamepad mr-2 text-primary-600 dark:text-primary-400"></i> Console Game Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if(!empty($conTitle))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Title</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $conTitle }}</dd>
                            </div>
                        @endif
                        @if(!empty($conPublisher))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Publisher</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $conPublisher }}</dd>
                            </div>
                        @endif
                        @if(!empty($conReleaseDate))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Release Date</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $conReleaseDate }}</dd>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
