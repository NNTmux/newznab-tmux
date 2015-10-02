<h1>
	{if $isadmin}
		<a title="Edit AniDB data"
		   href="{$smarty.const.WWW_TOP}/admin/anidb-edit.php?id={$animeAnidbid}&amp;from={$smarty.server.REQUEST_URI|escape:"url"}">{$animeTitle} </a>
	{else}
		{$animeTitle}
	{/if}

	{if $catname != ''} in {$catname|escape:"htmlall"}{/if}
</h1>

<div class="tvseriesheading">
	{if $animeType != ''}<i>({$animeType|escape:"htmlall"})</i>{/if}
	{if animePicture != ""}<img class="shadow" alt="{$animeTitle} Picture"
								src="{$smarty.const.WWW_TOP}/covers/anime/{$animeAnidbid}.jpg" />{/if}
	<p>
		{if $animeCategories != ''}<b>{$animeCategories}</b><br/>{/if}
		<span class="descinitial">{$animeDescription|escape:"htmlall"|nl2br|magicurl|truncate:"1500":" </span><a class=\"descmore\" href=\"#\">more...</a>"}
			{if $animeDescription|strlen > 1500}<span
					class="descfull">{$animeDescription|escape:"htmlall"|nl2br|magicurl}</span>{else}</span>{/if}
		{if $animeRating != ''}<br><b>AniDB Rating: {$animeRating|escape:"htmlall"}</b>{/if}
		{if $animeRelated != ''}<br><i>Related Anime: {$animeRelated|escape:"htmlall"}</i><br/>{/if}
	</p>

</div>

<form id="nzb_multi_operations_form" action="get">

	<div class="nzb_multi_operations">
		<div style="padding-bottom:10px;">
			<a target="_blank"
			   href="{$site->dereferrer_link}http://anidb.net/perl-bin/animedb.pl?show=anime&amp;aid={$animeAnidbid}"
			   title="View AniDB">View AniDB</a> |
			{if $animeTvdbID > 0}<a target="_blank"
									href="{$site->dereferrer_link}http://thetvdb.com/?tab=series&id={$animeTvdbID}"
									title="View TheTVDB">View TheTVDB</a> | {/if}
			{if $animeImdbID > 0}<a target="_blank"
									href="{$site->dereferrer_link}http://www.imdb.com/title/tt{$animeImdbID}"
									title="View IMDb">View IMDb</a> | {/if}
			<a href="{$smarty.const.WWW_TOP}/rss?anidb={$animeAnidbid}&amp;dl=1&amp;i={$userdata.id}&amp;r={$userdata.rsstoken}">RSS
				feed for this Anime</a>
		</div>
		<div class="btn-group">
			<button type="button" class="btn btn-mini nzb_multi_operations_download"><i class="icon-download"></i> Download NZBs</button>
			<button type="button" class="btn btn-mini nzb_multi_operations_cart"><i class="icon-shopping-cart"></i> Add to cart</button>
			{if $sabintegrated}<button type="button" class="btn btn-mini nzb_multi_operations_sab"><i class="icon-download-alt"></i> Send to my Queue</button>{/if}
			{if $isadmin}
				<button type="button" class="btn btn-mini btn-inverse nzb_multi_operations_edit"><i class="icon-edit icon-white"></i></button>
				<button type="button" class="btn btn-mini btn-inverse nzb_multi_operations_delete"><i class="icon-trash icon-white"></i></button>
			{/if}
		</div>
	</div>


	<table style="width:100%;" class="data highlight icons" id="browsetable">
		{foreach $animeEpisodeTitles as $animeEpno => $animeEpisodeTitle}
			<tr>
				<td style="padding-top:15px;" colspan="10"><a href="#top" class="top_link">Top</a>

					<h2>{$animeEpno}</h2></td>
			</tr>
			<tr>
				<th>Name</th>
				<th></th>
				<th>Category</th>
				<th style="text-align:center;">Posted</th>
				<th>Size</th>
				<th>Files</th>
				<th>Stats</th>
				<th></th>
			</tr>
			{foreach $animeEpisodeTitle as $result}
				<tr class="{cycle values=",alt"}" id="guid{$result.guid}">
					<td>
						<a title="View details"
						   href="{$smarty.const.WWW_TOP}/details/{$result.guid}/{$result.searchname|escape:"htmlall"}">{$result.searchname|escape:"htmlall"|replace:".":" "}</a>

						<div class="resextra">
							<div class="btns">
								{if $result.nfoid > 0}<a href="{$smarty.const.WWW_TOP}/nfo/{$result.guid}"
														 title="View Nfo" class="modal_nfo rndbtn" rel="nfo">
										Nfo</a>{/if}
								{if $result.haspreview == 1 && $userdata.canpreview == 1}<a
									href="{$smarty.const.WWW_TOP}/covers/preview/{$result.guid}_thumb.jpg"
									name="name{$result.guid}"title="Screenshot of {$result.searchname|escape:"htmlall"}"
									class="modal_prev rndbtn" rel="preview">Preview</a>{/if}
								{if $result.tvairdate != ""}<span class="rndbtn"
																  title="{$result.tvtitle} Aired on {$result.tvairdate|date_format}">
									Aired {if $result.tvairdate|strtotime > $smarty.now}in future{else}{$result.tvairdate|daysago}{/if}</span>{/if}
								{if $result.reid > 0}<span class="mediainfo rndbtn" title="{$result.guid}">
										Media</span>{/if}
							</div>

							{if $isadmin}
								<div class="admin">
									<a class="rndbtn"
									   href="{$smarty.const.WWW_TOP}/admin/release-edit.php?id={$result.id}&amp;from={$smarty.server.REQUEST_URI|escape:"url"}"
									   title="Edit Release">Edit</a> <a class="rndbtn confirm_action"
																		href="{$smarty.const.WWW_TOP}/admin/release-delete.php?id={$result.id}&amp;from={$smarty.server.REQUEST_URI|escape:"url"}"
																		title="Delete Release">Del</a>
								</div>
							{/if}
						</div>
					</td>
					<td class="check"><input id="chk{$result.guid|substr:0:7}" type="checkbox" class="nzb_check"
											 name="{$seasonnum}" value="{$result.guid}"/></td>
					<td class="less"><a title="This anime in {$result.category_name}"
										href="{$smarty.const.WWW_TOP}/anime/{$result.anidbid}?t={$result.categoryid}">{$result.category_name}</a>
					</td>
					<td class="less mid" width="40" title="{$result.postdate}">{$result.postdate|timeago}</td>
					<td width="40" class="less right">{$result.size|fsize_format:"MB"}{if $result.completion > 0}
							<br/>
							{if $result.completion < 100}<span class="warning">{$result.completion}
								%</span>{else}{$result.completion}%{/if}{/if}</td>
					<td class="less mid"><a title="View file list"
											href="{$smarty.const.WWW_TOP}/filelist/{$result.guid}">{$result.totalpart}</a>
					</td>
					<td width="40" class="less nowrap"><a
								title="View comments for {$result.searchname|escape:"htmlall"}"
								href="{$smarty.const.WWW_TOP}/details/{$result.guid}/#comments">{$result.comments}
							cmt{if $result.comments != 1}s{/if}</a><br/>{$result.grabs}grab{if $result.grabs != 1}s{/if}
					</td>
					<td class="icons">
						<div class="icon icon_nzb"><a title="Download NZB"
													  href="{$smarty.const.WWW_TOP}/getnzb/{$result.guid}/{$result.searchname|escape:"htmlall"}">
								&nbsp;</a></div>
						<div class="icon icon_cart" title="Add to Cart"></div>
						{if $sabintegrated}
							<div class="icon icon_sab" title="Send to my Queue"></div>
						{/if}
					</td>
				</tr>
			{/foreach}
		{/foreach}
	</table>

</form>