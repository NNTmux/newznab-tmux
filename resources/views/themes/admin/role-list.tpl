<div class="well well-sm">
	<h1>{$title}</h1>

	<table style="margin-top:10px;" class="data table table-striped responsive-utilities jambo-table">

		<tr>
			<th>name</th>
			<th>request limit</th>
            <th>api rate limit</th>
			<th>download limit</th>
			<th>invites</th>
			<th>can preview</th>
			<th>hide ads</th>
			<th>donation</th>
			<th>add years</th>
			<th>default roles</th>
			<th>options</th>
		</tr>


		{foreach $userroles as $role}
			<tr class="{cycle values=",alt"}">
				<td><a href="{$smarty.const.WWW_TOP}/admin/role-edit?id={$role.id}">{$role.name}</a></td>
				<td>{$role.apirequests}</td>
                <td>{$role.rate_limit}</td>
				<td>{$role.downloadrequests}</td>
				<td>{$role.defaultinvites}</td>
				<td>{if $role.canpreview == 1}Yes{else}No{/if}</td>
				<td>{if $role.hideads == 1}Yes{else}No{/if}</td>
				<td>{$role.donation}</td>
				<td>{$role.addyears}</td>
				<td>{if $role.isdefault=="1"}Yes{else}No{/if}</td>
				<td><a href="{$smarty.const.WWW_TOP}/admin/role-edit?id={$role.id}">edit</a>&nbsp;{if $role.id>"3"}<a
					class="confirm_action" href="{$smarty.const.WWW_TOP}/admin/role-delete?id={$role.id}">
						delete</a>{/if}
				</td>
			</tr>
		{/foreach}
	</table>
</div>
