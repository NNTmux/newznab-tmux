
<h1>{$page->title}</h1>

<div style="float:right;">

	<form name="usersearch" action="">
		<label for="username">username</label>
		<input id="username" type="text" name="username" value="{$username}" size="10" />
		&nbsp;&nbsp;
		<label for="email">email</label>
		<input id="email" type="text" name="email" value="{$email}" size="10" />
		&nbsp;&nbsp;
		<label for="host">host</label>
		<input id="host" type="text" name="host" value="{$host}" size="10" />
		&nbsp;&nbsp;
		<label for="role">role</label>
		<select name="role">
			<option value="">-- any --</option>
			{html_options values=$role_ids output=$role_names selected=$role}
		</select>
		&nbsp;&nbsp;
		<input type="submit" value="Go" />
	</form>
</div>

{$pager}

<br/><br/>

<table style="width:100%;margin-top:10px;" class="data highlight">

	<tr>
		<th>name<br/><a title="Sort Descending" href="{$orderbyusername_desc}"><img src="{$smarty.const.WWW_TOP}/../templates/carbon-tmux/images/sorting/arrow_down.gif" alt="" /></a><a title="Sort Ascending" href="{$orderbyusername_asc}"><img src="{$smarty.const.WWW_TOP}/../templates/carbon-tmux/images/sorting/arrow_up.gif" alt="" /></a></th>
		<th>email<br/><a title="Sort Descending" href="{$orderbyemail_desc}"><img src="{$smarty.const.WWW_TOP}/../templates/carbon-tmux/images/sorting/arrow_down.gif" alt="" /></a><a title="Sort Ascending" href="{$orderbyemail_asc}"><img src="{$smarty.const.WWW_TOP}/../templates/carbon-tmux/images/sorting/arrow_up.gif" alt="" /></a></th>
		<th>host<br/><a title="Sort Descending" href="{$orderbyhost_desc}"><img src="{$smarty.const.WWW_TOP}/../templates/carbon-tmux/images/sorting/arrow_down.gif" alt="" /></a><a title="Sort Ascending" href="{$orderbyhost_asc}"><img src="{$smarty.const.WWW_TOP}/../templates/carbon-tmux/images/sorting/arrow_up.gif" alt="" /></a></th>
		<th>join date<br/><a title="Sort Descending" href="{$orderbycreateddate_desc}"><img src="{$smarty.const.WWW_TOP}/../templates/carbon-tmux/images/sorting/arrow_down.gif" alt="" /></a><a title="Sort Ascending" href="{$orderbycreateddate_asc}"><img src="{$smarty.const.WWW_TOP}/../templates/carbon-tmux/images/sorting/arrow_up.gif" alt="" /></a></th>
		<th>last login<br/><a title="Sort Descending" href="{$orderbylastlogin_desc}"><img src="{$smarty.const.WWW_TOP}/../templates/carbon-tmux/images/sorting/arrow_down.gif" alt="" /></a><a title="Sort Ascending" href="{$orderbylastlogin_asc}"><img src="{$smarty.const.WWW_TOP}/../templates/carbon-tmux/images/sorting/arrow_up.gif" alt="" /></a></th>
		<th>api access<br/><a title="Sort Descending" href="{$orderbyapiaccess_desc}"><img src="{$smarty.const.WWW_TOP}/../templates/carbon-tmux/images/sorting/arrow_down.gif" alt="" /></a><a title="Sort Ascending" href="{$orderbyapiaccess_asc}"><img src="{$smarty.const.WWW_TOP}/../templates/carbon-tmux/images/sorting/arrow_up.gif" alt="" /></a></th>
		<th class="mid">grabs<br/><a title="Sort Descending" href="{$orderbygrabs_desc}"><img src="{$smarty.const.WWW_TOP}/../templates/carbon-tmux/images/sorting/arrow_down.gif" alt="" /></a><a title="Sort Ascending" href="{$orderbygrabs_asc}"><img src="{$smarty.const.WWW_TOP}/../templates/carbon-tmux/images/sorting/arrow_up.gif" alt="" /></a></th>
		<th class="mid">invites</th>
		<th class="mid">notes</th>
		<th>role<br/><a title="Sort Descending" href="{$orderbyrole_desc}"><img src="{$smarty.const.WWW_TOP}/../templates/carbon-tmux/images/sorting/arrow_down.gif" alt="" /></a><a title="Sort Ascending" href="{$orderbyrole_asc}"><img src="{$smarty.const.WWW_TOP}/../templates/carbon-tmux/images/sorting/arrow_up.gif" alt="" /></a></th>
		<th>options</th>
	</tr>


	{foreach from=$userlist item=user}
	<tr class="{cycle values=",alt"}">
		<td><a title="Edit user" href="{$smarty.const.WWW_TOP}/user-edit.php?id={$user.ID}">{$user.username}</a></td>
		<td><a title="View profile" href="{$smarty.const.WWW_TOP}/../profile?id={$user.ID}">{$user.email}</a></td>
		<td>{$user.host}</td>
		<td title="{$user.createddate}">{$user.createddate|date_format}</td>
		<td title="{$user.lastlogin}">{$user.lastlogin|date_format}</td>
		<td title="{$user.apiaccess}">{$user.apiaccess|date_format}</td>
		<td class="mid">{$user.grabs}</td>
		<td class="mid">{$user.invites}</td>
		<td class="mid"><a title="{if $user.notes|count_characters > 0}View{else}Add{/if} Notes" href="{$smarty.const.WWW_TOP}/user-edit.php?id={$user.ID}#notes"><img src="{$smarty.const.WWW_TOP}/../templates/nntmux/images/icons/{if $user.notes|count_characters > 0}note_edit.png{else}note_add.png{/if}" alt="" /></a></td>
		<td>{$user.rolename}</td>
		<td>{if $user.role!="2"}<a class="confirm_action" href="{$smarty.const.WWW_TOP}/user-delete.php?id={$user.ID}">delete</a>{/if}</td>
	</tr>
	{/foreach}


</table>
