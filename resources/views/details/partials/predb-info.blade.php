            <!-- PreDB Information -->
            @if(!empty($predb) && is_array($predb))
                <div class="surface-panel-alt rounded-lg p-6 border">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                        <i class="fas fa-database mr-2 text-primary-600"></i> PreDB Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if(!empty($predb['title']))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Title</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-mono">{{ $predb['title'] }}</dd>
                            </div>
                        @endif
                        @if(!empty($predb['source']))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Source</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $predb['source'] }}</dd>
                            </div>
                        @endif
                        @if(!empty($predb['predate']))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Pre Date</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $predb['predate'] }}</dd>
                            </div>
                        @endif
                        @if(!empty($predb['category']))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Category</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $predb['category'] }}</dd>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
