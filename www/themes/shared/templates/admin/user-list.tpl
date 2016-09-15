<div class="well well.sm">
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
		<input class="btn btn-default" type="submit" value="Go" />
	</form>
</div>

{$pager}

<br/><br/>

<table style="width:100%;margin-top:10px;" class="data table table-striped responsive-utilities jambo-table">

	<tr>
		<th>Name<br/><a title="Sort Descending" href="{$orderbyusername_desc}"><span><i class="fa fa-chevron-down"></i></span></a><a title="Sort Ascending" href="{$orderbyusername_asc}"><span><i class="fa fa-chevron-up"></i></span></a></th>
		<th>Email<br/><a title="Sort Descending" href="{$orderbyemail_desc}"><span><i class="fa fa-chevron-down"></i></span></a><a title="Sort Ascending" href="{$orderbyemail_asc}"><span><i class="fa fa-chevron-up"></i></span></a></th>
		<th>Host<br/><a title="Sort Descending" href="{$orderbyhost_desc}"><span><i class="fa fa-chevron-down"></i></span></a><a title="Sort Ascending" href="{$orderbyhost_asc}"><span><i class="fa fa-chevron-up"></i></span></a></th>
		<th>Join date<br/><a title="Sort Descending" href="{$orderbycreateddate_desc}"><span><i class="fa fa-chevron-down"></i></span></a><a title="Sort Ascending" href="{$orderbycreateddate_asc}"><span><i class="fa fa-chevron-up"></i></span></a></th>
		<th>last login<br/><a title="Sort Descending" href="{$orderbylastlogin_desc}"><span><i class="fa fa-chevron-down"></i></span></a><a title="Sort Ascending" href="{$orderbylastlogin_asc}"><span><i class="fa fa-chevron-up"></i></span></a></th>
		<th>API access<br/><a title="Sort Descending" href="{$orderbyapiaccess_desc}"><span><i class="fa fa-chevron-down"></i></span></a><a title="Sort Ascending" href="{$orderbyapiaccess_asc}"><span><i class="fa fa-chevron-up"></i></span></a></th>
		<th>API Requests<br/><a title="Sort Descending" href="{$orderbyapirequests_desc}"><span><i class="fa fa-chevron-down"></i></span></a><a title="Sort Ascending" href="{$orderbyapirequests_asc}"><span><i class="fa fa-chevron-up"></i></span></a></th>
		<th class="mid">grabs<br/><a title="Sort Descending" href="{$orderbygrabs_desc}"><span><i class="fa fa-chevron-down"></i></span></a><a title="Sort Ascending" href="{$orderbygrabs_asc}"><span><i class="fa fa-chevron-up"></i></span></a></th>
		<th class="mid">Invites</th>
		<th class="mid">Notes</th>
		<th>Role<br/><a title="Sort Descending" href="{$orderbyrole_desc}"><span><i class="fa fa-chevron-down"></i></span></a><a title="Sort Ascending" href="{$orderbyrole_asc}"><span><i class="fa fa-chevron-up"></i></span></a></th>
		<th>Role expiration date<br/><a title="Sort Descending" href="{$orderbyrolechangedate_desc}"><span><i class="fa fa-chevron-down"></i></span></a><a title="Sort Ascending" href="{$orderbyrolechangedate_asc}"><span><i class="fa fa-chevron-up"></i></span></a></th>
		<th>options</th>
	</tr>


	{foreach from=$userlist item=user}
	<tr class="{cycle values=",alt"}">
		<td><a title="Edit user" href="{$smarty.const.WWW_TOP}/user-edit.php?id={$user.id}">{$user.username}</a></td>
		<td><a title="View profile" href="{$smarty.const.WWW_TOP}/../profile?id={$user.id}">{$user.email}</a></td>
		<td>{$user.host}</td>
		<td title="{$user.createddate}">{$user.createddate|date_format}</td>
		<td title="{$user.lastlogin}">{$user.lastlogin|date_format}</td>
		<td title="{$user.apiaccess}">{$user.apiaccess|date_format}</td>
		<td>{$user.apirequests}</td>
		<td class="mid">{$user.grabs}</td>
		<td class="mid">{$user.invites}</td>
		<td class="mid"><a title="{if $user.notes|count_characters > 0}View{else}Add{/if} Notes" href="{$smarty.const.WWW_TOP}/user-edit.php?id={$user.id}#notes"><img src="{$smarty.const.WWW_THEMES}/shared/images/icons/{if $user.notes|count_characters > 0}note_edit.png{else}note_add.png{/if}" alt="" /></a></td>
		<td>{$user.rolename}</td>
		<td>{if !empty($user.rolechangedate)}{$user.rolechangedate}{/if}</td>
		<td>{if $user.role!="2"}<a class="confirm_action" href="{$smarty.const.WWW_TOP}/user-delete.php?id={$user.id}">delete</a>{/if}</td>
	</tr>
	{/foreach}
</table>
	</div>
