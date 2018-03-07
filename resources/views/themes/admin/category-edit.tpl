
<h1>{$page->title}</h1>

{if isset($error) && $error != ''}
	<div class="error">{$error}</div>
{/if}

<div class="well well-sm">
<a class="btn btn-success" href="{$smarty.const.WWW_TOP}/category-list.php"><i class="fa fa-arrow-left"></i> Go back</a>
<form action="{$SCRIPT_NAME}?action=submit" method="POST">

<table class="input data table table-striped responsive-utilities jambo-table">

<tr>
	<td>Title:</td>
	<td>
		<input type="hidden" name="id" value="{$category.id}" />
		{$category.title}
	</td>
</tr>

<tr>
	<td>Parent:</td>
	<td>
		{$category.parentid}
	</td>
</tr>

<tr>
	<td>Description:</td>
	<td>
		<input type="text" class="long" name="description" value="{$category.description}" />
	</td>
</tr>
	<td>Minimum Size (Bytes):</td>
	<td>
		<input type="text" class="short" name="minsizetoformrelease" value="{$category.minsizetoformrelease}" />
	</td>
</tr>
</tr>
	<td>Maximum Size (Bytes):</td>
	<td>
		<input type="text" class="short" name="maxsizetoformrelease" value="{$category.maxsizetoformrelease}" />
	</td>
</tr>

<tr>
	<td><label for="status">Active</label>:</td>
	<td>
		{html_radios id="status" name='status' values=$status_ids output=$status_names selected=$category.status separator='<br />'}
	</td>
</tr>

<tr>
	<td><label for="disablepreview">Disable Preview</label>:</td>
	<td>
		{html_radios id="disablepreview" name='disablepreview' values=$status_ids output=$status_names selected=$category.disablepreview separator='<br />'}
	</td>
</tr>

<tr>
	<td></td>
	<td>
		<input class="btn btn-default" type="submit" value="Save" />
	</td>
</tr>

</table>

</form>
</div>
