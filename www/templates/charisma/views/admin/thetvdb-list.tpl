<h1>{$page->title}</h1>

{if $serieslist}

<div style="float:right;">

	<form name="thetvdbsearch" action="">
		<label for="seriesname">Title</label>
		<input id="seriesname" type="text" name="seriesname" value="{$seriesname}" size="15" />
		&nbsp;&nbsp;
		<input type="submit" value="Go" />
	</form>
</div>

{$pager}

<br/><br/>

<table style="width:100%;margin-top:10px;" class="data Sortable highlight">

	<tr>
		<th style="width:90px;">TheTVDB id</th>
		<th>Title</th>
		<th style="width:120px;" class="mid">Options</th>
	</tr>

	{foreach from=$serieslist item=thetvdb}
	<tr class="{cycle values=",alt"}">
		<td class="less"><a href="{$site->dereferrer_link}http://thetvdb.com/?tab=series&id={$thetvdb.tvdbid}" title="View in TheTVDB">{$thetvdb.tvdbid}</a></td>
		<td><a title="Edit" href="{$smarty.const.WWW_TOP}/thetvdb-edit.php?id={$thetvdb.tvdbid}">{$thetvdb.seriesname|escape:"htmlall"}</a></td>
		<td class="mid"><a title="Delete this TheTVDB entry" href="{$smarty.const.WWW_TOP}/thetvdb-delete.php?id={$thetvdb.tvdbid}">delete</a> | <a title="Remove this tvdbid from all releases" href="{$smarty.const.WWW_TOP}/thetvdb-remove.php?id={$thetvdb.tvdbid}">remove</a></td>
	</tr>
	{/foreach}

</table>
    <br/>
    {$pager}
{else}
<p>No TheTVDB episodes available.</p>
{/if}
