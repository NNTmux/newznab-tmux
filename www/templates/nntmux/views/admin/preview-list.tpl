<h1>{$page->title}</h1>

<label for="previewcat"> Select Category :
<select id="previewcat" name="previewcat">
    <option class="grouping" value="-1">All</option>
    {foreach from=$catlist item=parentcat}
        <option {if $previewcat==$parentcat.id}selected="selected"{/if} class="grouping" value="{$parentcat.id}">{$parentcat.title}</option>
        {foreach from=$parentcat.subcatlist item=subcat}
            <option {if $previewcat==$subcat.id}selected="selected"{/if} value="{$subcat.id}">&nbsp;&nbsp;{$subcat.title}</option>
        {/foreach}
    {/foreach}
</select>
</label>

<br/><br/>

{if $releaselist}
{$pager}

<table style="margin-top:10px;" class="data highlight">

	<tr>
		<th>preview</th>
		<th>options</th>
	</tr>

	{foreach from=$releaselist item=release}
	<tr class="{cycle values=",alt"}">
		<td title="{$release.name}">
            <img src="{$smarty.const.WWW_TOP}/../covers/preview/{$release.guid}_thumb.jpg" />
            <br/>
            <a href="{$smarty.const.WWW_TOP}/release-edit.php?id={$release.id}">{$release.searchname|escape:"htmlall"|wordwrap:75:"\n":true}</a>
        </td>
		<td><!--<a href="{$smarty.const.WWW_TOP}/preview-delete.php?id={$release.id}">delete</a>--></td>
	</tr>
	{/foreach}

</table>
    <br/>
    {$pager}
{else}
<p>No results.</p>
{/if}
