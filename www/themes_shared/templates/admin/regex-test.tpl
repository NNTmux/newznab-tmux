
<h1>{$page->title}</h1>

{if isset($error) && $error != ''}
	<div class="error">{$error}</div>
{/if}

<form action="{$SCRIPT_NAME}" method="GET">
<input type="hidden" name="action" value="test" />
<table class="input">

<tr>
	<td><label for="groupname">Group</label>:</td>
	<td>
		<input type="text" id="groupname" name="groupname" value="{$groupname|escape:html}" />
		or
		<select name="groupid">
		{html_options values=$gid output=$gnames selected=$groupID}
		</select>
	</td>
</tr>

<tr>
	<td><label for="regex">Regex</label>:</td>
	<td>
		<input type="text" id="regex" name="regex" class="long" value="{$regex|escape:html}" />
	</td>
</tr>

<tr>
	<td><label for="regex">Poster</label>:</td>
	<td>
		<input type="text" id="poster" name="poster" class="long" value="{$poster|escape:html}" />
	</td>
</tr>

<tr>
	<td></td>
	<td>
		<input type="checkbox" id="unreleased" name="unreleased" {if $unreleased == 'on'}checked="checked"{/if} /> <label for="unreleased">Ignore binaries that are blacklisted or already matched by an existing regex</label>
	</td>
</tr>

<tr>
    <td></td>
    <td>
        <input type="checkbox" id="matchagainstbins" name="matchagainstbins" {if $matchagainstbins == 'on'}checked="checked"{/if} /> <label for="matchagainstbins">Run regex against binaries table instead of regextesting table</label>
    </td>
</tr>

<tr>
	<td></td>
	<td>
		<input type="submit" value="Test Regex" />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="{$smarty.const.WWW_TOP}/regex-edit.php?action=addtest&regex={$regex|urlencode}&groupname={$group}&poster={$poster|urlencode}">Add Regex</a>
	</td>
</tr>
</table>

</form>

{if $matches}
{$pager}
    <table style="width:100%;margin-top:10px;" class="data Sortable highlight">
        {foreach from=$matches item=match}

            {if $match@iteration mod 10 == 1}
                <tr>
                    <th width="15%">name</th>
                    <th width="21%">poster</th>
                    <th width="8%">Bin Count</th>
                    <th width="8%">Bin Size</th>
                    <th width="10%">Regex id</th>
                    <th width="10%">Req id</th>
                    <th width="20%">Group</th>
                    <th width="8%">Age</th>
                </tr>
            {/if}

            <tr {if $match.bininfo[0].blacklistid == 1}style="background-color:#ff9;"{elseif $match.bininfo[0].regexid != ''}style="background-color:#9f6;"{else}class="{cycle values=",alt"}{/if}">
                <td colspan="8">
                    <table width="100%" class="innerdata">
                        <tr>
                            <td colspan="8"><b>{$match.name|escape:html}</b><br /><small>{$match.bininfo[0].name|escape:html}</small></td>
                        </tr>
                        <tr>
                            <td width="15%"><small>{$match.catname|escape:"htmlall"}</small></td>
                            <td width="21%"><small>{$match.bininfo[0].fromname|escape:"htmlall"}</small></td>
                            <td width="8%"><small>{$match.bincount}{if $match.reltotalparts != ''}/{$match.reltotalparts}{/if}</small></td>
                            <td width="8%"><small>{$match.totalsize|fsize_format:"MB"}</small></td>
                            <td width="10%"><small><a href="regex-list.php#{$match.bininfo[0].regexid}">{$match.bininfo[0].regexid}</a></small></td>
                            <td width="10%"><small>{$match.reqid}</small></td>
                            <td width="20%"><small>{$match.bininfo[0].groupname|replace:"alt.binaries":"a.b"}</small></td>
                            <td width="8%"><small>{$match.bininfo[0].date|timeago}</small></td>
                        </tr>
                    </table>
                </td>
            </tr>
        {/foreach}

    </table>
<br />{$pager}
{/if}

<p>&nbsp;</p>

<h2>Fetch Binaries for Testing</h2>

<form action="{$SCRIPT_NAME}" method="get">
<input type="hidden" name="action" value="fetch" />
<table class="input">

<tr>
	<td><label for="groupname">Group</label>:</td>
	<td>
		<input type="text" id="groupname" name="groupname" value="{$groupname|escape:html}" />
		or
		<select name="groupid">
		{html_options values=$gid output=$gnames selected=$groupID}
		</select>
	</td>
</tr>

<tr>
	<td><label for="numarticles">Articles:</td>
	<td>
		<input type="text" class="short" id="numarticles" name="numarticles" value="{$numarticles}" />
	</td>
</tr>

<tr>
	<td></td>
	<td>
		<input type="checkbox" id="clearexistingbins" name="clearexistingbins" /><label for="clearexistingbins">Clear existing</label>
	</td>
</tr>

<tr>
	<td></td>
	<td>
		<input type="submit" value="Fetch Binaries" />
	</td>
</tr>

</table>

</form>