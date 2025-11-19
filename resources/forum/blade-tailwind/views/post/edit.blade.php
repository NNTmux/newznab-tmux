@extends ('forum::layouts.main', ['breadcrumbs_append' => [trans('forum::posts.edit')]])

@section ('content')
    <div id="edit-post">
        <h2 class="text-3xl font-medium my-3 text-gray-900 dark:text-gray-100">{{ trans('forum::posts.edit') }} ({{ $thread->title }})</h2>

        <hr class="mb-4 border-gray-300 dark:border-gray-700">

        @if ($post->parent)
            <h3 class="text-gray-900 dark:text-gray-100">{{ trans('forum::general.response_to', ['item' => $post->parent->authorName]) }}...</h3>

            @include ('forum::post.partials.list', ['post' => $post->parent, 'single' => true])
        @endif

        <form method="POST" action="{{ Forum::route('post.update', $post) }}">
            @csrf
            @method('PATCH')

            <div class="mb-3">
                <x-forum::textarea name="content" class="w-full">{{ old('content') !== null ? old('content') : $post->content }}</x-forum::textarea>
            </div>

            <div class="flex items-center gap-4 justify-end">
                <a href="{{ URL::previous() }}" class="underline text-blue-500 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 transition-colors">{{ trans('forum::general.cancel') }}</a>
                <x-forum::button type="submit">{{ trans('forum::general.save') }}</x-forum::button>
            </div>
        </form>
    </div>
@stop
