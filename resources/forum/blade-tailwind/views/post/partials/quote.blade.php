<div class="bg-white dark:bg-gray-800 rounded-md border dark:border-gray-700 mb-2 transition-colors">
    <div class="p-6">
        <div class="flex justify-between flex-row-reverse mb-2">
            <span>
                <a href="{{ Forum::route('thread.show', $post) }}" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 transition-colors">#{{ $post->sequence }}</a>
            </span>
            <div>
                <strong class="text-gray-900 dark:text-gray-100">{{ $post->authorName }}</strong> <span class="text-gray-500 dark:text-gray-400">{{ $post->posted }}</span>
            </div>
        </div>
        <div class="text-gray-900 dark:text-gray-100">
            {!! \Illuminate\Support\Str::limit(Forum::render($post->content)) !!}
        </div>
    </div>
</div>
