<ol class="breadcrumb">
    <li><a href="{{ url('/') }}">{{ trans('Back to '. \App\Models\Settings::settingValue('site.main.title')) }}</a></li>
    <li><a href="{{ url(config('forum.routing.prefix')) }}">{{ trans('Forum Index') }}</a></li>
    @if (isset($category) && $category)
        @include ('forum::partials.breadcrumb-categories', ['category' => $category])
    @endif
    @if (isset($thread) && $thread)
        <li><a href="{{ Forum::route('thread.show', $thread) }}">{{ $thread->title }}</a></li>
    @endif
    @if (isset($breadcrumb_other) && $breadcrumb_other)
        <li>{!! $breadcrumb_other !!}</li>
    @endif
</ol>
