<div class="well well-sm" id="group_list">
	<h1>{$title}</h1>
	{if !empty($check)}
		<p>
			Below is a list of MultiGroup posters
		</p>
		<table style="width:100%;" class="data table table-striped responsive-utilities sortable">
			<tr>
				<th>Poster name</th>
			</tr>
			{foreach from=$posters item=$poster}
				<tr class="{cycle values=",alt"}">
					<td>
						<a href="{$smarty.const.WWW_TOP}/posters-edit?id={$poster->id}&poster={$poster->poster}">{$poster->poster}</a>
					</td>
					<td>
						<a class="confirm_action btn btn-sm btn-danger"
						   href="{$smarty.const.WWW_TOP}/poster-delete?id={$poster->id} from={$smarty.server.REQUEST_URI|escape:"url"}"
						   title="Delete Poster">Delete poster</a>
					</td>
				</tr>
			{/foreach}
		</table>
	{else}
		<p>No MultiGroup posters available (e.g. none have been added).</p>
	{/if}
</div>
