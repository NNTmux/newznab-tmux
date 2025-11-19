@component('forum::modal-form')
    @slot('key', 'mark-as-read')
    @slot('title', trans('forum::general.mark_read'))
    @slot('route', Forum::route('unread.mark-as-read'))
    @slot('method', 'PATCH')

    <p class="text-gray-900 dark:text-gray-100">{{ trans('forum::general.generic_confirm') }}</p>

    @slot('actions')
        <x-forum::button type="submit">
            {{ trans('forum::general.mark_read') }}
        </x-forum::button>
    @endslot
@endcomponent
