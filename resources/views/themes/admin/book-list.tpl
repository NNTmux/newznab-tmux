<h1>{$page->title}</h1>

<div class="well well-sm">
	{if $booklist}
		{$pager}
		<table style="margin-top:10px;" class="data table table-striped responsive-utilities jambo-table Sortable">

			<tr>
				<th>id</th>
				<th>Title</th>
				<th>Author</th>
				<th width="80" class="mid">Created</th>
			</tr>

			{foreach from=$booklist item=book}
				<tr class="{cycle values=",alt"}">
					<td class="less">{$book.id}</td>
					<td><a title="Edit" href="{$smarty.const.WWW_TOP}/book-edit.php?id={$book.id}">{$book.title}</a>
					</td>
					<td>{$book.author}</td>
					<td class="mid">{$book.created_at|date_format}</td>
				</tr>
			{/foreach}

		</table>
		<br/>
		{$pager}
	{else}
		<p>No books available.</p>
	{/if}
</div>
