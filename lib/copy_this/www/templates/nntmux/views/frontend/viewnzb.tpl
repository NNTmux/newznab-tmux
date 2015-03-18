
<h1>{$release.searchname|escape:"htmlall"}</h1>

{if $site->addetail != ""}
	<table class="adblock" cellspacing="0" cellpadding="0"><tr><td>{$site->addetail}</td></tr></table><br />
{/if}


<!-- group/etc -->
<span class="label">
    <a style="color:white" title="Browse by {$release.category_name}" href="{$smarty.const.WWW_TOP}/browse?t={$release.categoryid}">{$release.category_name}</a>
</span>
<span class="label">
    <a style="color:white" title="Browse {$release.group_name}" href="{$smarty.const.WWW_TOP}/browse?g={$release.group_name}">{$release.group_name|replace:"alt.binaries":"a.b"}</a>
</span>
{if $predb}
	{if $predb.nuketype != '' && $predb.nukereason != ''}
		<span class="badge label-warning">
			<!--<tr><th>Pre:</th><td>{$predb.ctime|date_format:"%b %e, %Y %T"} ({$predb.ctime|daysago})</td></tr>-->
			<i class="icon-warning-sign icon-white"></i>
			{if preg_match('/^(UN)?((MOD)?NUKED?|DELPRE|MOD|LOCAL)$/', $predb.nuketype)}
				{$predb.nuketype}NUKE:{$predb.nukereason}
			{else}
				{$predb.nukereason} [{$predb.nuketype}]
			{/if}
		</span>
	{/if}
{/if}
{if $site->checkpasswordedrar > 0 && $release.passwordstatus > 0}
	<span class="badge label-warning"><i class="icon-lock icon-white"></i> {if $release.passwordstatus == 2}Passworded Rar Archive{elseif $release.passwordstatus == 1}Contains Cab/Ace/Rar Inside Archive{else}Unknown{/if}
    </span>
{/if}

{foreach from=$reAudio item=audio}
	{if $audio.audiolanguage != ""}
		<i class="icon-flag {$audio.audioflag}" title="{$audio.audiolanguage}-{$audio.audioformat}"></i>
	{/if}
{/foreach}
<br />

<!-- ### -- IMAGE ON RIGHT -->
{if $rage && $release.rageid > 0 && $rage.imgdata != ""}<img class="img-rounded" src="{$smarty.const.WWW_TOP}/getimage?type=tvrage&amp;id={$rage.id}" width="220" height="auto" alt="{$rage.releasetitle|escape:"htmlall"}" style="float:right;" />{/if}
{if $movie && $release.rageid < 0 && $movie.cover == 1}<img class="img-rounded" src="{$smarty.const.WWW_TOP}/covers/movies/{$movie.imdbid}-cover.jpg" width="220" height="auto" alt="{$movie.title|escape:"htmlall"}" style="float:right;" />{/if}
{if $game && $game.cover == 1}<img class="img-rounded" src="{$smarty.const.WWW_TOP}/covers/games/{$game.id}.jpg" width="160"
								   alt="{$con.title|escape:"htmlall"}" style="float:right;" />{/if}
{if $xxx && $xxx.cover == 1}<img class="img-rounded" src="{$smarty.const.WWW_TOP}/covers/xxx/{$xxx.id}-cover.jpg" width="160"
								 alt="{$movie.title|escape:"htmlall"}" style="float:right;" />{/if}
{if $anidb && $release.anidbid > 0 && $anidb.picture != ""}<img class="img-rounded" src="{$smarty.const.WWW_TOP}/covers/anime/{$anidb.anidbid}.jpg" width="220" alt="{$anidb.title|escape:"htmlall"}" style="float:right;" />{/if}
{if $con && $con.cover == 1}<img class="img-rounded" src="{$smarty.const.WWW_TOP}/covers/console/{$con.id}.jpg" width="220" alt="{$con.title|escape:"htmlall"}" style="float:right;" />{/if}
{if $music && $music.cover == 1}<img class="img-rounded" src="{$smarty.const.WWW_TOP}/covers/music/{$music.id}.jpg" width="220" alt="{$music.title|escape:"htmlall"}" style="float:right;" />{/if}
{if $book && $book.cover == 1}<img class="img-rounded" src="{$smarty.const.WWW_TOP}/covers/book/{$book.id}.jpg" width="220" alt="{$book.title|escape:"htmlall"}" style="float:right;" />{/if}

<!-- ### -- MEDIA DESCRIPTION -->
{if $rage && $release.rageid > 0}
	<!-- TV Info -->
	<strong>{if $release.tvtitle != ""}{$release.tvtitle|escape:"htmlall"} - {/if}{$release.seriesfull|replace:"S":"Season "|replace:"E":" Episode "}</strong><br />
	{if $rage.description != ""}<span class="descinitial">{$rage.description|escape:"htmlall"|nl2br|magicurl|truncate:"350":"</span><a class=\"descmore\" href=\"#\">more...</a>"}{if $rage.description|strlen > 350}<span class="descfull">{$rage.description|escape:"htmlall"|nl2br|magicurl}</span>{else}{/if}<br /><br />{/if}
{if $rage.genre != ""}<strong>Genre:</strong> {$rage.genre|escape:"htmlall"|replace:"|":", "}<br />{/if}
{if $release.tvairdate != ""}<strong>Aired:</strong> {$release.tvairdate|date_format}<br/>{/if}
{if $rage.country != ""}<strong>Country:</strong> {$rage.country}<br/>{/if}
{if $episode && $release.episodeinfoid > 0}
{if $episode.overview != ""}<strong>Overview:</strong> <span class="descinitial">{$episode.overview|escape:"htmlall"|nl2br|magicurl|truncate:"350":"</span><a class=\"descmore\" href=\"#\">more...</a>"}{if $episode.overview|strlen > 350}<span class="descfull">{$episode.overview|escape:"htmlall"|nl2br|magicurl}</span>{else}{/if}<br /><br />{/if}
{if $episode.rating > 0}<strong>Rating:</strong> {$episode.rating}/10 <div class="progress progress-striped" style="width:150px">
	<div class="bar" style="width: {$episode.rating * 10}%;"></div>
</div> {/if}
{if $episode.director != ""}<strong>Director:</strong> {$episode.director|escape:"htmlall"|replace:"|":", "}{/if}
{if $episode.writer != ""}<strong>Writer:</strong> {$episode.writer|escape:"htmlall"|replace:"|":", "}{/if}
{if $episode.gueststars != ""}<strong>Guest Stars:</strong> {$episode.gueststars|escape:"htmlall"|replace:"|":", "}{/if}
{/if}<br /><div class="btn-group">
	<a class="btn btn-mini" title="View all episodes from this series" href="{$smarty.const.WWW_TOP}/series/{$release.rageid}">All Episodes</a>
	<a class="btn btn-mini" target="_blank" href="{$site->dereferrer_link}http://www.tvrage.com/shows/id-{$release.rageid}" title="View at TV Rage">TV Rage</a>
	{if $release.tvdbid > 0}<a class="btn btn-mini" target="_blank" href="{$site->dereferrer_link}http://thetvdb.com/?tab=series&id={$release.tvdbid}&lid=7" title="View at TheTVDB">TheTVDB</a>{/if}
	<a class="btn btn-mini" href="{$smarty.const.WWW_TOP}/rss?rage={$release.rageid}&amp;dl=1&amp;i={$userdata.id}&amp;r={$userdata.rsstoken}" title="Rss feed for this series">Series Rss Feed</a></div>
{/if}
{if $movie && $release.rageid < 0}
	<!-- Movie Info -->
	<strong>{$movie.title|escape:"htmlall"} ({$movie.year})</strong>
	{if $movie.tagline != ''}<br />{$movie.tagline|escape:"htmlall"}{/if}
    {if $movie.plot != ''}{if $movie.tagline != ''} - {else}<br />{/if}{$movie.plot|escape:"htmlall"}{/if}
    <br /><br />{if $movie.director != ""} <strong>Director:</strong> {$movie.director}<br />{/if}
    <strong>Genre:</strong> {$movie.genre}
    <br /><strong>Starring:</strong> {$movie.actors}<br />
	{if $movie.trailer != ''}
	<br/>
	<strong>Trailer:</strong>
	<div>{$movie.trailer}</div>
{/if}
	<strong>Rating:</strong>
	{if $movie.rating == ''}N/A{else}{$movie.rating}/10
	<div class="progress progress-striped" style="width:150px">
		<div class="bar" style="width: {$movie.rating * 10}%;"></div>
	</div> {/if}
	<div class="btn-group">
	<a class="btn btn-mini" target="_blank" href="{$site->dereferrer_link}http://www.imdb.com/title/tt{$release.imdbid}/" title="View at IMDB">IMDB</a>
	{if $movie.tmdbID != ''}<a class="btn btn-mini" target="_blank" href="{$site->dereferrer_link}http://www.themoviedb.org/movie/{$movie.tmdbID}" title="View at TMDb">TMDb</a>{/if}
	<a class="btn btn-mini" href="{$smarty.const.WWW_TOP}/movies?imdb={$release.imdbid}" title="View all versions">Movie View</a>
	<a
			class="btn btn-mini" target="blackhole"
			href="javascript:;"
			rel="{$site->dereferrer_link}{$cpurl}/api/{$cpapi}/movie.add/?identifier=tt{$release.imdbid}&title={$movie.title}"
			name="CP{$release.imdbid}" title="Add to CouchPotato">
		CouchPotato
	</a>
	<a class="btn btn-mini" target="_blank" href="http://www.opensubtitles.org/search/sublanguageid-all/moviename-{$movie.title|replace:" ":"+"}"title="Opensubtitles">OpenSubtitles</a>
	<a class="btn btn-mini" target="_blank" href="http://www.subtitleseeker.com/search/MOVIE_TITLES/{$movie.title}"title="SubtitleSeeker">SubtitleSeeker</a>
</div>

{/if}
{if $anidb && $release.anidbid > 0}
<!-- ANIME INFO -->
    <strong>{if $release.tvtitle != ""}{$release.tvtitle|escape:"htmlall"}{/if}</strong><br />
{if $anidb.description != ""}<span class="descinitial">{$anidb.description|escape:"htmlall"|nl2br|magicurl|truncate:"350":"</span><a class=\"descmore\" href=\"#\">more...</a>"}{if $anidb.description|strlen > 350}<span class="descfull">{$anidb.description|escape:"htmlall"|nl2br|magicurl}</span>{else}{/if}<br /><br />{/if}
{if $anidb.categories != ""}<strong>Categories:</strong> {$anidb.categories|escape:"htmlall"|replace:"|":", "}<br />{/if}
{if $release.tvairdate != "0000-00-00 00:00:00"}<strong>Aired:</strong> {$release.tvairdate|date_format}<br/>{/if}
{if $episode && $release.episodeinfoid > 0}
	{if $episode.overview != ""}<strong>Overview:</strong> {$episode.overview}{/if}
				{if $episode.rating > 0}<strong>Rating:</strong> {$episode.rating}{/if}
				{if $episode.director != ""}<strong>Director:</strong> {$episode.director|escape:"htmlall"|replace:"|":", "}{/if}
				{if $episode.gueststars != ""}<strong>Guest Stars:</strong> {$episode.gueststars|escape:"htmlall"|replace:"|":", "}{/if}
				{if $episode.writer != ""}<strong>Writer:</strong> {$episode.writer|escape:"htmlall"|replace:"|":", "}{/if}
			{/if}<br /><br />
    <div class="btn-group">
		<a class="btn btn-mini" title="View all episodes from this anime" href="{$smarty.const.WWW_TOP}/anime/{$release.anidbid}">All Episodes</a>
		<a class="btn btn-mini" target="_blank" href="{$site->dereferrer_link}http://anidb.net/perl-bin/animedb.pl?show=anime&aid={$anidb.anidbid}" title="View at AniDB">AniDB</a>
		{if $release.tvdbid > 0}<a class="btn btn-mini" target="_blank" href="{$site->dereferrer_link}http://thetvdb.com/?tab=series&id={$release.tvdbid}&lid=7" title="View at TheTVDB">TheTVDB</a>{/if}
		<a class="btn btn-mini" href="{$smarty.const.WWW_TOP}/rss?anidb={$release.anidbid}&amp;dl=1&amp;i={$userdata.id}&amp;r={$userdata.rsstoken}" title="RSS feed for this anime">Anime RSS Feed</a>
	</div>
{/if}
{if $con}
	<!-- Console Info -->
	{$con.title|escape:"htmlall"} ({$con.releasedate|date_format:"%Y"})
	{if $con.review != ""}{$con.review|escape:"htmlall"|nl2br|magicurl|truncate:"350":"more..."}{if $con.review|strlen > 350}{$con.review|escape:"htmlall"|nl2br|magicurl}{else}{/if}

	{/if} {if $con.esrb != ""}ESRB: {$con.esrb|escape:"htmlall"}
{/if} {if $con.genres != ""}Genre: {$con.genres|escape:"htmlall"}
{/if} {if $con.publisher != ""}Publisher: {$con.publisher|escape:"htmlall"}
{/if} {if $con.platform != ""}Platform: {$con.platform|escape:"htmlall"}
{/if} {if $con.releasedate != ""}Released: {$con.releasedate|date_format}{/if} {if $con.url != ""} Amazon
{/if}
{/if}
{if $game}
	<!-- Game Info -->
	<strong>{$game.title|escape:"htmlall"} ({$game.releasedate|date_format:"%Y"})</strong><br/>
	{if $game.review != ""}<span
			class="descinitial">{$game.review|escape:"htmlall"|nl2br|magicurl|truncate:"350":" <a class=\"descmore\" href=\"#\">more...</a>"}</span>{if $game.review|strlen > 350}
				<span class="descfull">{$game.review|escape:"htmlall"|nl2br|magicurl}</span>{/if}
				<br/>
		<br/>
	{/if}
			{if $game.esrb != ""}<strong>ESRB:</strong>{$game.esrb|escape:"htmlall"}<br/>{/if}
			{if $game.genres != ""}<strong>Genre:</strong>{$game.genres|escape:"htmlall"}<br/>{/if}
			{if $game.publisher != ""}<strong>Publisher:</strong>{$game.publisher|escape:"htmlall"}<br/>{/if}
			{if $game.platform != ""}<strong>Platform:</strong>{$game.platform|escape:"htmlall"}<br/>{/if}
			{if $game.releasedate != ""}<strong>Released:</strong>{$game.releasedate|date_format}{/if}
			<div style="margin-top:10px;">
	{if $game.classused == "gb"}
		<a class="btn btn-mini" target="_blank" href="{$site->dereferrer_link}{$game.url}"
		   title="View game at Giantbomb">Giantbomb</a>
	{/if}
	{if $game.classused == "steam"}
		<a class="btn btn-mini" target="_blank" href="{$site->dereferrer_link}{$game.url}"
		   title="View game at Steam">Steam</a>
	{/if}
</div>
{/if}

{if $xxx}
	<!-- XXX Info -->
	<strong>{$xxx.title|stripslashes|escape:"htmlall"}</strong>
	{if $xxx.tagline != ''}<br/>{$xxx.tagline|stripslashes|escape:"htmlall"}{/if}
			{if $xxx.plot != ''}{if $xxx.tagline != ''} - {else}<br/>{/if}{$xxx.plot|stripslashes|escape:"htmlall"}{/if}
			<br/><br/>{if $xxx.director != ""} <strong>Director:</strong> {$xxx.director}<br/>{/if}
			<strong>Genre:</strong> {$xxx.genres}
			<br/><strong>Starring:</strong> {$xxx.actors}
			{if $xxx.trailer != ''}
	<br/>
	<strong>Trailer:</strong>
	<div>{$xxx.trailer}</div>
{/if}
			<div style="margin-top:10px;">
	{if $xxx.classused === "ade"}
		<a class="btn btn-mini" target="_blank" href="{$site->dereferrer_link}{$xxx.directurl}"
		   title="View at Adult DVD Empire">ADE</a>
	{elseif $xxx.classused === "pop"}
		<a class="btn btn-mini" target="_blank" href="{$site->dereferrer_link}{$xxx.directurl}/"
		   title="View at Popporn">Popporn</a>
	{elseif $xxx.classused === "aebn"}
		<a class="btn btn-mini" target="_blank" href="{$site->dereferrer_link}{$xxx.directurl}"
		   title="View at Adult Entertainment Broadcast Network">AEBN</a>
	{else}
		<a class="btn btn-mini" target="_blank" href="{$site->dereferrer_link}{$xxx.directurl}"
		   title="View at Hot Movies">HM</a>
	{/if}
	<a class="btn btn-mini" target="_blank"
	   href="{$site->dereferrer_link}http://www.iafd.com/results.asp?searchtype=title&searchstring={$xxx.title}"
	   title="Search IAFD">IAFD</a>
</div>
{/if}
{if $book}
<!-- Book info -->
    <strong>{$book.author|escape:"htmlall"} - {$book.title|escape:"htmlall"}</strong><br />
{if $book.review != ""}<span class="descinitial">{$book.review|escape:"htmlall"|nl2br|magicurl|truncate:"350":"</span><a class=\"descmore\" href=\"#\">more...</a>"}{if $book.review|strlen > 350}<span class="descfull">{$book.review|escape:"htmlall"|nl2br|magicurl}</span>{else}{/if}<br /><br />{/if}
{if $book.ean != ""}<strong>EAN:</strong> {$book.ean|escape:"htmlall"}<br />{/if}
{if $book.isbn != ""}<strong>ISBN:</strong> {$book.isbn|escape:"htmlall"}<br />{/if}
{if $book.pages != ""}<strong>Pages:</strong> {$book.pages|escape:"htmlall"}<br />{/if}
{if $book.dewey != ""}<strong>Dewey:</strong> {$book.dewey|escape:"htmlall"}<br />{/if}
{if $book.publisher != ""}<strong>Publisher:</strong> {$book.publisher|escape:"htmlall"}<br />{/if}
{if $book.publishdate != ""}<strong>Publish Date:</strong> {$book.publishdate|date_format}{/if}
{if $book.url != ""}<br /><br />
	<a class="btn btn-mini" target="_blank" href="{$site->dereferrer_link}{$book.url}/" title="View book at Amazon">Amazon</a>
{/if}
{/if}
{if $music}
<!-- Music Info -->
    <strong>{$music.title|escape:"htmlall"} {if $music.year != ""}({$music.year}){/if}</strong><br />
{if $music.review != ""}<span class="descinitial">{$music.review|nl2br|magicurl|truncate:"350":"</span><a class=\"descmore\" href=\"#\">more...</a>"}{if $music.review|strlen > 350}<span class="descfull">{$music.review|escape:"htmlall"|nl2br|magicurl}</span>{else}{/if}<br /><br />{/if}
{if $music.genres != ""}<strong>Genre:</strong> {$music.genres|escape:"htmlall"}<br />{/if}
{if $music.publisher != ""}<strong>Publisher:</strong> {$music.publisher|escape:"htmlall"}<br />{/if}
{if $music.releasedate != ""}<strong>Released:</strong> {$music.releasedate|date_format}<br />{/if}
{if $music.tracks != ""}
	<strong>Track Listing:</strong>
	<ol class="tracklist">
		{assign var="tracksplits" value="|"|explode:$music.tracks}
		{foreach from=$tracksplits item=tracksplit}
			<li>{$tracksplit|trim|escape:"htmlall"}</li>
		{/foreach}
	</ol>
{/if}
{if $music.url != ""} <br /><br />
	<a class="btn btn-mini" target="_blank" href="{$site->dereferrer_link}{$music.url}/" title="View record at Amazon">Amazon</a>
{/if}

{/if}
<br /><br />
<!-- NAV-TAB PILLS -->
<ul class="nav nav-tabs">
	<li class="active"><a href="#details" data-toggle="tab" style="color: black;"><i class="icon-time"></i> Details</a></li>
	{if $reVideo.releaseid|@count > 0 || $reAudio|@count > 0}
		<li><a href="#mediainfo" data-toggle="tab" style="color: black;"><i class="icon-info-sign"></i> Media Info</a></li>
	{/if}
	{if $nfo.id|@count > 0}
		<li><a href="#viewnfo" data-toggle="tab" style="color: black;"><i class="icon-file"></i> View NFO</a></li>
	{/if}
	<li><a href="#fileinfo" data-toggle="tab" style="color: black;"><i class="icon-folder-open"></i> File Info</a></li>
	<li><a href="#nzbcontents" data-toggle="tab" style="color: black;"><i class="icon-list"></i> NZB Contents</a></li>

	{if ($release.haspreview == 1 && $userdata.canpreview == 1) || ($release.haspreview == 2 && $userdata.canpreview == 1)}
		<li><a href="#preview" data-toggle="tab" style="color: black;"><i class="icon-picture"></i> Preview</a></li>
	{/if}
	{if ($release.jpgstatus == 1 && $userdata.canpreview == 1)}
		<li><a href="#sample" data-toggle="tab" style="color: black;"><i class="icon-picture"></i> Sample</a></li>
	{/if}
	{if ($release.videostatus == 1 && $userdata.canpreview == 1)}
		<li><a href="#video" data-toggle="tab" style="color: black;"><i class="icon-picture"></i> Video</a></li>
	{/if}
	{if ($release.audiostatus == 1 && $userdata.canpreview == 1)}
		<li><a href="#audio" data-toggle="tab" style="color: black;"><i class="icon-picture"></i> Audio</a></li>
	{/if}
	{if $isadmin}
		<li><a href="#admin" data-toggle="tab" style="color: black;"><i class="icon-font"></i> Admin Info</a></li>
	{/if}
	<li><a href="#comments" data-toggle="tab" style="color:black;"><i class="icon-comment"></i> Comments</a></li>
</ul>

<div class="tab-content">
<div class="tab-pane active" id="details">
	<table class="table " id="detailstable" >
		<tr><th>Group:</th><td title="{$release.group_name}"><a title="Browse {$release.group_name}" href="{$smarty.const.WWW_TOP}/browse?g={$release.group_name}">{$release.group_name|replace:"alt.binaries":"a.b"}</a></td></tr>
		<tr><th>Category:</th><td><a title="Browse by {$release.category_name}" href="{$smarty.const.WWW_TOP}/browse?t={$release.categoryid}">{$release.category_name}</a></td></tr>
		<tr><th>Size:</th><td>{$release.size|fsize_format:"MB"}{if $release.completion > 0}&nbsp;({if $release.completion < 100}<span class="warning">{$release.completion}%</span>{else}{$release.completion}%{/if}){/if}</td></tr>
		<tr><th>Grabs:</th><td>{$release.grabs} time{if $release.grabs==1}{else}s{/if}</td></tr>
		{if $release.name != $release.searchname}
			<tr><th>Original Name:</th><td title="{$release.name}">{$release.name}</td></tr>
		{/if}
		<tr><th>Poster:</th><td>{$release.fromname|escape:"htmlall"}</td></tr>
		{if $predb}
			<tr><th>Pre:</th><td>{$predb.ctime|date_format:"%b %e, %Y %T"} ({$predb.ctime|daysago})</td></tr>
			{if $predb.nuketype != '' && $predb.nukereason != '' && $predb.nuketime != 0}
				<tr><th>Nuked:</th><td>{$predb.nuketime|date_format:"%b %e, %Y %T"} ({$predb.nuketime|daysago})</td></tr>
			{/if}
		{/if}
		{if $prehash|@count > 0}
			<tr>
				<th>Prehash:</th>
				<td style="padding:0;">
					<table style="width:100%;">
						<tr>
							<th>Title</th>
							<th class="mid">Date</th>
							<th class="mid">Source</th>
							<th class="mid">Size</th>
							<th class="mid">Files</th>
							<th class="mid">Nukereason</th>
						</tr>
						{foreach from=$prehash item=pd}
							<tr>
								<td>{$pd.title}</td>
								<td class="mid">{$pd.predate|date_format}</td>
								<td class="mid">{$pd.source}</td>
								{if isset($pd.size)}{if $pd.size > 0}
									<td class="right">{$pd.size}</td>{/if}{/if}
								{if isset($pd.files)}{if $pd.files != ''}
									<td class="right">{$pd.files}</td>{/if}{/if}
								{if isset($pd.nuked) && $pd.nuked > 0 && $pd.nukereason !=''}
									<td class="right">{$pd.nukereason}</td>{/if}
							</tr>
						{/foreach}
					</table>
				</td>
			</tr>
		{/if}
		<tr><th>Posted:</th><td title="{$release.postdate}">{$release.postdate|date_format:"%b %e, %Y %T"} ({$release.postdate|daysago})</td></tr>
		<tr><th>Added:</th><td title="{$release.adddate}">{$release.adddate|date_format:"%b %e, %Y %T"} ({$release.adddate|daysago})</td></tr>
		<tr id="guid{$release.guid}"><th>Download:</th><td>
				<div class="icon icon_nzb"><a title="Download Nzb" href="{$smarty.const.WWW_TOP}/getnzb/{$release.guid}/{$release.searchname|escape:"htmlall"}">&nbsp;</a></div>
				<div class="icon icon_cart" title="{$themevars.cart_name_add}"></div>
				{if $sabintegrated}<div class="icon icon_sab" title="Send to my Queue"></div>{/if}
			</td></tr>
		<tr>
			<th>Similar:</th>
			<td>
				<a title="Search for similar Nzbs" href="{$smarty.const.WWW_TOP}/search/{$searchname|escape:"url"}">Search for similar</a><br/>
			</td>
		</tr>

	</table>
</div>
{if $reVideo.releaseid|@count > 0 || $reAudio|@count > 0}
	<div class="tab-pane" id="mediainfo">
		<table style="width:100%;" class="table table-bordered table-hover">
			<tr>
				<th width="15%"></th>
				<th>Property</th>
				<th class="right">Value</th>
			</tr>
			{if $reVideo.containerformat != ""}
				<tr>
					<td style="width:15%;"><strong>Overall</strong></td>
					<td>Container Format</td>
					<td class="right">{$reVideo.containerformat}</td>
				</tr>
			{/if}
			{if $reVideo.overallbitrate != ""}
				<tr>
					<td></td>
					<td>Bitrate</td>
					<td class="right">{$reVideo.overallbitrate}</td>
				</tr>
			{/if}
			{if $reVideo.videoduration != ""}
				<tr>
					<td><strong>Video</strong></td>
					<td>Duration</td>
					<td class="right">{$reVideo.videoduration}</td>
				</tr>
			{/if}
			{if $reVideo.videoformat != ""}
				<tr>
					<td></td>
					<td>Format</td>
					<td class="right">{$reVideo.videoformat}</td>
				</tr>
			{/if}
			{if $reVideo.videocodec != ""}
				<tr>
					<td></td>
					<td>Codec</td>
					<td class="right">{$reVideo.videocodec}</td>
				</tr>
			{/if}
			{if $reVideo.videowidth != "" && $reVideo.videoheight != ""}
				<tr>
					<td></td>
					<td>Width x Height</td>
					<td class="right">{$reVideo.videowidth}x{$reVideo.videoheight}</td>
				</tr>
			{/if}
			{if $reVideo.videoaspect != ""}
				<tr>
					<td></td>
					<td>Aspect</td>
					<td class="right">{$reVideo.videoaspect}</td>
				</tr>
			{/if}
			{if $reVideo.videoframerate != ""}
				<tr>
					<td></td>
					<td>Framerate</td>
					<td class="right">{$reVideo.videoframerate} fps</td>
				</tr>
			{/if}
			{if $reVideo.videolibrary != ""}
				<tr>
					<td></td>
					<td>Library</td>
					<td class="right">{$reVideo.videolibrary}</td>
				</tr>
			{/if}
			{foreach from=$reAudio item=audio}
				<tr>
					<td><strong>Audio {$audio.audioID}</strong></td>
					<td>Format</td>
					<td class="right">{$audio.audioformat}</td>
				</tr>
				{if $audio.audiolanguage != ""}
					<tr>
						<td></td>
						<td>Language</td>
						<td class="right">{$audio.audiolanguage}</td>
					</tr>
				{/if}
				{if $audio.audiotitle != ""}
					<tr>
						<td></td>
						<td>Title</td>
						<td class="right">{$audio.audiotitle}</td>
					</tr>
				{/if}
				{if $audio.audiomode != ""}
					<tr>
						<td></td>
						<td>Mode</td>
						<td class="right">{$audio.audiomode}</td>
					</tr>
				{/if}
				{if $audio.audiobitratemode != ""}
					<tr>
						<td></td>
						<td>Bitrate Mode</td>
						<td class="right">{$audio.audiobitratemode}</td>
					</tr>
				{/if}
				{if $audio.audiobitrate != ""}
					<tr>
						<td></td>
						<td>Bitrate</td>
						<td class="right">{$audio.audiobitrate}</td>
					</tr>
				{/if}
				{if $audio.audiochannels != ""}
					<tr>
						<td></td>
						<td>Channels</td>
						<td class="right">{$audio.audiochannels}</td>
					</tr>
				{/if}
				{if $audio.audiosamplerate != ""}
					<tr>
						<td></td>
						<td>Sample Rate</td>
						<td class="right">{$audio.audiosamplerate}</td>
					</tr>
				{/if}
				{if $audio.audiolibrary != ""}
					<tr>
						<td></td>
						<td>Library</td>
						<td class="right">{$audio.audiolibrary}</td>
					</tr>
				{/if}
			{/foreach}
			{if $reSubs.subs != ""}
				<tr>
					<td><strong>Subtitles</strong></td>
					<td>Languages</td>
					<td class="right">{$reSubs.subs|escape:"htmlall"}</td>
				</tr>
			{/if}
		</table>

	</div>
{/if}
{if $nfo.id|@count > 0}
	<div class="tab-pane" id="viewnfo">
	</div>
{/if}
<div class="tab-pane" id="fileinfo">
	<table class="table table-bordered" id="detailstable" >
		{if $site->checkpasswordedrar > 0}
			<tr><th>Password:</th>
				<td>
					{if $release.passwordstatus == 0}None{elseif $release.passwordstatus == 2}Passworded Rar Archive{elseif $release.passwordstatus == 1}Contains Cab/Ace/Rar Inside Archive{else}Unknown{/if}
				</td>
			</tr>
		{/if}
		{if $releasefiles|@count > 0}
			<tr><th>Rar Contains:</th>
				<td style="padding:0px;">
					<table style="width:100%;" class="table table-striped">
						<tr>
							<th>Filename</th>
							<th class="mid">Password</th>
							<th class="mid">Size</th>
							<th class="mid">Date</th>
						</tr>
						{foreach from=$releasefiles item=rf}
							<tr>
								<td>{$rf.name}</td>
								<td class="mid">{if $rf.passworded != 1}No{else}Yes{/if}</td>
								<td class="right">{$rf.size|fsize_format:"MB"}</td>
								<td title="{$rf.createddate}" class="right" >{$rf.createddate|date_format}</td>
							</tr>
						{/foreach}
					</table>
				</td>
			</tr>
		{/if}
	</table>
</div>
<div class="tab-pane" id="nzbcontents"></div>
{if $release.haspreview == 1 && $userdata.canpreview == 1}
	<div class="tab-pane" id="preview">
		<img class="img-rounded" width="770" src="{$smarty.const.WWW_TOP}/covers/preview/{$release.guid}_thumb.jpg" alt="{$release.searchname|escape:"htmlall"} screenshot" />
	</div>
{/if}
{if $release.haspreview == 2 && $userdata.canpreview == 1}
	<div class="tab-pane" id="preview">
		<a href="#" name="audio{$release.guid}" title="Listen to {$release.searchname|escape:"htmlall"}" class="audioprev btn" rel="audio">Listen</a><audio id="audprev{$release.guid}" src="{$smarty.const.WWW_TOP}/covers/audio/{$release.guid}.mp3" preload="none"></audio>
	</div>
{/if}
{if $release.jpgstatus == 1 && $userdata.canpreview == 1}
	<div class="tab-pane" id="sample">
		<img class="img-rounded" width="770" src="{$smarty.const.WWW_TOP}/covers/sample/{$release.guid}_thumb.jpg"
			 alt="{$release.searchname|escape:"htmlall"} screenshot"/>
	</div>
{/if}
{if $release.videostatus == 1 && $userdata.canpreview == 1}
	<div class="tab-pane" id="video">
		<video width="770" controls>
			<source src="{$smarty.const.WWW_TOP}/covers/video/{$release.guid}.ogv" type="video/ogg">
			Your browser does not support the video tag.
		</video>
	</div>
{/if}
{if $release.audiostatus == 1 && $userdata.canpreview == 1}
	<div class="tab-pane" id="audio">
		<audio controls>
			<source src="{$smarty.const.WWW_TOP}/covers/audio/{$release.guid}.ogg" type="audio/ogg">
			Your browser does not support the audio element.
		</audio>
	</div>
{/if}
{if $isadmin}
	<div class="tab-pane" id="admin">

		<table class="table table-bordered" id="detailstable" >
			<tr><th>Actions:</th>
				<td><div class="btn-group">
						<a class="btn btn-mini btn-inverse" href="{$smarty.const.WWW_TOP}/admin/release-edit.php?id={$release.id}&amp;from={$smarty.server.REQUEST_URI}" title="Edit Release"><i class="icon-edit icon-white"></i> Edit</a>
						<a class="btn btn-mini btn-inverse" href="{$smarty.const.WWW_TOP}/admin/release-delete.php?id={$release.id}&amp;from={$smarty.server.HTTP_REFERER}" title="Delete Release"><i class="icon-trash icon-white"></i> Delete</a>
					</div></td>
			</tr>
			<tr><th>Release Info:</th>
				<td>
					Regex Id (<a href="{$smarty.const.WWW_TOP}/admin/regex-list.php#{$release.regexid}">{$release.regexid}</a>) <br/>
					{if $release.reqid != ""}
						Request Id ({$release.reqid})
					{/if}
				</td>
			</tr>
		</table>
	</div>
{/if}
<div class="tab-pane" id="comments">
	<h2><i class="icon-comment"></i> Comments</h2>
	{if $comments|@count > 0}
		<table style="margin-bottom:20px;" class="table table-striped Sortable">
			<tr class="{cycle values=",alt"}">
				<th width="80">User</th>
				<th>Comment</th>
			</tr>
			{foreach from=$comments|@array_reverse:true item=comment}
				<tr>
					<td class="less" title="{$comment.createddate}">
						{if $comment.role == -1}<i class="icon-globe" title="Syndicated User"></i>
							{$comment.username}{if $isadmin} @<a href="{$smarty.const.WWW_TOP}/admin/spotnab-edit.php?id={$comment.sourceid}&amp;from={$smarty.server.REQUEST_URI}">{$comment.rolename}</a>{/if}
						{elseif $comment.role == 2}<i class="icon-font" title="{$comment.rolename}"></i>
							<strong><a title="View {$comment.username}'s profile" href="{$smarty.const.WWW_TOP}/profile?name={$comment.username}">{$comment.username}</a></strong>
						{elseif $comment.role == 4}<i class="icon-certificate" title="{$comment.rolename}"></i>
							<a title="View {$comment.username}'s profile" href="{$smarty.const.WWW_TOP}/profile?name={$comment.username}">{$comment.username}</a>
						{else}<i class="icon-user" title="{$comment.username}"></i>
							<a title="View {$comment.username}'s profile" href="{$smarty.const.WWW_TOP}/profile?name={$comment.username}">{$comment.username}</a>
						{/if}
						<br/>{$comment.createddate|daysago}
					</td>
					<td>{$comment.text|escape:"htmlall"|nl2br}</td>
				</tr>
			{/foreach}
		</table>
	{/if}
	<form action="" method="post">
		<label for="txtAddComment">Add Comment:</label><br/>
		<textarea id="txtAddComment" name="txtAddComment" rows="6" cols="60"></textarea>
		<br/>
		<input class="btn" type="submit" value="Submit"/>
	</form>
	</div>
</div

</div>
<script>
	// nzbcontents loader
	$(document).ready(function() {
		$('#nzbcontents').load('{$smarty.const.WWW_TOP}/filelist/{$release.guid}&modal', function() {
			$('.tabs').tab('show'); //reinitialize tabs
		});

		$('.tabs').bind('change', function(e) {
			var pattern=/#.+/gi //set a regex pattern (all the things after "#").
			var contentID = e.target.toString().match(pattern)[0]; //find pattern

			$(contentID).load('/'+contentID.replace('#',''), function(){
				$('.tabs').tabs(); //reinitialize tabs
			});
		});
	});
</script>

{if $nfo.id|@count > 0}
<script>
	// nfo loader
	$(document).ready(function() {
		$('#viewnfo').load('{$smarty.const.WWW_TOP}/nfo/{$release.guid}&modal', function() {
			$('.tabs').tab('show'); //reinitialize tabs
		});

		$('.tabs').bind('change', function(e) {
			var pattern=/#.+/gi //set a regex pattern (all the things after "#").
			var contentID = e.target.toString().match(pattern)[0]; //find pattern

			$(contentID).load('/'+contentID.replace('#',''), function(){
				$('.tabs').tabs(); //reinitialize tabs
			});
		});
	});
</script>
{/if}