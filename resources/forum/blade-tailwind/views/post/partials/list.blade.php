<div @if (!$post->trashed())id="post-{{ $post->sequence }}"@endif
    class="bg-white dark:bg-gray-800 border dark:border-gray-700 mb-2 rounded-md transition-colors {{ $post->trashed() || $thread->trashed() ? 'opacity-50' : '' }}"
    :class="{ 'border-blue-500 dark:border-blue-400': state.selectedPosts.includes({{ $post->id }}) }">
    <div class="bg-gray-100 dark:bg-gray-700 border-b dark:border-gray-600 px-6 py-4 flex justify-between flex-row-reverse rounded-t-md transition-colors">
        @if (!isset($single) || !$single)
            <span class="float-end">
                <a href="{{ Forum::route('thread.show', $post) }}" class="text-blue-500 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 transition-colors">#{{ $post->sequence }}</a>
                @if ($post->sequence != 1)
                    @can ('deletePosts', $post->thread)
                        @can ('delete', $post)
                            <input type="checkbox" name="posts[]" :value="{{ $post->id }}" v-model="state.selectedPosts" class="ml-2 rounded border-gray-300 dark:border-gray-600 text-blue-500 dark:text-blue-400 focus:ring-blue-500 dark:focus:ring-blue-400 dark:bg-gray-700" />
                        @endcan
                    @endcan
                @endif
            </span>
        @endif

        <div>
            <span class="text-gray-900 dark:text-gray-100">{{ $post->authorName }}</span>
            <span class="text-gray-500 dark:text-gray-400">
                @include ('forum::partials.timestamp', ['carbon' => $post->created_at])
                @if ($post->hasBeenUpdated())
                    ({{ trans('forum::general.last_updated') }} @include ('forum::partials.timestamp', ['carbon' => $post->updated_at]))
                @endif
            </span>
        </div>
    </div>
    <div class="p-6 text-gray-900 dark:text-gray-100">
        @if ($post->parent !== null)
            @include ('forum::post.partials.quote', ['post' => $post->parent])
        @endif

        @if ($post->trashed())
            @can ('viewTrashedPosts')
                {!! Forum::render($post->content) !!}
                <br>
            @endcan
            <x-forum::badge type="danger">{{ trans('forum::general.deleted') }}</x-forum::badge>
        @else
            {!! Forum::render($post->content) !!}
        @endif

        @if (!isset($single) || !$single)
            <div class="flex items-center gap-4 justify-end mt-4">
                @if (!$post->trashed())
                    <a href="{{ Forum::route('post.show', $post) }}" class="text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300 transition-colors">{{ trans('forum::general.permalink') }}</a>
                    @if ($post->sequence != 1)
                        @can ('deletePosts', $post->thread)
                            @can ('delete', $post)
                                <a href="{{ Forum::route('post.confirm-delete', $post) }}" class="text-red-500 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 transition-colors">{{ trans('forum::general.delete') }}</a>
                            @endcan
                        @endcan
                    @endif
                    @can ('edit', $post)
                        <a href="{{ Forum::route('post.edit', $post) }}" class="text-blue-500">{{ trans('forum::general.edit') }}</a>
                    @endcan
                    @can ('reply', $post->thread)
                        <a href="{{ Forum::route('post.create', $post) }}" class="text-blue-500">{{ trans('forum::general.reply') }}</a>
                    @endcan
                @else
                    @can ('restorePosts', $post->thread)
                        @can ('restore', $post)
                            <a href="{{ Forum::route('post.confirm-restore', $post) }}" class="card-link">{{ trans('forum::general.restore') }}</a>
                        @endcan
                    @endcan
                @endif
            </div>
        @endif
    </div>
</div>
