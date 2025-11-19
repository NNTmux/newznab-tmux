@extends ('forum::layouts.main', ['breadcrumbs_append' => [trans_choice('forum::posts.delete', 1)]])

@section ('content')
    <div id="delete-post">
        <h2 class="text-3xl text-gray-900 dark:text-gray-100">{{ trans_choice('forum::posts.delete', 1) }}</h2>

        <hr class="border-gray-300 dark:border-gray-700">

        @include ('forum::post.partials.list', ['post' => $post, 'single' => true])

        <form method="POST" action="{{ Forum::route('post.delete', $post) }}">
            @csrf
            @method('DELETE')

            <div class="bg-white dark:bg-gray-800 border dark:border-gray-700 rounded-md mb-3 transition-colors">
                <div class="p-4 text-gray-900 dark:text-gray-100">

                    @if (config('forum.general.soft_deletes'))
                        <div class="form-check" v-if="state.selectedPostAction == 'delete'">
                            <input class="form-check-input rounded border-gray-300 dark:border-gray-600 text-blue-500 dark:text-blue-400 focus:ring-blue-500 dark:focus:ring-blue-400 dark:bg-gray-700" type="checkbox" name="permadelete" value="1" id="permadelete">
                            <label class="form-check-label text-gray-700 dark:text-gray-300" for="permadelete">
                                {{ trans('forum::general.perma_delete') }}
                            </label>
                        </div>
                    @else
                        {{ trans('forum::general.generic_confirm') }}
                    @endif
                </div>
            </div>

            <div class="flex justify-end items-center gap-2">
                <x-forum::button-link href="{{ URL::previous() }}">{{ trans('forum::general.cancel') }}</x-forum::button-link>
                <x-forum::button type="submit" class="bg-red-500 dark:bg-red-600 hover:bg-red-400 dark:hover:bg-red-500 px-5">{{ trans('forum::general.delete') }}</x-forum::button>
            </div>
        </form>
    </div>
@stop
