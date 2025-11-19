@component('forum::modal-form')
    @slot('key', 'delete-category')
    @slot('title', trans('forum::general.delete'))
    @slot('route', Forum::route('category.delete', $category))
    @slot('method', 'DELETE')

    <div class="text-gray-900 dark:text-gray-100">
        {{ trans('forum::general.generic_confirm') }}
    </div>

    @if(!$category->isEmpty())
        <div class="form-check mt-3">
            <input class="form-check-input rounded border-gray-300 dark:border-gray-600 text-blue-500 dark:text-blue-400 focus:ring-blue-500 dark:focus:ring-blue-400 dark:bg-gray-700" type="checkbox" value="1" name="force" id="forceDelete">
            <label class="form-check-label text-gray-700 dark:text-gray-300" for="forceDelete">
                {{ trans('forum::categories.confirm_nonempty_delete') }}
            </label>
        </div>
    @endif

    @slot('actions')
        <x-forum::button type="submit" class="bg-red-500 dark:bg-red-600 hover:bg-red-400 dark:hover:bg-red-500">{{ trans('forum::general.delete') }}</x-forum::button>
    @endslot
@endcomponent
