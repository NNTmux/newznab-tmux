@extends ('forum::layouts.main', ['breadcrumbs_append' => [trans('forum::general.new_reply')]])

@section ('content')
    <div id="create-post">
        <h2 class="text-3xl font-medium my-3 text-gray-900 dark:text-gray-100">{{ trans('forum::general.new_reply') }} ({{ $thread->title }})</h2>

        @if ($post !== null && !$post->trashed())
            <div class="mb-2 text-gray-700 dark:text-gray-300">{{ trans('forum::general.replying_to', ['item' => $post->authorName]) }}:</div>

            @include ('forum::post.partials.quote')
        @endif

        <hr class="my-4 border-gray-300 dark:border-gray-700" />

        <form method="POST" action="{{ Forum::route('post.store', $thread) }}">
            {!! csrf_field() !!}

            @if ($post !== null)
                <input type="hidden" name="post" value="{{ $post->id }}" />
            @endif

            <div class="mb-3">
                <x-forum::textarea name="content" class="w-full min-h-48">{{ old('content') }}</x-forum::textarea>
            </div>

            <div class="flex justify-end items-center gap-4">
                <a href="{{ URL::previous() }}" class="text-blue-500 dark:text-blue-400 underline hover:text-blue-700 dark:hover:text-blue-300 transition-colors">{{ trans('forum::general.cancel') }}</a>
                <x-forum::button type="submit" class="">{{ trans('forum::general.reply') }}</x-forum::button>
            </div>
        </form>
    </div>
@stop
