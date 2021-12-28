<div class="card card-header">
	<h2>{if $title !=''}{$title}{else}Forum{/if}</h2>
	{if count($results) > 0}
		<div>
			{$results->onEachSide(5)->links()}
		</div>
		<a id="top"></a>
		<table style="width:100%;" class="data table table-bordered" id="forumtable">
			<tr>
				<th style="padding-top:0px; padding-bottom:0px;" width="60%">Topic</th>
				<th style="padding-top:0px; padding-bottom:0px;">Posted By</th>
				<th style="padding-top:0px; padding-bottom:0px;">Last Update</th>
				<th style="padding-top:0px; padding-bottom:0px;" width="5%" class="mid">Replies</th>
				{if isset($isadmin)}
					<th style="padding-top:0px; padding-bottom:0px;">Action</th>
				{/if}
			</tr>
			{foreach $results as $result}
				<tr class="{cycle values=",alt"}" id="guid{$result.id}">
					<td style="cursor:pointer;" class="item"
						onclick="document.location='{{url("/forumpost/{$result.id}")}}';">
						<a title="View post" class="title"
						   href="{{url("/forumpost/{$result.id}")}}">{$result.subject|escape:"htmlall"|truncate:100:'...':true:true}</a>
						<div class="hint">
							{$result.message|truncate:200:'...':false:false}
						</div>
						<br/>
						{if $result.locked == 1}
							<button type="button"
									class="btn btn-sm btn-warning"
									data-bs-toggle="tooltip" data-bs-placement="top" title
									data-original-title="Topic Locked">
								<i class="fa fa-lock"></i></button>
						{/if}
					</td>
					<td>
						<a title="View profile"
						   href="{{url("/profile/?name={$result.username}")}}"><h5>
								<strong>{$result.username}</strong></h5></a>
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
					</td>
					<td>
						<a href="{{url("/forumpost/{$result.id}#last")}}"
						   title="{$result.updated_at}">{$result.updated_at|date_format}</a>
						<div class="hint">({$result.updated_at|timeago})</div>
					</td>
					<td class="mid">{$result.replies}</td>
					<td>
						{if isset($isadmin)}
							<div>
								<a class="confirm_action btn btn-sm btn-danger"
								   href="{{url("/topic_delete?id={$result.id}")}}"
								   title="Delete Topic">Delete Topic</a>
							</div>
							<div>
								{if $result.locked == 0}
									<a class="confirm_action btn btn-sm btn-danger"
									   href="{{url("/forum?lock={$result.id}")}}"
									   title="Lock Topic">Lock Topic</a>
								{else}
									<a class="confirm_action btn btn-sm btn-danger"
									   href="{{url("/forum?unlock={$result.id}")}}"
									   title="Unlock Topic">Unlock Topic</a>
								{/if}
							</div>
						{/if}
					</td>
				</tr>
			{/foreach}
		</table>
		<div style="float:right;margin-top:5px;"><a class="btn btn-small" href="#top">Top</a></div>
		<br/>
		<div>
			{$results->onEachSide(5)->links()}
		</div>
	{/if}
	<div id="new" tabindex="-1" role="dialog" aria-labelledby="myLabel" aria-hidden="true">
		<div class="header">
			<h3 id="myLabel">Add New Post</h3>
		</div>
		<div class="body">
            {{Form::open(['id' => 'new-forum-thread', 'class' => 'form-horizontal'])}}
				<div class="control-group">
					<label class="col-form-label" for="addSubject">Subject</label>
					<div class="controls">
						<input class="input form-control-lg" type="text" maxlength="200" id="addSubject"
							   name="addSubject"/>
					</div>
				</div>
				<div class="control-group">
					<label class="col-form-label" for="addMessage">Message</label>
					<div class="controls">
						<textarea maxlength="5000" id="addMessage" name="addMessage"></textarea>
					</div>
					<input class="btn btn-success" type="submit" value="submit"/>
					<input class="btn btn-warning" value="Cancel"
						   onclick="if(confirm('Are you SURE you wish to cancel?')) history.back();"/>
				</div>
			{{Form::close()}}
		</div>
	</div>
</div>
