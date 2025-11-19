@extends ('forum::layouts.main', ['thread' => null, 'breadcrumbs_append' => [trans('forum::threads.recent')]])

@section ('content')
    <div id="new-posts">
        <h2 class="text-3xl font-medium my-3 text-gray-900 dark:text-gray-100">{{ trans('forum::threads.recent') }}</h2>

        @if (!$threads->isEmpty())
            <div class="my-3">
                @foreach ($threads as $thread)
                    @include ('forum::thread.partials.list')
                @endforeach
            </div>
        @else
            <div class="bg-white dark:bg-gray-800 my-3 transition-colors">
                <div class="card-body text-center text-muted text-gray-500 dark:text-gray-400 p-6">
                    {{ trans('forum::threads.none_found') }}
                </div>
            </div>
        @endif
    </div>
@stop
