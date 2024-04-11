<div class="card card-body">
	<h1>{$title}</h1>

	{if $releaselist}
		{$releaselist->onEachSide(5)->links()}
		<table style="margin-top:10px;" class="data table table-striped responsive-utilities jambo-table Sortable">

			<tr>
				<th>name</th>
				<th>category</th>
				<th>size</th>
				<th>files</th>
				<th>postdate</th>
				<th>adddate</th>
				<th>grabs</th>
				<th>options</th>
			</tr>

			{foreach $releaselist as $release}
				<tr class="{cycle values=",alt"}">
					<td title="{$release.name}"><a
								href="{{url("/admin/release-edit?id={$release->guid}")}}">{$release->searchname|escape:"htmlall"|wordwrap:75:"\n":true}</a>
					</td>
					<td class="less">{$release->category_name}</td>
					<td class="less right">{$release->size|filesize}</td>
					<td class="less mid"><a href="release-files?id={$release->guid}">{$release->totalpart}</a></td>
					<td class="less">{$result->postdate}</td>
					<td class="less">{$result->adddate}</td>
					<td class="less mid">{$release->grabs}</td>
					<td><a href="{{url("/admin/release-delete/{$release->guid}")}}">delete</a></td>
				</tr>
			{/foreach}

		</table>
		<br/>
		{$releaselist->onEachSide(5)->links()}
	{else}
		<p>No releases available.</p>
	{/if}
</div>
