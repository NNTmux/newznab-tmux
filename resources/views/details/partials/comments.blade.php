            <!-- Comments Section -->
            <div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                    <i class="fas fa-comments mr-2 text-primary-600 dark:text-primary-400"></i>
                    Comments ({{ isset($comments) ? count($comments) : 0 }})
                </h3>

                <!-- Flash Messages -->
                @if(session('success'))
                    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 mb-4">
                        <p class="text-sm text-green-800 dark:text-green-200 flex items-center">
                            <i class="fas fa-check-circle mr-2"></i>
                            {{ session('success') }}
                        </p>
                    </div>
                @endif

                @if(session('error'))
                    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-4">
                        <p class="text-sm text-red-800 dark:text-red-200 flex items-center">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            {{ session('error') }}
                        </p>
                    </div>
                @endif

                <!-- Add Comment Form -->
                @auth
                    <div class="surface-panel rounded-lg p-4 mb-6 border shadow-sm">
                        <form method="POST" action="{{ url('/details/' . $release->guid) }}" id="commentForm">
                            @csrf
                            <div class="mb-3">
                                <label for="txtAddComment" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Add a Comment
                                </label>
                                <textarea
                                    name="txtAddComment"
                                    id="txtAddComment"
                                    rows="4"
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500"
                                    placeholder="Share your thoughts about this release..."
                                    required
                                ></textarea>
                            </div>
                            <div class="flex justify-end">
                                <x-button
                                    type="submit"
                                    class="shadow-sm"
                                    icon="fas fa-paper-plane"
                                >
                                    Post Comment
                                </x-button>
                            </div>
                        </form>
                    </div>
                @else
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 mb-6">
                        <p class="text-sm text-yellow-800 dark:text-yellow-200">
                            <i class="fas fa-info-circle mr-2"></i>
                            Please <a href="{{ route('login') }}" class="font-semibold underline hover:text-yellow-900 dark:hover:text-yellow-100">log in</a> to add a comment.
                        </p>
                    </div>
                @endauth

                <!-- Comments List -->
                @if(isset($comments) && count($comments) > 0)
                    <div class="space-y-4">
                        @foreach($comments as $comment)
                            <div class="detail-comment-card surface-panel-alt rounded-lg p-4 border transition hover:border-gray-300 dark:hover:border-gray-600">
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-primary-600 dark:bg-primary-700 rounded-full flex items-center justify-center text-white font-bold mr-3 shadow-sm">
                                            {{ strtoupper(substr($comment['username'] ?? 'U', 0, 1)) }}
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-800 dark:text-gray-200">{{ $comment['username'] ?? 'Anonymous' }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                <i class="far fa-clock mr-1"></i>
                                                {{ \Carbon\Carbon::parse($comment['created_at'])->diffForHumans() }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <p class="text-gray-700 dark:text-gray-300 leading-relaxed">{{ $comment['text'] ?? '' }}</p>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="detail-empty-comments surface-panel-alt rounded-lg p-8 border text-center">
                        <i class="fas fa-comments text-4xl text-gray-400 dark:text-gray-600 mb-3"></i>
                        <p class="text-gray-500 dark:text-gray-400">No comments yet. Be the first to comment!</p>
                    </div>
                @endif
            </div>
