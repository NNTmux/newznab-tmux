<h1>{$page->title}</h1> 

{if $booklist}
{$pager}

<table style="margin-top:10px;" class="data Sortable highlight">

	<tr>
		<th>ID</th>
		<th>Title</th>
		<th>Author</th>
		<th width="80" class="mid">Created</th>
	</tr>
	
	{foreach from=$booklist item=book}
	<tr class="{cycle values=",alt"}">
		<td class="less">{$book.ID}</td>
		<td><a title="Edit" href="{$smarty.const.WWW_TOP}/book-edit.php?id={$book.ID}">{$book.title}</a></td>
		<td>{$book.author}</td>
		<td class="mid">{$book.createddate|date_format}</td>
	</tr>
	{/foreach}

</table>
    <br/>
    {$pager}
{else}
    <p>No books available.</p>
{/if}
