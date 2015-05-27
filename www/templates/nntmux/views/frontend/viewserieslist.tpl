<h1>{$page->title}</h1>

<div class="pagination pagination-mini">
	<ul style="padding-bottom:1.5px;">
		<li{if $seriesletter == '0-9'} class="active"{/if}>
			<a href="{$smarty.const.WWW_TOP}/series/0-9">0-9</a>
		</li>
		{foreach $seriesrange as $range}
			<li{if $range == $seriesletter} class="active"{/if}>
				<a href="{$smarty.const.WWW_TOP}/series/{$range}">{$range}</a>
			</li>
		{/foreach}
	</ul>

	<ul class="nav nav-pills">
		<li><a href="{$smarty.const.WWW_TOP}/myshows" title="List my watched shows">My Shows</a></li>
		<li><a href="{$smarty.const.WWW_TOP}/myshows/browse" title="browse releases that match your shows">Browse My Shows</a></li>
	</ul>
</div>

<div style="float:right;">
	<form name="ragesearch" action="" method="get">
		&nbsp;&nbsp;<input id="title" type="text" name="title" value="{$ragename}" size="25" />
		&nbsp;&nbsp;
		<button type="submit" class="btn"><i class="icon-search"></i></button>


	</form>

</div>

{if $site->adbrowse != ""}
	<table class="adblock" cellspacing="0" cellpadding="0"><tr><td>{$site->adbrowse}</td></tr></table><br />
{/if}

{if $serieslist|@count > 0}

	<table style="width:100%;" class="table table-bordered table-striped table-hover data highlight icons" id="browsetable">
		{foreach $serieslist as $sletter => $series}
			<tr>
				<td style="padding-top:15px;" colspan="10"><a href="#top" class="top_link">Top</a><h2>{$sletter}...</h2></td>
			</tr>
			<tr>
				<th width="35%">Name</th>
				<th>Country</th>
				<th width="35%">Genre</th>
				<th class="mid">Option</th>
				<th class="mid">View</th>
			</tr>
			{foreach $series as $s}
				<tr class="{cycle values=",alt"}">
					<td><a class="title" title="View series" href="{$smarty.const.WWW_TOP}/series/{$s.rageid}">{$s.releasetitle|escape:"htmlall"}</a>{if $s.prevdate != ''}<br />Last: {$s.previnfo|escape:"htmlall"} aired {$s.prevdate|date_format}{/if}</td>
					<td>{$s.country|escape:"htmlall"}</td>
					<td>{$s.genre|escape:"htmlall"|replace:'|':', '}</td>
					<td class="mid"><div class="btn-group">
							{if $s.userseriesID != ''}

								<a class="btn btn-mini" href="{$smarty.const.WWW_TOP}/myshows/delete/{$s.rageid}?from={$smarty.server.REQUEST_URI|escape:"url"}" class="myshows" rel="remove" name="series{$s.rageid}" title="Remove from My Shows"><i class="icon-minus-sign"></i></a>
								<a class="btn btn-mini" href="{$smarty.const.WWW_TOP}/myshows/edit/{$s.rageid}?from={$smarty.server.REQUEST_URI|escape:"url"}" class="myshows" rel="edit" name="series{$s.rageid}" title="Edit"><i class="icon-edit"></i></a>

							{else}
								<a class="btn btn-mini" href="{$smarty.const.WWW_TOP}/myshows/add/{$s.rageid}?from={$smarty.server.REQUEST_URI|escape:"url"}" class="myshows" rel="add" name="series{$s.rageid}" title="Add to My Shows"><i class="icon-plus-sign"></i></a>
							{/if}
						</div></td>
					<td class="mid">
						<div class="btn-group">
							<a class="btn btn-mini" title="View series" href="{$smarty.const.WWW_TOP}/series/{$s.rageid}">Series</a>
							{if $s.rageid > 0}<a class="btn btn-mini" title="View at TVRage" target="_blank" href="{$site->dereferrer_link}http://www.tvrage.com/shows/id-{$s.rageid}">TVRage</a>
							<a class="btn btn-mini" title="RSS Feed for {$s.releasetitle|escape:"htmlall"}" href="{$smarty.const.WWW_TOP}/rss?rage={$s.rageid}&amp;dl=1&amp;i={$userdata.id}&amp;r={$userdata.rsstoken}"><i class="icon-rss"></i></a>{/if}

						</div>
					</td>
				</tr>
			{/foreach}
		{/foreach}
	</table>

{else}



	<h2>No results</h2>
{/if}