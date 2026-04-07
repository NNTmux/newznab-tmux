@extends ('forum::layouts.main', ['breadcrumbs_append' => [trans('forum::posts.pending_approval')]])

@section ('forum-content')
    <div id="pending-approval" data-all-ids="{{ $posts->pluck('id')->toJson() }}" v-cloak>
        <div class="flex flex-col md:flex-row justify-between my-4">
            <h2 class="text-3xl font-semibold text-gray-900 dark:text-gray-100">{{ trans('forum::posts.pending_approval') }}</h2>
        </div>

        @if ($posts->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-6 text-center text-gray-500 dark:border-gray-600 dark:bg-gray-900/40 dark:text-gray-400">
                {{ trans('forum::posts.none_found') }}
            </div>
        @else
            <div class="mb-4 flex justify-end">
                <label class="inline-flex items-center gap-2 cursor-pointer text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" v-model="selectAll" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-blue-400 dark:focus:border-blue-400 dark:focus:ring-blue-400">
                    <span>{{ trans('forum::posts.select_all') }}</span>
                </label>
            </div>

            <div class="space-y-4">
                @foreach ($posts as $post)
                    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="border-b border-gray-200 p-4 dark:border-gray-700">
                            <div class="flex items-start gap-4">
                                <div class="pt-1">
                                    <input type="checkbox" name="posts[]" v-model="selectedIds" :value="{{ $post->id }}" class="post-checkbox rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-blue-400 dark:focus:border-blue-400 dark:focus:ring-blue-400">
                                </div>
                                <div class="flex-1">
                                    <div class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                                        {!! $post->content !!}
                                    </div>
                                    <div class="mt-2 inline-flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                                        <span>{{ $post->authorName }}</span>
                                        <span>({{ $post->created_at->diffForHumans() }})</span>
                                    </div>
                                    <div class="mt-2 text-sm">
                                        {{ trans_choice('forum::threads.thread', 1) }}:
                                        <a href="{{ Forum::route('thread.show', $post->thread) }}?post={{ $post->id }}" class="text-blue-600 hover:underline dark:text-blue-400">
                                            {{ $post->thread->title }}
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 flex flex-wrap gap-4">
                <button type="button"
                        :disabled="!selectedIds.length"
                        data-open-modal="delete-posts"
                        class="rounded-md bg-red-500 px-4 py-2 text-white shadow hover:bg-red-600 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-red-600 dark:hover:bg-red-500">
                    {{ trans('forum::general.delete_selection') }}
                </button>
                <button type="button"
                        :disabled="!selectedIds.length"
                        data-open-modal="approve-posts"
                        class="rounded-md bg-blue-500 px-4 py-2 text-white shadow hover:bg-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-blue-600 dark:hover:bg-blue-500">
                    {{ trans('forum::general.approve_selection') }}
                </button>
            </div>

            @if ($posts->hasPages())
                <div class="mt-4">
                    {{ $posts->links('forum::pagination') }}
                </div>
            @endif
        @endif

        @component('forum::modal-form')
            @slot('key', 'delete-posts')
            @slot('title', trans('forum::general.delete_selection'))
            @slot('route', Forum::route('bulk.post.delete'))
            @slot('method', 'DELETE')
            @slot('actions')
                <x-forum::button type="submit" class="bg-red-500 dark:bg-red-600 hover:bg-red-400 dark:hover:bg-red-500">
                    {{ trans('forum::general.proceed') }}
                </x-forum::button>
            @endslot

            <p class="text-gray-900 dark:text-gray-100">{{ trans('forum::general.generic_confirm') }}</p>
            <template v-for="id in selectedIds" :key="id">
                <input type="hidden" name="posts[]" :value="id">
            </template>
        @endcomponent

        @component('forum::modal-form')
            @slot('key', 'approve-posts')
            @slot('title', trans('forum::general.approve_selection'))
            @slot('route', Forum::route('bulk.post.approve'))
            @slot('method', 'POST')
            @slot('actions')
                <x-forum::button type="submit">
                    {{ trans('forum::general.proceed') }}
                </x-forum::button>
            @endslot

            <p class="text-gray-900 dark:text-gray-100">{{ trans('forum::general.generic_confirm') }}</p>
            <template v-for="id in selectedIds" :key="id">
                <input type="hidden" name="posts[]" :value="id">
            </template>
        @endcomponent
    </div>
@stop

