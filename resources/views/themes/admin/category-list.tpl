<h1>{$title}</h1>

<div class="card card-body">

	<p>
		Make a category inactive to remove it from the menu. This does not prevent binaries being matched into an
		appropriate category. Disable preview prevents ffmpeg being used for releases in the category.
	</p>

	<table style="margin-top:10px;" class="data table table-striped responsive-utilities jambo-table Sortable">

		<tr>
			<th>id</th>
			<th>title</th>
			<th>parent</th>
			<th>min size</th>
			<th>max size</th>
			<th>active</th>
			<th>disable preview</th>
		</tr>

		{foreach $categorylist as $category}
			<tr class="{cycle values=",alt"}">
				<td>{$category.id}</td>
				<td><a href="{$smarty.const.WWW_TOP}/admin/category-edit?id={$category.id}">{$category.title}</a></td>
				<td>
					{if $category.categories != null}
						{$category->parent->title}
					{else}
						n/a
					{/if}
				</td>
				<td class="mid">
					{if $category.minsizetoformrelease != 0 }{$category.minsizetoformrelease|filesize}{/if}
				</td>
				<td class="mid">
					{if $category.maxsizetoformrelease != 0 }{$category.maxsizetoformrelease|filesize}{/if}
				</td>
				<td>{if $category.status == "1"}Yes{else}No{/if}</td>
				<td class="mid">{if $category.disablepreview == "1"}Yes{else}No{/if}</td>
			</tr>
		{/foreach}
	</table>
</div>
