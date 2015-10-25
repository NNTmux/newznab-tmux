{if isset($nodata) && $nodata !=''}
<div class="header">
	<h2>View > <strong>Anime</strong></h2>
	<p>{$nodata}</p>
</div>
{else}

<div class="header">
	<h2>View > <strong>Anime</strong></h2>
	<div class="breadcrumb-wrapper">
		<ol class="breadcrumb">
			<li><a href="{$smarty.const.WWW_TOP}{$site->home_link}">Home</a></li>
			/ Anime
		</ol>
	</div>
</div>
<h1>
	{$animeTitle}
	{if isset($isadmin)}
		<a class="btn btn-xs btn-warning"
		   title="Edit AniDB data"
		   href="{$smarty.const.WWW_TOP}/admin/anidb-edit.php?id={$animeAnidbid}&amp;
					from={$smarty.server.REQUEST_URI|escape:"url"}">Edit</a>
	{/if}
</h1>
<div>
	{if animePicture != ""}
		<center>
			<img class="shadow img img-polaroid" alt="{$animeTitle} Picture"
				 src="{$smarty.const.WWW_TOP}/covers/anime/{$animeAnidbid}.jpg"/>
		</center>
		<br/>
	{/if}
	<p>
		{if $animeCategories != ''}<b>{$animeCategories}</b><br/>{/if}
		<span class="descinitial">{$animeDescription|escape:"htmlall"|nl2br|magicurl|truncate:"1500":" </span><a class=\"descmore\" href=\"#\"> more...</a>"}
			{if $animeDescription|strlen > 1500}<span
					class="descfull">{$animeDescription|escape:"htmlall"|nl2br|magicurl}</span>{else}</span>{/if}
	</p>
	<p>
		{if $animeRating != ''}<br><b>AniDB Rating: {$animeRating|escape:"htmlall"}</b>{/if}
		{if $animeRelated != ''}<br><i>Related Anime: {$animeRelated|escape:"htmlall"}</i><br/>{/if}
	</p>
</div>
<center>
	<div class="btn-group">
		<a class="btn btn-sm btn-default"
		   href="{$site->dereferrer_link}http://anidb.net/perl-bin/animedb.pl?show=anime&amp;aid={$animeAnidbid}"
		   title="View AniDB">View AniDB</a>
		<a class="btn btn-sm btn-default"
		   href="{$smarty.const.WWW_TOP}/rss?anidb={$animeAnidbid}&amp;dl=1&amp;i={$userdata.id}&amp;r={$userdata.rsstoken}">RSS
			feed for this Anime <i class="fa fa-rss"></i></a>
	</div>
</center>
<form id="nzb_multi_operations_form" action="get">
	<div class="well well-small">
		<div class="nzb_multi_operations">
			With Selected:
			<div class="btn-group">
				<input type="button" class="nzb_multi_operations_download btn btn-sm btn-success"
					   value="Download NZBs"/>
				<input type="button" class="nzb_multi_operations_cart btn btn-sm btn-info" value="Add to Cart"/>
				{if isset($sabintegrated)}
					<input type="button" class="nzb_multi_operations_sab btn btn-sm btn-primary" value="Send to Queue"/>
				{/if}
				{if isset($nzbgetintegrated)}
					<input type="button" class="nzb_multi_operations_nzbget btn btn-sm btn-primary"
						   value="Send to NZBGet"/>
				{/if}
			</div>
			{if isset($isadmin)}
				<div class="pull-right">
					Admin:
					<div class="btn-group">
						<input type="button" class="nzb_multi_operations_edit btn btn-sm btn-warning" value="Edit"/>
						<input type="button" class="nzb_multi_operations_delete btn btn-sm btn-danger" value="Delete"/>
					</div>
				</div>
			{/if}
		</div>
	</div>
	<div class="row">
		<div class="col-xlg-12 portlets">
			<div class="panel panel-default">
				<div class="panel-body pagination2">
					<table style="width:100%;" class="data table table-condensed table-striped table-responsive table-hover"
						   id="browsetable">
						{foreach $animeEpisodeTitles as $result}
							<tr id="guid{$result.guid}">
								<td>
									<a title="View details" href="{$smarty.const.WWW_TOP}/details/{$result.guid}">{$result.searchname|escape:"htmlall"|replace:".":" "}</a>
									<div class="resextra">
										<div class="btns pull-left">
											{if $result.nfoid > 0}<span class="label label-default"><a href="{$smarty.const.WWW_TOP}/nfo/{$result.guid}" title="View Nfo" class="modal_nfo " rel="nfo">Nfo</a></span> {/if}
											{if $result.haspreview == 1 && $userdata.canpreview == 1}<span class="label label-default"><a href="{$smarty.const.WWW_TOP}/covers/preview/{$result.guid}_thumb.jpg" name="name{$result.guid}" title="Screenshot of {$result.searchname|escape:"htmlall"}" class="modal_prev " rel="preview">Preview</a></span> {/if}
											{if $result.reid > 0}<span class="mediainfo label label-default" title="{$result.guid}">Media</span>{/if}
										</div>
										{if $isadmin || $ismod}
											<div class="admin pull-right">
												<span class="label label-warning"><a class="" href="{$smarty.const.WWW_TOP}/admin/release-edit.php?id={$result.id}&amp;from={$smarty.server.REQUEST_URI|escape:"url"}" title="Edit Release">Edit</a></span> <span class="label label-danger"><a class=" confirm_action" href="{$smarty.const.WWW_TOP}/admin/release-delete.php?id={$result.id}&amp;from={$smarty.server.REQUEST_URI|escape:"url"}" title="Delete Release">Del</a></span>
											</div>
										{/if}
									</div>
								</td>
								<td style="text-align:center;"><a title="This anime in {$result.category_name}" href="{$smarty.const.WWW_TOP}/anime/{$result.anidbid}?t={$result.categoryid}">{$result.category_name}</a></td>
								<td style="text-align:center;" title="{$result.postdate}">{$result.postdate|timeago}</td>
								<td style="text-align:center;">{$result.size|fsize_format:"MB"}{if $result.completion > 0}<br>{if $result.completion < 100}<span class="warning">{$result.completion}%</span>{else}{$result.completion}%{/if}{/if}</td>
								<td style="text-align:center;">
									<a title="View file list" href="{$smarty.const.WWW_TOP}/filelist/{$result.guid}">{$result.totalpart}</a>
									{if $result.rarinnerfilecount > 0}
										<div class="rarfilelist">
											<img src="{$smarty.const.WWW_TOP}/themes_shared/images/icons/magnifier.png" alt="{$result.guid}" class="tooltip">
										</div>
									{/if}
								</td>
								<td style="text-align:center;"><a title="View comments for {$result.searchname|escape:"htmlall"}" href="{$smarty.const.WWW_TOP}/details/{$result.guid}#comments">{$result.comments} cmt{if $result.comments != 1}s{/if}</a><br/>{$result.grabs} grab{if $result.grabs != 1}s{/if}</td>
								<td class="icons" style="text-align:center;">
									<div class="icon icon_nzb"><a title="Download Nzb" href="{$smarty.const.WWW_TOP}/getnzb/{$result.guid}">&nbsp;</a></div>
									{if $sabintegrated}<div class="icon icon_sab" title="Send to my Queue"></div>{/if}
									<div class="icon icon_cart" title="Add to Cart"></div>
								</td>
							</tr>
						{/foreach}
					</table>
</form>
{/if}