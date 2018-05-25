<div class="row" style="background-color: white">
	<h4>{$type|ucwords} {$show.title|escape:"htmlall"} in:</h4>
	{{Form::open(['id' => 'myshows', 'class' => 'form-horizontal', 'url' => "myshows/do{$type}"])}}
		<input type="hidden" name="subpage" value="{$video}"/>
		{if isset($from)}<input type="hidden" name="from" value="{$from}" />{/if}
		{html_checkboxes name='category' values=$cat_ids output=$cat_names selected=$cat_selected separator='<br />'}
		<br/>
		<input class="btn btn-primary" type="submit" name="{$type}" value="{$type|ucwords}"/>
	{{Form::close()}}
</div>
