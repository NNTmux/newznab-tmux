<h1>{$title}</h1>
<div class="card card-body">
	<p>This page lists regex used for grouping usenet collections.</p>
	<div id="message"></div>

	<form name="groupsearch" action="" style="margin-bottom:5px;">
		{{csrf_field()}}
		<label for="group">Search a group:</label>
		<input id="group" type="text" name="group" value="{$group}" size="15"/>
		&nbsp;&nbsp;
		<input class="btn btn-success" type="submit" value="Go"/>
	</form>
	{if $regex}
		<div>{$regex->onEachSide(5)->links()}</div>
		<table style="margin-top:10px;" class="data table table-striped responsive-utilities jambo-table Sortable">
			<tr>
				<th style="width:20px;">id</th>
				<th>group</th>
				<th style="width:25px;">edit</th>
				<th>description</th>
				<th style="width:40px;">delete</th>
				<th>ordinal</th>
				<th>status</th>
				<th>regex</th>
			</tr>
			{foreach from=$regex item=row}
				<tr id="row-{$row.id}" class="{cycle values=",alt"}">
					<td>{$row.id}</td>
					<td>{$row.group_regex}</td>
					<td title="Edit this regex"><a
								href="{{url("/admin/collection_regexes-edit?id={$row.id}")}}">Edit</a></td>
					<td>{$row.description|truncate:50:"...":true}</td>
					<td title="Delete this regex"><a href="javascript:ajax_collection_regex_delete({$row.id})"
													 onclick="return confirm('Are you sure? This will delete the regex from this list.');">Delete</a>
					</td>
					<td>{$row.ordinal}</td>
					{if $row.status==1}
						<td style="color:#00CC66">Active</td>
					{else}
						<td style="color:#FF0000">Disabled</td>
					{/if}
					<td title="Edit this regex"><a
								href="{{url("/admin/collection_regexes-edit?id={$row.id}")}}">{$row.regex|escape:html|truncate:50:"...":true}</a>
					</td>
				</tr>
			{/foreach}
		</table>
		<div style="margin-top: 15px">{$regex->onEachSide(5)->links()}</div>
	{/if}
</div>
