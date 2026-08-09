<div class="panel panel-default" data-actions>
    <div class="panel-heading">
        <span class="glyphicon glyphicon-option-vertical"></span>
        <a href="#" data-toggle="collapse" data-target=".collapse.post-actions">{{ trans('forum::posts.actions') }}</a>
    </div>
    <div class="collapse post-actions">
        <div class="panel-body">
            <div class="mb-3">
                <label for="action">{{ trans_choice('forum::general.actions', 1) }}</label>
                <x-forum.select name="action" id="action">
                    @can ('deletePosts', $post->thread)
                        @can ('delete', $post)
                            <option value="delete" data-confirm="true" data-method="delete">{{ trans('forum::general.delete') }}</option>
                        @endcan
                    @endcan
                </x-forum.select>
            </div>
        </div>
        <div class="panel-footer clearfix">
            <x-forum.button-secondary type="submit" class="pull-right">{{ trans('forum::general.proceed') }}</x-forum.button-secondary>
        </div>
    </div>
</div>
