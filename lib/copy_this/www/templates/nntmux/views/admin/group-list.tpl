<div id="group_list">

    <h1>{$page->title}</h1>

		<p>
			Below is a list of all usenet groups available to be indexed. Click 'Activate' to enable a group to be indexed.<br/>
			&bull; Reset will update the last indexed date of the group so it will appear as the first time you have ever activated that group.<br/>
			&bull; Delete will delete the group but leave all releases.<br/>
			&bull; Purge will delete all releases for the group.
		</p>


	<div style="float:right;">

		<form name="groupsearch" method="GET">

			<label for="groupname">Filter</label>
			<input id="groupname" type="text" name="groupname" value="{$groupname}" size="15" />
			&nbsp;&nbsp;
			{html_radios id="active" name='active' values=$active_ids output=$active_names selected=$active separator=' '}
			&nbsp;&nbsp;
			<input type="submit" value="Go" />
		</form>
	</div>

    {if $grouplist}

	{$pager}
	<br/><br/>

    <div id="message">msg</div>
    <table style="width:100%;" class="data highlight Sortable">

        <tr>
            <th>group</th>
            <th>First Post</th>
			<th>Last Post</th>
            <th>last updated</th>
            <th>active</th>
			<th>backfill</th>
            <th>Pre Filter</th>
            <th>releases</th>
			<th>Min Files</th>
			<th>Min Size</th>
            <th>Backfill Days</th>
			<th>options</th>
        </tr>

        {foreach from=$grouplist item=group}
        <tr id="grouprow-{$group.ID}" class="{cycle values=",alt"}">
            <td>
                <a href="{$smarty.const.WWW_TOP}/group-edit.php?id={$group.ID}">{$group.name|replace:"alt.binaries":"a.b"}</a>
                <div class="hint">{$group.description}</div>
            </td>
            <td class="less mid">{$group.first_record_postdate|timeago}</td>
            <td class="less mid">{$group.last_record_postdate|timeago}</td>
            <td class="less">{$group.last_updated|timeago} ago</td>
            <td class="less" id="group-{$group.ID}">{if $group.active=="1"}<a href="javascript:ajax_group_status({$group.ID}, 0)" class="group_active">Deactivate</a>{else}<a href="javascript:ajax_group_status({$group.ID}, 1)" class="group_deactive">Activate</a>{/if}</td>
			<td class="less" id="backfill-{$group.ID}">{if $group.backfill=="1"}<a href="javascript:ajax_backfill_status({$group.ID}, 0)" class="backfill_active">Deactivate</a>{else}<a href="javascript:ajax_backfill_status({$group.ID}, 1)" class="backfill_deactive">Activate</a>{/if}</td>
            <td class="less mid">{if $group.regexmatchonly == "1"}Yes{else}No{/if}</td>
            <td class="less right"><a href="{$smarty.const.WWW_TOP}/../browse?g={$group.name}">{$group.num_releases}</a></td>
            <td class="less mid">{if $group.minfilestoformrelease==""}n/a{else}{$group.minfilestoformrelease}{/if}</td>
            <td class="less mid">{if $group.minsizetoformrelease==""}n/a{else}{$group.minsizetoformrelease|fsize_format:"MB"}{/if}</td>
            <td class="less mid">{$group.backfill_target}</td>
            <td class="less" id="groupdel-{$group.ID}"><a title="Reset this group" href="javascript:ajax_group_reset({$group.ID})" class="group_reset">Reset</a> | <a href="javascript:ajax_group_delete({$group.ID})" class="group_delete confirm_action">Delete</a> | <a href="javascript:ajax_group_purge({$group.ID})" class="group_purge" onclick="return confirm('Are you sure? This will delete all releases, binaries/parts in the selected group');" >Purge</a></td>
        </tr>
        {/foreach}

    </table>

    <br/>{$pager}

    {else}
    <p>No groups available (eg. none have been added).</p>
    {/if}

</div>

