 
<h1>{$page->title}</h1>

<a href="?action=update&id={$rage.ID}">Update from TV Rage</a>

<br/><br/>

<form enctype="multipart/form-data" action="{$SCRIPT_NAME}?action=submit" method="POST">

<input type="hidden" name="from" value="{$smarty.get.from}" />

<table class="input">

<tr>
	<td><label for="rageID">Rage Id</label>:</td>
	<td>
		<input type="hidden" name="id" value="{$rage.ID}" />
		<input id="rageID" class="short" name="rageID" type="text" value="{$rage.rageID}" />
		<div class="hint">The numeric TVRage Id.</div>
	</td>
</tr>

<tr>
	<td><label for="releasetitle">Show Name</label>:</td>
	<td>
		<input id="releasetitle" class="long" name="releasetitle" type="text" value="{$rage.releasetitle|escape:'htmlall'}" />
		<div class="hint">The title of the TV show.</div>
	</td>
</tr>

<tr>
	<td><label for="description">Description</label>:</td>
	<td>
		<textarea class="autosize" id="description" name="description">{$rage.description|escape:'htmlall'}</textarea>
	</td>
</tr>

<tr>
	<td><label for="genre">Show Genres</label>:</td>
	<td>
		<input id="genre" class="long" name="genre" type="text" value="{$rage.genre|escape:'htmlall'}" />
		<div class="hint">The genres for the TV show. Separated by pipes ( | )</div>
	</td>
</tr>

<tr>
	<td><label for="country">Show Country</label>:</td>
	<td>
		<input id="country" name="country" type="text" value="{$rage.country|escape:'htmlall'}" maxlength="2" />
		<div class="hint">The country for the TV show.</div>
	</td>
</tr>

<tr>
	<td><label for="imagedata">Series Image</label>:</td>
	<td>
		{if $rage.imgdata != ""}
			<img style="max-width:200px; display:block;" src="{$smarty.const.WWW_TOP}/../getimage?type=tvrage&id={$rage.ID}">
		{/if}
		<input type="file" id="imagedata" name="imagedata">
		<div class="hint">Shown in the TV series view page.</div>
	</td>
</tr>


<tr>
	<td></td>
	<td>
		<input type="submit" value="Save" />
		&nbsp;
		<input onclick="doDelete(); return false;" type="button" value="Delete" />
	</td>
</tr>

</table>

</form>

<script>
function doDelete()
{
	if (confirm('Are you sure?'))
	{
		window.location = "rage-delete.php?id={$rage.ID}&from={$smarty.get.from}";
	}
}
</script>