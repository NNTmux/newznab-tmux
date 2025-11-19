@extends ('forum::layouts.main', ['breadcrumbs_append' => [trans_choice('forum::posts.restore', 1)]])

@section ('content')
    <div id="delete-post">
        <h2 class="flex-grow-1 text-3xl text-gray-900 dark:text-gray-100">{{ trans_choice('forum::posts.restore', 1) }}</h2>

        <hr class="border-gray-300 dark:border-gray-700">

        @include ('forum::post.partials.list', ['post' => $post, 'single' => true])

        <form method="POST" action="{{ Forum::route('post.restore', $post) }}">
            @csrf
            @method('POST')

            <div class="bg-white dark:bg-gray-800 border dark:border-gray-700 rounded-md mb-3 transition-colors">
                <div class="p-4 text-gray-900 dark:text-gray-100">
                    {{ trans('forum::general.generic_confirm') }}
                </div>
            </div>

            <div class="flex justify-end items-center gap-2">
                <x-forum::button-link href="{{ URL::previous() }}" class="text-blue-500 dark:text-blue-400">{{ trans('forum::general.cancel') }}</x-forum::button-link>
                <x-forum::button type="submit" class="px-5">
                    {{ trans('forum::general.restore') }}
                </x-forum::button>
            </div>
        </form>
    </div>
@stop
