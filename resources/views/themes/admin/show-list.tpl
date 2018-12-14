<div class="well well-sm">
	<h1>{$title}</h1>
	{if $tvshowlist}
		<div style="float:right;">
			<form name="showsearch" action="">
				{{csrf_field()}}
				<label for="showname">Title</label>
				<input id="showname" type="text" name="showname" value="{$showname}" size="15"/>
				&nbsp;&nbsp;
				<input class="btn btn-success" type="submit" value="Go"/>
			</form>
		</div>
		{$tvshowlist->onEachSide(5)->links()}
		<br/>
		<br/>
		<table style="width:100%;margin-top:10px;"
			   class="data table table-striped responsive-utilities jambo-table Sortable">
			<tr>
				<th style="width:50px;">videos_id</th>
				<th>title</th>
				<th style="width:80px;">date</th>
				<th style="width:80px;">source</th>
				<th style="width:100px;" class="right">options</th>
			</tr>
			{foreach from=$tvshowlist item=tvshow}
				<tr class="{cycle values=",alt"}">
					<td class="less">{$tvshow.id}</td>
					<td><a title="Edit"
						   href="{$smarty.const.WWW_TOP}/admin/show-edit?id={$tvshow.id}">{$tvshow.title|escape:"htmlall"}</a>
					</td>
					<td class="less">{$tvshow.started|date_format}</td>
					<td class="less">
						{if $tvshow.source == 1}tvdb
						{elseif $tvshow.source == 2}tvmaze
						{elseif $tvshow.source == 3}tmdb
						{/if}
					</td>
					<td class="right"><a title="delete this show entry"
										 href="{$smarty.const.WWW_TOP}/admin/show-delete?id={$tvshow.id}">delete</a> |
						<a
								title="remove this showid from all releases"
								href="{$smarty.const.WWW_TOP}/admin/show-remove?id={$tvshow.id}">remove</a></td>
				</tr>
			{/foreach}
		</table>
	{else}
		<p>No TV Shows available.</p>
	{/if}
</div>
