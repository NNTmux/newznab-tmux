<h1>{$page->title}</h1>

<br/>
<table>
{foreach $lists as $key=>$result}
    <tr>
        <th style="vertical-align:top;text-align:right;width:80px;">{$key} :</th>
        <td>
            {foreach $result as $row}
                <a style="display:inline-block;margin:0 0 3px 0" class="btn btn-small" href="{$smarty.const.WWW_TOP}/upcoming/{$row.typeid}">{$row.title|escape:htmlall}</a>
            {/foreach}
        </td>
    </tr>
{/foreach}
</table>
<br/>

{$site->adbrowse}

{if $data|@count > 0}

<table style="width:100%;" class="data highlight icons" id="coverstable">
		<tr>
			<th></th>
			<th>&nbsp;</th>
		</tr>

		{foreach $data as $result}
		<tr class="{cycle values=",alt"}">
			<td class="mid">
				<div class="movcover">
					<center>
					<img class="shadow img img-polaroid" src="{$result->posters->profile|regex_replace:"/(http:\/\/resizing\.flixster\.com\/[a-z0-9\=\-\+\_]+\/[a-z0-9\=\-\+\_]+\/[a-z0-9\=\-\+\_]+\.cloudfront\.net)/i":"https://content6.flixster.com"|replace:array("tmb","ori"):"pro"}" width="120" border="0" alt="{$result->title|escape:"htmlall"}" />
					</center>
					<div class="movextra">
						<center>
						<a class="rndbtn badge badge-success" target="_blank" href="{$site->dereferrer_link}{$result->links->alternate}" title="View Rotten Tomatoes Details">Rotten</a>
						<a class="rndbtn badge badge-imdb" target="_blank" href="{$site->dereferrer_link}http://www.imdb.com/title/tt{$result->alternate_ids->imdb}" title="View Imdb Details">Imdb</a>
						</center>
					</div>
				</div>
			</td>
			<td colspan="3" class="left">
				<h4><a href="{$smarty.const.WWW_TOP}/movies?imdb={$result->alternate_ids->imdb}">{$result->title|escape:"htmlall"}</a> (<a class="title" title="{$result->year}" href="{$smarty.const.WWW_TOP}/movies?year={$result->year}">{$result->year}</a>) {if $result->ratings->critics_score > 0}{$result->ratings->critics_score}/100{/if}</h4>
				{if $result->synopsis == ""}No synopsis. Check <a target="_blank" href="{$site->dereferrer_link}{$result->links->alternate}" title="View Rotten Tomatoes Details">Rotten Tomatoes</a> for more information.{else}{$result->synopsis}{/if}
				{if $result->abridged_cast|@count > 0}
					<br /><br />
					<b>Starring:</b>
					{foreach from=$result->abridged_cast item=cast name=cast}
						<a href="{$smarty.const.WWW_TOP}/movies?actors={$cast->name|escape:"htmlall"}" title="Search for movies starring {$cast->name|escape:"htmlall"}">{$cast->name|escape:"htmlall"}</a>
						{if $smarty.foreach.cast.last}<br/><br/>{else},{/if}
					{/foreach}
				{else}
					<br/><br/>
				{/if}

				{if $ourmovies[$result->alternate_ids->imdb] != ""}
					<a class="rndbtn btn btn-mini btn-success" href="{$smarty.const.WWW_TOP}/movies?imdb={$result->alternate_ids->imdb}">Download</a>
				{else}
					<a {if $userimdbs[$result->alternate_ids->imdb] != ""}style="display:none;"{/if} onclick="mymovie_add('{$result->alternate_ids->imdb}', this);return false;" class="rndbtn btn btn-mini btn-info" href="#">Add To My Movies</a>
				{/if}
				<a style="display:{if $userimdbs[$result->alternate_ids->imdb] != ""}inline{else}none;{/if}" onclick="mymovie_del('{$result->alternate_ids->imdb}', this);return false;" href="#" class="rndbtn btn btn-mini btn-danger">Remove From My Movies</a>
				<br/>


			</td>
		</tr>
		{/foreach}
</table>

{else}
<h2>No results</h2>
{/if}
