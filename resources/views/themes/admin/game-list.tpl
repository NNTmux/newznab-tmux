<div class="card card-body">
	<h1>{$title}</h1>
	{if $gamelist}
		{$gamelist->onEachSide(5)->links()}
		<table style="margin-top:10px;" class="data table table-striped responsive-utilities jambo-table Sortable">
			<tr>
				<th>ID</th>
				<th>Title</th>
				<th>Genre</th>
				<th>Created</th>
			</tr>
			{foreach from=$gamelist item=game}
				<tr class="{cycle values=",alt"}">
					<td class="less">{$game.id}</td>
					<td><a title="Edit" href="{$smarty.const.WWW_TOP}/admin/game-edit?id={$game.id}">{$game.title}</a>
					</td>
					<td>{$game.genretitle}</td>
					<td>{$game.created_at|date_format}</td>
				</tr>
			{/foreach}
		</table>
	{else}
		<p>No games available.</p>
	{/if}
