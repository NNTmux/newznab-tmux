<div class="well well-sm">
	<div class="page-header">
		<h1>Add to category</h1>
	</div>
	<h3>{$type|ucwords} {$movie.title|escape:"htmlall"} in</h3>
	<form class="form-horizontal" id="mymovies" action="{$smarty.const.WWW_TOP}/mymovies/do{$type}" method="post">
		<input type="hidden" name="subpage" value="{$imdbid}"/>
		<div class="control-group">
			<label class="control-label" for="category">Choose</label>
			<div class="controls">
				{if $from}<input type="hidden" name="from" value="{$from}" />{/if}
				{html_checkboxes id="category" name='category' values=$cat_ids output=$cat_names selected=$cat_selected separator=''}
			</div>
		</div>
		<div class="control-group">
			<label class="control-label"></label>
			<div class="controls">
				<input class="btn btn-success" type="submit" name="{$type}" value="{$type|ucwords}"/>
			</div>
		</div>
	</form>
</div>
