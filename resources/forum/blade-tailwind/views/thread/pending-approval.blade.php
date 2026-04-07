@extends ('forum::layouts.main', ['breadcrumbs_append' => [trans('forum::threads.pending_approval')]])

@section ('forum-content')
    <div id="pending-approval" data-all-ids="{{ $threads->pluck('id')->toJson() }}" v-cloak>
        <div class="flex flex-col md:flex-row justify-between my-4">
            <h2 class="text-3xl font-semibold text-gray-900 dark:text-gray-100">{{ trans('forum::threads.pending_approval') }}</h2>
        </div>

        @if ($threads->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-6 text-center text-gray-500 dark:border-gray-600 dark:bg-gray-900/40 dark:text-gray-400">
                {{ trans('forum::threads.none_found') }}
            </div>
        @else
            <div class="mb-4 flex justify-end">
                <label class="inline-flex items-center gap-2 cursor-pointer text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" v-model="selectAll" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-blue-400 dark:focus:border-blue-400 dark:focus:ring-blue-400">
                    <span>{{ trans('forum::threads.select_all') }}</span>
                </label>
            </div>

            <div class="space-y-4">
                @foreach ($threads as $thread)
                    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="border-b border-gray-200 p-4 dark:border-gray-700">
                            <div class="flex items-start gap-4">
                                <div class="pt-1">
                                    <input type="checkbox" name="threads[]" :value="{{ $thread->id }}" v-model="selectedIds" class="thread-checkbox rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-blue-400 dark:focus:border-blue-400 dark:focus:ring-blue-400">
                                </div>
                                <div class="flex-1">
                                    <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                                        <div>
                                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                                {{ trans_choice('forum::threads.thread', 1) }}:
                                                <a href="{{ Forum::route('thread.show', $thread) }}" class="text-blue-600 hover:underline dark:text-blue-400">
                                                    {{ $thread->title }}
                                                </a>
                                            </h3>
                                            <div class="inline-flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                                                <span>{{ $thread->authorName }}</span>
                                                <span>({{ $thread->created_at->diffForHumans() }})</span>
                                            </div>
                                            <div class="mt-3 text-sm text-gray-700 dark:text-gray-300">
                                                {!! $thread->firstPost->content !!}
                                            </div>
                                        </div>
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
                        data-open-modal="delete-threads"
                        class="rounded-md bg-red-500 px-4 py-2 text-white shadow hover:bg-red-600 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-red-600 dark:hover:bg-red-500">
                    {{ trans('forum::general.delete_selection') }}
                </button>
                <button type="button"
                        :disabled="!selectedIds.length"
                        data-open-modal="approve-threads"
                        class="rounded-md bg-blue-500 px-4 py-2 text-white shadow hover:bg-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-blue-600 dark:hover:bg-blue-500">
                    {{ trans('forum::general.approve_selection') }}
                </button>
            </div>

            @if ($threads->hasPages())
                <div class="mt-4">
                    {{ $threads->links('forum::pagination') }}
                </div>
            @endif
        @endif

        @component('forum::modal-form')
            @slot('key', 'delete-threads')
            @slot('title', trans('forum::general.delete_selection'))
            @slot('route', Forum::route('bulk.thread.delete'))
            @slot('method', 'DELETE')
            @slot('actions')
                <x-forum::button type="submit" class="bg-red-500 dark:bg-red-600 hover:bg-red-400 dark:hover:bg-red-500">
                    {{ trans('forum::general.proceed') }}
                </x-forum::button>
            @endslot

            <p class="text-gray-900 dark:text-gray-100">{{ trans('forum::general.generic_confirm') }}</p>
            <template v-for="id in selectedIds" :key="id">
                <input type="hidden" name="threads[]" :value="id">
            </template>
        @endcomponent

        @component('forum::modal-form')
            @slot('key', 'approve-threads')
            @slot('title', trans('forum::general.approve_selection'))
            @slot('route', Forum::route('bulk.thread.approve'))
            @slot('method', 'POST')
            @slot('actions')
                <x-forum::button type="submit">
                    {{ trans('forum::general.proceed') }}
                </x-forum::button>
            @endslot

            <p class="text-gray-900 dark:text-gray-100">{{ trans('forum::general.generic_confirm') }}</p>
            <template v-for="id in selectedIds" :key="id">
                <input type="hidden" name="threads[]" :value="id">
            </template>
        @endcomponent
    </div>
@stop

