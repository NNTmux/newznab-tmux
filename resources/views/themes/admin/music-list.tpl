<div class="card card-body">
	<h1>{$title}</h1>

	{if $musiclist}
		{$musiclist->onEachSide(5)->links()}
		<table style="margin-top:10px;" class="data table table-striped responsive-utilities jambo-table Sortable">

			<tr>
				<th>id</th>
				<th>Title</th>
				<th>Artist</th>
				<th class="mid">Created</th>
			</tr>

			{foreach from=$musiclist item=music}
				<tr class="{cycle values=",alt"}">
					<td class="less">{$music->id}</td>
					<td><a title="Edit" href="{$smarty.const.WWW_TOP}/admin/music-edit?id={$music->id}">{$music->title}
							({$music->year})</a></td>
					<td>{$music->artist}</td>
					<td width="80" class="mid">{$music->created_at|date_format}</td>
				</tr>
			{/foreach}

		</table>
		<br/>
		{$musiclist->onEachSide(5)->links()}
	{else}
		<p>No Music available.</p>
	{/if}
</div>
