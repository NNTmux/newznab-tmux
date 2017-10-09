<h1>{$page->title}</h1>
<div class="well well-sm">
{if $commentslist}
	{$pager}
	<table style="margin-top:10px;" class="data table table-striped responsive-utilities jambo-table Sortable">
		<tr>
			<th>user</th>
			<th>date</th>
			<th>comment</th>
			<th>host</th>
			<th>options</th>
		</tr>
		{foreach from=$commentslist item=comment}
			<tr class="{cycle values=",alt"}">
				<td>
					{if $comment.users_id > 0}
						<a href="{$smarty.const.WWW_TOP}/user-edit.php?id={$comment.users_id}">{$comment.username}</a>
					{else}
						{$comment.username}
					{/if}
				</td>
				<td title="{$comment.created_at}">{$comment.created_at|timeago}</td>
				{if $comment.shared == 2}
					<td style="color:#6B2447">{$comment.text|escape:"htmlall"|nl2br}</td>
				{else}
					<td>{$comment.text|escape:"htmlall"|nl2br}</td>
				{/if}
				<td>{$comment.host}</td>
				<td>
					{if $comment.guid}<a href="{$smarty.const.WWW_TOP}/../details/{$comment.guid}#comments">view</a> |{/if}
					<a href="{$smarty.const.WWW_TOP}/comments-delete.php?id={$comment.id}">delete</a>
				</td>
			</tr>
		{/foreach}
	</table>
{else}
	<p>No comments available</p>
{/if}
</div>
