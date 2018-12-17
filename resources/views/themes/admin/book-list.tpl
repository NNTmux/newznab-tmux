<h1>{$title}</h1>

<div class="card card-body">
	{if $booklist}
		{if $booklist->count() > 0}
			<div class="col-md-4">
				{$booklist->onEachSide(5)->links()}
			</div>
		{/if}
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
					<td><a title="Edit" href="{$smarty.const.WWW_TOP}/admin/book-edit?id={$book.id}">{$book.title}</a>
					</td>
					<td>{$book.author}</td>
					<td class="mid">{$book.created_at|date_format}</td>
				</tr>
			{/foreach}

		</table>
		<br/>
		{if $booklist->count() > 0}
			<div class="col-md-4">
				{$booklist->onEachSide(5)->links()}
			</div>
		{/if}
	{else}
		<p>No books available.</p>
	{/if}
</div>
