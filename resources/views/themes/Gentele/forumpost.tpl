<div class="well well-sm">
	<h2><a href="{{route('forum')}}">Forum</a></h2>
	{if $results|@count > 0}
		<h3>{$results[0].subject|escape:"htmlall"}</h3>
		<a id="top"></a>
		<table style="width:100%;" class="data highlight table" id="forumtable">
			<tr>
				<th style="padding-top:0px; padding-bottom:0px;">By</th>
				<th style="padding-top:0px; padding-bottom:0px;">Message</th>
			</tr>
			{foreach $results as $result name=result}
				<tr class="{cycle values=",alt"}">
					<td width="15%;">
						{if isset($isadmin) && $isadmin == 1}<strong>{/if}
							<a {if $smarty.foreach.result.last}id="last"{/if}
							   title="{if isset($isadmin) && $isadmin == 1}Admin{else}View profile{/if}"
							   href="{{url("/profile/?name={$result.username}")}}"><h5>
									<strong>{$result.username}</strong></h5></a>
							{if isset($isadmin) && $isadmin == 1}</strong>{/if}
						{if $result.rolename === 'Admin' || $result.rolename === 'Moderator' || $result.rolename === 'Friend'}
							<span class="btn btn-success btn-xs">{$result.rolename}</span>
						{elseif $result.rolename === 'Supporter'}
							<span class="btn btn-warning btn-xs">{$result.rolename}</span>
						{elseif $result.rolename === 'Supporter ++'}
							<span class="btn btn-danger btn-xs">{$result.rolename}</span>
						{else}
							<span class="btn btn-info btn-xs">{$result.rolename}</span>
						{/if}
						<br/>
						on <span title="{$result.created_at}">{$result.created_at|date_format}</span>
						<div class="hint">({$result.created_at|timeago})</div>
						<br/>
						{if $userdata.id == $result.users_id && $result.locked != 1 || isset($isadmin)}
							<div>
								<a class="btn btn-sm btn-warning"
								   href="{{url("/post_edit?id={$result.id}")}}"
								   title="Edit Post">Edit</a>
							</div>
						{/if}
						{if isset($isadmin)}
							<br/>
							<div>
								<a class="confirm_action btn btn-sm btn-danger"
								   href="{{url("/admin/forum-delete/{$result.id}")}}"
								   title="Delete Post">Delete</a>
							</div>
						{/if}
					</td>
					<td>{$result.message}</td>
				</tr>
			{/foreach}
		</table>
		<div id="new" tabindex="-1" role="dialog" aria-labelledby="myLabel" aria-hidden="true">
			{if $result.locked == 0}
				<div class="header">
					<h3 id="myLabel">Reply</h3>
				</div>
				<div class="body">
                    {{Form::open(['id' => 'forum-post-reply', 'class' => 'form-horizontal'])}}
						{{csrf_field()}}
						<div class="control-group">
							<label class="col-form-label" for="addMessage">Message</label>
							<div class="controls">
								<textarea id="addMessage" name="addMessage"></textarea>
							</div>
							<input class="btn btn-success" type="submit" value="Submit"/>
							<input class="btn btn-warning" value="Cancel"
								   onclick="if(confirm('Are you SURE you wish to cancel?')) history.back();"/>
						</div>
					{{Form::close()}}
				</div>
			{else}
				<label class="badge bg-warning" title="Topic Locked">Topic Locked</label>
			{/if}
		</div>
	{/if}
</div>
