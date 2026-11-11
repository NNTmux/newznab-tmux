            <!-- Comments Section -->
            <div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-3 flex items-center">
                    <i aria-hidden="true" class="fas fa-comments mr-2 text-link dark:text-primary-400"></i>
                    Comments ({{ isset($comments) ? count($comments) : 0 }})
                </h3>

                {{-- Success/error messages are shown as toasts by the layout. --}}
                <!-- Add Comment Form -->
                @auth
                    <div class="surface-panel rounded-lg p-4 mb-4 border shadow-sm">
                        <form method="POST" action="{{ url('/details/' . $release->guid) }}" id="commentForm">
                            @csrf
                            <div class="mb-3">
                                <label for="txtAddComment" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Add a Comment
                                </label>
                                <textarea
                                    name="txtAddComment"
                                    id="txtAddComment"
                                    rows="2"
                                    maxlength="2000"
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500"
                                    placeholder="Share your thoughts about this release..."
                                    required
                                ></textarea>
                            </div>
                            <div class="flex justify-end">
                                <button
                                    type="submit"
                                    class="px-6 py-2 bg-accent dark:bg-accent-hover text-white rounded-lg hover:bg-accent-hover dark:hover:bg-primary-800 transition inline-flex items-center font-medium shadow-sm"
                                >
                                    <i aria-hidden="true" class="fas fa-paper-plane mr-2"></i>
                                    Post Comment
                                </button>
                            </div>
                        </form>
                    </div>
                @else
                    <div class="bg-warning-50 dark:bg-warning-900/20 border border-warning-200 dark:border-warning-800 rounded-lg p-4 mb-6">
                        <p class="text-sm text-warning-800 dark:text-warning-200">
                            <i aria-hidden="true" class="fas fa-info-circle mr-2"></i>
                            Please <a href="{{ route('login') }}" class="font-semibold underline hover:text-warning-900 dark:hover:text-warning-100">log in</a> to add a comment.
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
                                        <div class="w-10 h-10 bg-accent dark:bg-accent-hover rounded-full flex items-center justify-center text-white font-bold mr-3 shadow-sm">
                                            {{ strtoupper(substr($comment['username'] ?? 'U', 0, 1)) }}
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-800 dark:text-gray-200">{{ $comment['username'] ?? 'Anonymous' }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                <i aria-hidden="true" class="far fa-clock mr-1"></i>
                                                {{ userDateDiffForHumans($comment['created_at']) }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <p class="text-gray-700 dark:text-gray-300 leading-relaxed">{{ $comment['text'] ?? '' }}</p>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="detail-empty-comments text-sm text-gray-600 dark:text-gray-400">
                        <i aria-hidden="true" class="fas fa-comments mr-1.5"></i>No comments yet. Be the first to comment!
                    </p>
                @endif
            </div>
