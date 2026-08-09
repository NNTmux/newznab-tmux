            <!-- Book Information -->
            @if(!empty($book))
                @php
                    $bookData = is_object($book) ? get_object_vars($book) : $book;
                    $bookTitle = $bookData['title'] ?? ($book->title ?? null);
                    $bookAuthor = $bookData['author'] ?? ($book->author ?? null);
                    $bookPublisher = $bookData['publisher'] ?? ($book->publisher ?? null);
                    $bookPublishDate = $bookData['publishdate'] ?? ($book->publishdate ?? null);
                    $bookOverview = $bookData['overview'] ?? ($book->overview ?? null);
                @endphp
                <div class="surface-panel-alt rounded-lg p-6 border">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                        <i class="fas fa-book mr-2 text-primary-600 dark:text-primary-400"></i> Book Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if(!empty($bookTitle))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Title</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $bookTitle }}</dd>
                            </div>
                        @endif
                        @if(!empty($bookAuthor))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Author</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $bookAuthor }}</dd>
                            </div>
                        @endif
                        @if(!empty($bookPublisher))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Publisher</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $bookPublisher }}</dd>
                            </div>
                        @endif
                        @if(!empty($bookPublishDate))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Published</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $bookPublishDate }}</dd>
                            </div>
                        @endif
                        @if(!empty($bookOverview))
                            <div class="md:col-span-2">
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Overview</dt>
                                <dd class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ $bookOverview }}</dd>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
