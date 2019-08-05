<div class="card card-body" id="group_list">
	<h1>{$title}</h1>
	<p>
		Below is a list of all usenet groups available to be indexed. Click 'Activate' to start indexing a group.
		Backfill works independently of active.
	</p>
	{if $grouplist}
		<div class="row" style="position:relative;margin-bottom:5px;">
            <div class="col-md-4">
            {{Form::open(['name' => 'groupsearch', 'style' => 'margin-bottom:5px;'])}}
                {{Form::label('groupname', 'Group')}}
				<input id="groupname" type="text" name="groupname" value="{$groupname}" size="15"/>
				&nbsp;&nbsp;
				<input class="btn btn-success" type="submit" value="Go"/>
			{{Form::close()}}
            </div>
			<div class="col-md-4">
				{$grouplist->onEachSide(5)->links()}
			</div>
			<div class="col-md-4" style="text-align:center;">
				<a title="Reset all groups" href="javascript:ajax_all_reset()" class="all_reset"
				   onclick="return confirm('Are you sure? This will reset all groups, deleting all collections/binaries/parts (does not delete releases).');">Reset
					all</a> |
				<a title="Delete all releases, collections/binaries/parts from all groups"
				   href="javascript:ajax_all_purge()" class="all_purge"
				   onclick="return confirm('Are you sure? This will delete all releases, collections/binaries/parts.');">Purge
					all</a><br/>
				<a title="List all groups Activated for Update_Binaries"
				   href="{$smarty.const.WWW_TOP}/admin/group-list-active">Active Groups</a> |
				<a title="List all groups NOT Activated for Update_Binaries"
				   href="{$smarty.const.WWW_TOP}/admin/group-list-inactive">Inactive Groups</a> |
				<a title="List all groups" href="{$smarty.const.WWW_TOP}/admin/group-list">All Groups</a>
			</div>
		</div>
		<div id="message">{$msg}</div>
		<table style="width:100%;" class="data table table-striped responsive-utilities jambo-table Sortable">
			<tr>
				<th>group</th>
				<th>First Post</th>
				<th>Last Post</th>
				<th>last updated</th>
				<th>active</th>
				<th>backfill</th>
				<th>releases</th>
				<th>Min Files</th>
				<th>Min Size</th>
				<th>Backfill Days</th>
				<th>options</th>
			</tr>
			{foreach from=$grouplist item=group}
				<tr id="grouprow-{$group.id}" class="{cycle values=",alt"}">
					<td>
						<a href="{$smarty.const.WWW_TOP}/admin/group-edit?id={$group.id}">{$group.name|replace:"alt.binaries":"a.b"}</a>
						<div class="hint">{$group.description}</div>
					</td>
					<td class="less">{$group.first_record_postdate}<br/>{$group.first_record_postdate|timeago}</td>
					<td class="less">{$group.last_record_postdate}</td>
					<td class="less">{$group.last_updated|timeago} ago</td>
					<td class="less" id="group-{$group.id}">{if $group.active=="1"}<a
							href="javascript:ajax_group_status({$group.id}, 0)" class="group_active">
								Deactivate</a>{else}<a href="javascript:ajax_group_status({$group.id}, 1)"
													   class="group_deactive">Activate</a>{/if}</td>
					<td class="less" id="backfill-{$group.id}">{if $group.backfill=="1"}<a
							href="javascript:ajax_backfill_status({$group.id}, 0)" class="backfill_active">
								Deactivate</a>{else}<a href="javascript:ajax_backfill_status({$group.id}, 1)"
													   class="backfill_deactive">Activate</a>{/if}</td>
					<td class="less">{$group.num_releases}</td>
					<td class="less">{if $group.minfilestoformrelease==""}n/a{else}{$group.minfilestoformrelease}{/if}</td>
					<td class="less">{if $group.minsizetoformrelease==""}n/a{else}{$group.minsizetoformrelease|filesize}{/if}</td>
					<td class="less">{$group.backfill_target}</td>
					<td class="less" id="groupdel-{$group.id}">
						<a title="Reset this group" href="javascript:ajax_group_reset({$group.id})" class="group_reset">Reset</a>
						|
						<a title="Delete this group and all of its releases"
						   href="javascript:ajax_group_delete({$group.id})" class="group_delete"
						   onclick="return confirm('Are you sure? This will delete the group from this list.');">Delete</a>
						|
						<a title="Remove all releases from this group" href="javascript:ajax_group_purge({$group.id})"
						   class="group_purge"
						   onclick="return confirm('Are you sure? This will delete all releases, binaries/parts in the selected group');">Purge</a>
					</td>
				</tr>
			{/foreach}
		</table>
        <div class="row" style="position:relative;margin-bottom:5px;">
            <div class="col-md-4">
                {{Form::open(['name' => 'groupsearch', 'style' => 'margin-bottom:5px;'])}}
                {{Form::label('groupname', 'Group')}}
                <input id="groupname" type="text" name="groupname" value="{$groupname}" size="15"/>
                &nbsp;&nbsp;
                <input class="btn btn-success" type="submit" value="Go"/>
                {{Form::close()}}
            </div>
            <div class="col-md-4">
                {$grouplist->onEachSide(5)->links()}
            </div>
            <div class="col-md-4" style="text-align:center;">
                <a title="Reset all groups" href="javascript:ajax_all_reset()" class="all_reset"
                   onclick="return confirm('Are you sure? This will reset all groups, deleting all collections/binaries/parts (does not delete releases).');">Reset
                    all</a> |
                <a title="Delete all releases, collections/binaries/parts from all groups"
                   href="javascript:ajax_all_purge()" class="all_purge"
                   onclick="return confirm('Are you sure? This will delete all releases, collections/binaries/parts.');">Purge
                    all</a><br/>
                <a title="List all groups Activated for Update_Binaries"
                   href="{$smarty.const.WWW_TOP}/admin/group-list-active">Active Groups</a> |
                <a title="List all groups NOT Activated for Update_Binaries"
                   href="{$smarty.const.WWW_TOP}/admin/group-list-inactive">Inactive Groups</a> |
                <a title="List all groups" href="{$smarty.const.WWW_TOP}/admin/group-list">All Groups</a>
            </div>
        </div>
	{else}
		<p>No groups available (eg. none have been added).</p>
	{/if}
</div>
