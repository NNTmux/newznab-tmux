<div class="card card-body" id="group_list">
	<h1>{$title}</h1>
	{if $check != null}
		<p>
			Below is a list of MultiGroup posters
		</p>
		<table style="width:100%;" class="data table table-striped sortable">
			<tr>
				<th>Poster name</th>
			</tr>
			{foreach $posters as $poster}
				<tr class="{cycle values=",alt"}">
					<td>
						<a href="{$smarty.const.WWW_TOP}/admin/posters-edit/{$poster->id}">{$poster->poster}</a>
					</td>
					<td>
						<a class="confirm_action btn btn-sm btn-danger"
						   href="{$smarty.const.WWW_TOP}/admin/poster-delete/{$poster->id}"
						   title="Delete Poster">Delete poster</a>
					</td>
				</tr>
			{/foreach}
		</table>
	{else}
		<p>No MultiGroup posters available (e.g. none have been added).</p>
	{/if}
</div>
