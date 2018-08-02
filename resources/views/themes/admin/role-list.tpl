<div class="well well-sm">
	<h1>{$title}</h1>

	<table style="margin-top:10px;" class="data table table-striped responsive-utilities jambo-table">

		<tr>
			<th>Name</th>
			<th>Request Limit</th>
            <th>API Rate Limit</th>
			<th>Download Limit</th>
			<th>Invites</th>
			<th>Can Preview</th>
			<th>Hide Ads</th>
			<th>Donation</th>
			<th>Add Years</th>
			<th>Default Role</th>
			<th>Options</th>
		</tr>


		{foreach $userroles as $role}
			<tr class="{cycle values=",alt"}">
				<td><a href="{$smarty.const.WWW_TOP}/admin/role-edit?id={$role.id}">{$role.name}</a></td>
				<td>{$role.apirequests}</td>
                <td>{$role.rate_limit}</td>
				<td>{$role.downloadrequests}</td>
				<td>{$role.defaultinvites}</td>
				<td>{if $role->hasPermissionTo('preview') == true}Yes{else}No{/if}</td>
				<td>{if $role->hasPermissionTo('hideads') == true}Yes{else}No{/if}</td>
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
