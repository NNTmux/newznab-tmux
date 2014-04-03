<h1>{$page->title}</h1> 

{if $commentslist}
{$pager}

<table style="margin-top:10px;" class="data Sortable highlight">

	<tr>
		<th>user</th>
		<th>date</th>
		<th>comment</th>
		<th>host</th>
		<th class="mid">options</th>
	</tr>

	
	{foreach from=$commentslist item=comment}
	<tr class="{cycle values=",alt"}">
		<td>
			{if $comment.sourceid == 0}<a title="View {$comment.username}'s profile" href="{$smarty.const.WWW_TOP}/user-edit.php?id={$comment.userID}">{$comment.username}</a>{else}{$comment.username}<br/><span style="color: #ce0000;">(syndicated)</span>{/if}
		</td>
		<td width="70" title="{$comment.createddate}">{$comment.createddate|date_format}</td>
		<td>{$comment.text|escape:"htmlall"|nl2br}</td>
		<td>{$comment.host}</td>
		<td class="mid" width="70">
			{if $comment.guid != ""}<a href="{$smarty.const.WWW_TOP}/../details/{$comment.guid}#comments">view</a> | {/if}
			<a class="confirm_action" href="{$smarty.const.WWW_TOP}/comments-delete.php?id={$comment.ID}">delete</a>
		</td>
	</tr>
	{/foreach}


</table>
{else}
<p>No comments available</p>
{/if}
