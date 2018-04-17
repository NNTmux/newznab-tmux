<h1>{$title}</h1>

<div class="well well-sm">
	{if $consolelist}
		{$consolelist->links()}
		<table style="margin-top:10px;" class="data table table-striped responsive-utilities jambo-table Sortable">

			<tr>
				<th>id</th>
				<th>Title</th>
				<th>Platform</th>
				<th>Created</th>
			</tr>

			{foreach from=$consolelist item=console}
				<tr class="{cycle values=",alt"}">
					<td class="less">{$console.id}</td>
					<td><a title="Edit"
						   href="{$smarty.const.WWW_TOP}/console-edit?id={$console.id}">{$console.title}</a></td>
					<td>{$console.platform}</td>
					<td>{$console.created_at|date_format}</td>
				</tr>
			{/foreach}

		</table>
		<br/>
		{$consolelist->links()}
	{else}
		<p>No games available.</p>
	{/if}
</div>
