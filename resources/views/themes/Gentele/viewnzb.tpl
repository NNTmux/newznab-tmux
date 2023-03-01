<div class="header">
	<h2>NZB > <strong>Details</strong></h2>
	<div class="breadcrumb-wrapper">
		<ol class="breadcrumb">
			<li><a href="{{url("{$site->home_link}")}}">Home</a></li>
			/ NZB
		</ol>
	</div>
</div>
<div class="row">
	<div class="col-lg-12 col-sm-12 col-12">
		<div class="card card-default">
			<div class="card-body">
				<h1>{$release.searchname|escape:"htmlall"} {if !empty($failed)}<span class="btn btn-light btn-xs"title="This release has failed to download for some users">
						<i class="fa fa-thumbs-o-up"></i>
						{$release.grabs} Grab{if $release.grabs != 1}s{/if} /
						<i class="fa fa-thumbs-o-down"></i>
						{$failed} Failed Download{if $failed != 1}s{/if}</span>{/if}</h1>
				{if $isadmin == true || $ismod == true}
					<a class="badge bg-warning"
					   href="{{url("/admin/release-edit?id={$release.guid}")}}"
					   title="Edit release">Edit</a>
                {/if}
                {if isset($isadmin)}
					<a class="badge bg-danger"
					   href="{{url("/admin/release-delete/{$release.guid}")}}"
					   title="Delete release">Delete</a>
				{/if}
				{if $movie && $release.videos_id <= 0 }
					{if $movie.imdbid > 0}
						<a class="badge bg-info" target="_blank"
						   href="{$site->dereferrer_link}http://www.imdb.com/title/tt{$movie.imdbid}/"
						   title="View at IMDB">IMDB</a>
						<a target="_blank"
						   href="{$site->dereferrer_link}http://trakt.tv/search/imdb/tt{$movie.imdbid}/"
						   name="trakt{$release.imdbid}" title="View Trakt page"
						   class="badge bg-info" rel="trakt">TRAKT</a>
					{/if}
					{if $movie.tmdbid > 0}
						<a class="badge bg-info" target="_blank"
						   href="{$site->dereferrer_link}http://www.themoviedb.org/movie/{$movie.tmdbid}"
						   title="View at TMDb">TMDb</a>
					{/if}
					{if $movie.imdbid > 0}
						<a class="badge bg-info" href="{{url("/Movies?imdb={$movie.imdbid}")}}"
						   title="View all versions">Movie View</a>
					{/if}
				{/if}
				{if $anidb && $release.anidbid > 0}
					<a class="badge bg-info" href="{{url("/anime?id={$release.anidbid}")}}"
					   title="View all releases from this anime">View all episodes</a>
					<a class="badge bg-info"
					   href="{$site->dereferrer_link}http://anidb.net/perl-bin/animedb.pl?show=anime&aid={$anidb.anidbid}"
					   title="View at AniDB" target="_blank">AniDB</a>
					<a class="badge bg-info"
					   href="{{url("/rss/full-feed?anidb={$release.anidbid}&amp;dl=1&amp;i={$userdata.id}&amp;api_token={$userdata.api_token}")}}">Anime
						RSS Feed</a>
				{/if}
				{if $show && $release.videos_id > 0}
					<a href="{{url("/myshows?action=add&id={$release.videos_id}&from={$smarty.server.REQUEST_URI|escape:"url"}")}}"
					   class="badge bg-success">Add to My Shows</a>
					<a class="badge bg-info" href="{{url("/series/{$release.videos_id}")}}"
					   title="View all releases for this series">View all episodes</a>
					{if $show.tvdb > 0}
						<a class="badge bg-info" target="_blank"
						   href="{$site->dereferrer_link}http://thetvdb.com/?tab=series&id={$show.tvdb}")}}"
						   title="View at TheTVDB">TheTVDB</a>
					{/if}
					{if $show.tvmaze > 0}
						<a class="badge bg-info" target="_blank"
						   href="{$site->dereferrer_link}http://tvmaze.com/shows/{$show.tvmaze}"
						   title="View at TVMaze">TVMaze</a>
					{/if}
					{if $show.trakt > 0}
						<a class="badge bg-info" target="_blank"
						   href="{$site->dereferrer_link}http://www.trakt.tv/shows/{$show.trakt}"
						   title="View at TraktTv">Trakt</a>
					{/if}
					{if $show.tvrage > 0}
						<a class="badge bg-info" target="_blank"
						   href="{$site->dereferrer_link}http://www.tvrage.com/shows/id-{$show.tvrage}"
						   title="View at TV Rage">TV Rage</a>
					{/if}
					{if $show.tmdb > 0}
						<a class="badge bg-info" target="_blank"
						   href="{$site->dereferrer_link}https://www.themoviedb.org/tv/{$show.tmdb}"
						   title="View at TheMovieDB">TMDb</a>
					{/if}
				{/if}
				{if $con && $con.url != ""}<a href="{$site->dereferrer_link}{$con.url}/"
											  class="badge bg-info" target="_blank">Amazon</a>{/if}
				{if $book && $book.url != ""}<a href="{$site->dereferrer_link}{$book.url}/"
												class="badge bg-info" target="_blank">Amazon</a>{/if}
				{if $music && $music.url != ""}<a href="{$site->dereferrer_link}{$music.url}/"
												  class="badge bg-info" target="_blank">
						Amazon</a>{/if}
				{if $xxx}
					{if $xxx.classused === "ade"}<a class="badge bg-info" target="_blank"
													href="{$site->dereferrer_link}{$xxx.directurl}"
													title="View at Adult DVD Empire">ADE</a>
					{elseif $xxx.classused === "adm"}<a class="badge bg-info" target="_blank"
														href="{$site->dereferrer_link}{$xxx.directurl}"
														title="View at Adult DVD Marketplace">ADM</a>
					{elseif $xxx.classused === "pop"}<a class="badge bg-info" target="_blank"
														href="{$site->dereferrer_link}{$xxx.directurl}"
														title="View at Popporn">PopPorn</a>
					{elseif $xxx.classused === "aebn"}<a class="badge bg-info" target="_blank"
														 href="{$site->dereferrer_link}{$xxx.directurl}"
														 title="View at Adult Entertainment Broadcast Network">
							AEBN</a>
					{elseif $xxx.classused === "hm"}<a class="badge bg-info" target="_blank"
													   href="{$site->dereferrer_link}{$xxx.directurl}"
													   title="View at Hot Movies">HotMovies</a>
					{/if}
				{/if}
				<p>
					{if $movie && $release.videos_id <= 0 && $movie.plot != ''}<span
							class="descinitial">{$movie.plot|escape:"htmlall"|truncate:500:"...":true}</span>
						{if $movie.plot|strlen > 500}
							<a class="descmore" href="#">more...</a>
							<span class="descfull">{$movie.plot|escape:"htmlall"|nl2br|magicurl}</span>{/if}{/if}
					{if $show && $release.videos_id > 0 && $show.summary != ""}<span
							class="descinitial">{$show.summary|escape:"htmlall"|nl2br|magicurl|truncate:500:"...":true}</span>
						{if $show.summary|strlen > 500}
							<a class="descmore" href="#">more...</a>
							<span class="descfull">{$show.summary|escape:"htmlall"|nl2br|magicurl}</span>{/if}{/if}
					{if $xxx}
						{if $xxx.tagline != ''}<br/>{$xxx.tagline|stripslashes|escape:"htmlall"}{/if}
						{if $xxx.plot != ''}{if $xxx.tagline != ''} - {else}
							<br/>
						{/if}{$xxx.plot|stripslashes|escape:"htmlall"}{/if}
					{/if}
					{if $anidb && $release.anidbid > 0 && $anidb.description != ""}{$anidb.description|escape:"htmlall"|nl2br|magicurl|truncate:500:"...":true}{/if}
					{if $music && $music.review != ""}{$music.review|escape:"htmlall"|nl2br|magicurl|truncate:500:"...":true}{/if}
					{if $book && $book.review != ""}{$book.review|escape:"htmlall"|nl2br|magicurl|truncate:500:"...":true}{/if}
					{if $con &&$con.review != ""}{$con.review|escape:"htmlall"|nl2br|magicurl|truncate:500:"...":true}{/if}
				</p>
				<div class="col-md-12">
					<div class="tabbable">
						<div class role="tabpanel">
							<ul class="nav nav-tabs bar-tabs" role="tablist">
								<li role="presentation" class="active"><a href="#pane1"
																		  data-bs-toggle="tab">Info</a></li>
								{if $movie && $release.videos_id <= 0}{if $movie.trailer != ""}
									<li><a href="#pane2" data-bs-toggle="tab">Trailer</a></li>
								{/if}{/if}
								{if isset($xxx.trailers) && $xxx.trailers != ''}
									<li role="presentation"><a href="#pane2" data-bs-toggle="tab">Trailer</a></li>
								{/if}
								{if !empty($nfo.nfo)}
									<li role="presentation"><a href="#pane3" data-bs-toggle="tab">NFO</a></li>
								{/if}
								{if isset($similars) && $similars|@count > 1}
									<li role="presentation"><a href="#pane4" data-bs-toggle="tab">Similar</a></li>
								{/if}
								{if $release.jpgstatus == 1 && $userdata->can('preview') == true}
									<li role="presentation"><a href="#pane6" data-bs-toggle="tab">Sample</a></li>
								{/if}
								<li role="presentation"><a href="#comments" data-bs-toggle="tab">Comments</a></li>
								{if ($release.haspreview == 1 && $userdata->can('preview') == true) || ($release.haspreview == 2 && $userdata->can('preview') == true)}
									<li role="presentation"><a href="#pane7" data-bs-toggle="tab">Preview</a></li>
								{/if}
								{if $reVideo != false || $reAudio != false}
									<li role="presentation"><a href="#pane8" data-bs-toggle="tab">MediaInfo</a></li>
								{/if}
								{if isset($xxx.backdrop) && $xxx.backdrop == 1}
									<li role="presentation"><a href="#pane9" data-bs-toggle="tab">Back Cover</a></li>
								{/if}
								{if isset($game.backdrop) && $game.backdrop == 1}
									<li role="presentation"><a href="#pane10" data-bs-toggle="tab">Screenshot</a></li>
								{/if}
							</ul>
						</div>
						<div class="tab-content">
							<div id="pane1" class="tab-pane active">
								<div class="row small-gutter-left">
									<div class="col-md-3 small-gutter-left">
										{if $movie && $release.videos_id <= 0 && $movie.cover == 1}
											<img src="{{url("/covers/movies/{$movie.imdbid}-cover.jpg")}}"
												 width="185"
												 alt="{$movie.title|escape:"htmlall"}"
												 data-bs-toggle="modal"
												 data-target="#modal-image"/>
										{/if}
										{if $show && $release.videos_id > 0 && $show.image != "0"}
											<img src="{{url("/covers/tvshows/{$release.videos_id}.jpg")}}"
												 width="185"
												 alt="{$show.title|escape:"htmlall"}"
												 data-bs-toggle="modal"
												 data-target="#modal-image"/>
										{/if}
										{if $anidb && $release.anidbid > 0 && $anidb.picture != ""}
											<img src="{{url("/covers/anime/{$anidb.anidbid}.jpg")}}"
												 width="185"
												 alt="{$anidb.title|escape:"htmlall"}"
												 data-bs-toggle="modal"
												 data-target="#modal-image"/>
										{/if}
										{if $con && $con.cover == 1}
											<img src="{{url("/covers/console/{$con.id}.jpg")}}"
												 width="185"
												 alt="{$con.title|escape:"htmlall"}"
												 data-bs-toggle="modal"
												 data-target="#modal-image"/>
										{/if}
										{if $game && $game.cover == 1}
											<img src="{{url("/covers/games/{$game.id}.jpg")}}"
												 width="185"
												 alt="{$con.title|escape:"htmlall"}"
												 data-bs-toggle="modal"
												 data-target="#modal-image"/>
										{/if}
										{if $music && $music.cover == 1}
											<img src="{{url("/covers/music/{$music.id}.jpg")}}"
												 width="185"
												 alt="{$music.title|escape:"htmlall"}"
												 data-bs-toggle="modal"
												 data-target="#modal-image"/>
										{/if}
										{if $book && $book.cover == 1}
											<img src="{{url("/covers/book/{$book.id}.jpg")}}"
												 width="185"
												 alt="{$book.title|escape:"htmlall"}"
												 data-bs-toggle="modal"
												 data-target="#modal-image"/>
										{/if}
										{if $xxx && $xxx.cover == 1}
											<a href="{{url("/covers/xxx/{$xxx.id}-cover.jpg")}}"
											   class="modal-image"><img
														class="modal-image"
														src="{{url("/covers/xxx/{$xxx.id}-cover.jpg")}}"
														width="185"
														alt="{$xxx.title|escape:"htmlall"}"
														data-bs-toggle="modal"
														data-target="#modal-image"/></a>
										{/if}
										<br/><br/>
										<div class="btn-group btn-group-vertical">
											<a class="btn btn-light btn-sm btn-success btn-transparent"
											   href="{{url("/getnzb?id={$release.guid}")}}"><i
														class="fa fa-cloud-download"></i> Download</a>
                                            <a class="btn btn-success btn-sm btn-info btn-transparent"
                                                href="{{url("/cart/add?id={$release.guid}")}}" target="_blank">
                                                        <i class="fa fa-shopping-basket guid"></i> Add to Cart</a>
											{if isset($sabintegrated) && $sabintegrated !=""}
												<button type="button"
														class="btn btn-success btn-sm btn-transparent sabsend">
													<i class="icon_sab fa fa-arrow-right"
													   id="guid{$release.guid}"></i> Send to Queue
												</button>
											{/if}
											{if !empty($movie.imdbid)}
												{if !empty($cpurl) && !empty($cpapi)}
													<button
															type="button"
															id="imdb{$movie.imdbid}"
															href="javascript:;"
															class="btn btn-success btn-sm btn-info btn-transparent sendtocouch">
														<i class="fa fa-bed"></i>
														Send to CouchPotato
													</button>
												{/if}
											{/if}
											{if $weHasVortex}
												<button type="button"
														class="btn btn-success btn-sm btn-transparent vortexsend">
													<i class="icon_sab fa fa-arrow-right"
													   id="guid{$release.guid}"></i> Send to
													NZBVortex
												</button>
											{/if}
										</div>
									</div>
									<div class="col-md-9 small-gutter-left">
										<table cellpadding="0" cellspacing="0"
											   width="100%">
											<tbody>
											<tr valign="top">
												<td>
													<table class="data table table-striped responsive-utilities jambo-table">
														{if $movie && $release.videos_id <= 0 && $movie.imdbid > 0}
															<tr>
																<th width="140">Name
																</th>
																<td>{$movie.title|escape:"htmlall"}</td>
															</tr>
														{/if}
														{if $show && $release.videos_id > 0}
															<tr>
																<th width="140">Name
																</th>
																<td>{$release.title|escape:"htmlall"}</td>
															</tr>
														{/if}
														{if $xxx}
															<tr>
																<th width="140">Name
																</th>
																<td>{$xxx.title|stripslashes|escape:"htmlall"}</td>
															</tr>
															<tr>
																<th width="140">
																	Starring
																</th>
																<td>{$xxx.actors}</td>
															</tr>
															{if isset($xxx.director) && $xxx.director != ""}
																<tr>
																	<th width="140">
																		Director
																	</th>
																	<td>{$xxx.director}</td>
																</tr>
															{/if}
															{if isset($xxx.genres) && $xxx.genres != ""}
																<tr>
																	<th width="140">
																		Genre
																	</th>
																	<td>{$xxx.genres}</td>
																</tr>
															{/if}
														{/if}
														{if $movie && $release.videos_id <= 0 && $movie.imdbid > 0 }
															<tr>
																<th width="140">
																	Starring
																</th>
																<td>{$movie.actors}</td>
															</tr>
															<tr>
																<th width="140">
																	Director
																</th>
																<td>{$movie.director}</td>
															</tr>
															<tr>
																<th width="140">Genre
																</th>
																<td>{$movie.genre}</td>
															</tr>
															<tr>
																<th width="140">Year &
																	Rating
																</th>
																<td>{$movie.year}
																	- {if $movie.rating == ''}N/A{/if}{$movie.rating}
																	/10
																</td>
															</tr>
															{if !empty($movie.rtrating)}
																<tr>
																	<th width="140">RottenTomatoes score</th>
																	<td>{$movie.rtrating}</td>
																</tr>
															{/if}
														{/if}
														{if $show && $release.videos_id > 0}
															{if $release.firstaired != ""}
																<tr>
																	<th width="140">
																		Aired
																	</th>
																	<td>{$release.firstaired|date_format}</td>
																</tr>
															{/if}
															{if $show.publisher != ""}
																<tr>
																	<th width="140">
																		Network
																	</th>
																	<td>{$show.publisher}</td>
																</tr>
															{/if}
															{if $show.countries_id != ""}
																<tr>
																	<th width="140">
																		Country
																	</th>
																	<td>{$show.countries_id}</td>
																</tr>
															{/if}
														{/if}
														{if $music}
															<tr>
																<th width="140">Name
																</th>
																<td>{$music.title|escape:"htmlall"}</td>
															</tr>
															<tr>
																<th width="140">Genre
																</th>
																<td>{$music.genres|escape:"htmlall"}</td>
															</tr>
															{if $music.releasedate != ""}
																<tr>
																	<th width="140">
																		Release Date
																	</th>
																	<td>{$music.releasedate|date_format}</td>
																</tr>
															{/if}
															{if $music.publisher != ""}
																<tr>
																	<th width="140">
																		Publisher
																	</th>
																	<td>{$music.publisher|escape:"htmlall"}</td>
																</tr>
															{/if}
														{/if}
														{if $book}
															<tr>
																<th width="140">Name
																</th>
																<td>{$book.title|escape:"htmlall"}</td>
															</tr>
															<tr>
																<th width="140">Author
																</th>
																<td>{$book.author|escape:"htmlall"}</td>
															</tr>
															{if $book.ean != ""}
																<tr>
																	<th width="140">
																		EAN
																	</th>
																	<td>{$book.ean|escape:"htmlall"}</td>
																</tr>
															{/if}
															{if $book.isbn != ""}
																<tr>
																	<th width="140">
																		ISBN
																	</th>
																	<td>{$book.isbn|escape:"htmlall"}</td>
																</tr>
															{/if}
															{if $book.pages != ""}
																<tr>
																	<th width="140">
																		Pages
																	</th>
																	<td>{$book.pages|escape:"htmlall"}</td>
																</tr>
															{/if}
															{if $book.dewey != ""}
																<tr>
																	<th width="140">
																		Dewey
																	</th>
																	<td>{$book.dewey|escape:"htmlall"}</td>
																</tr>
															{/if}
															{if $book.publisher != ""}
																<tr>
																	<th width="140">
																		Publisher
																	</th>
																	<td>{$book.publisher|escape:"htmlall"}</td>
																</tr>
															{/if}
															{if $book.publishdate != ""}
																<tr>
																	<th width="140">
																		Released
																	</th>
																	<td>{$book.publishdate|date_format}</td>
																</tr>
															{/if}
														{/if}
														<tr>
															<th width="140">Group(s)</th>
															{if !empty($release.group_names)}
																{assign var="groupname" value=","|explode:$release.group_names}
																<td>
																	{foreach $groupname as $grp}
																		<a title="Browse {$grp}"
																		   href="{{url("/browse/group?g={$grp}")}}">{$grp|replace:"alt.binaries":"a.b"}</a>
																		<br/>
																	{/foreach}
																</td>
															{else}
																<td>
																	<a title="Browse {$release.group_name}"
																	   href="{{url("/browse/group?g={$release.group_name}")}}">{$release.group_name|replace:"alt.binaries":"a.b"}</a>
																</td>
															{/if}
														</tr>
														<tr>
															<th width="140">Size /
																Completion
															</th>
															<td>{$release.size|filesize}{if $release.completion > 0}&nbsp;({if $release.completion < 100}
																	<span class="warning">{$release.completion}
																	%</span>{else}{$release.completion}%{/if}){/if}
															</td>
														</tr>
														<tr>
															<th width="140">Grabs</th>
															<td>{$release.grabs}
																time{if $release.grabs == 1}{else}s{/if}</td>
														</tr>
														{if !empty($failed)}
															<tr>
																<th width="140">Failed Download</th>
																<td>{$failed}
																	time{if $failed == 1}{else}s{/if}</td>
															</tr>
														{/if}
														<tr>
															<th width="140">Password
															</th>
															<td>{if $release.passwordstatus == 0}None{elseif $release.passwordstatus == 1}Passworded Rar Archive{else}Unknown{/if}</td>
														</tr>
														<tr>
															<th width="140">Category</th>
															<td>
																<a title="Browse {$release.category_name}"
																   href="{{url("/browse/{$release.parent_category}/{$release.sub_category}")}}"> {$release.category_name}</a>
															</td>
														</tr>
														<tr>
															<th width="140">Files</th>
															<td>
																<a title="View file list"
																   href="{{url("/filelist/{$release.guid}")}}">{$release.totalpart}
																	file{if $release.totalpart == 1}{else}s{/if}</a>
															</td>
														</tr>
														<tr>
															<th width="140">RAR Contains
															</th>
															<td>
																<strong>Files:</strong><br/>
																{foreach $releasefiles as $rf}
																	<code>{$rf.name}</code>
																	<br/>
																	{if $rf.passworded != 1}
																		<i class="fa fa-unlock"></i>
																		<span class="badge bg-success">No Password</span>
																	{else}
																		<i class="fa fa-lock"></i>
																		<span class="badge bg-danger">Passworded</span>
																	{/if}
																	<span class="badge bg-info">{$rf.size|filesize}</span>
																	<span class="badge bg-info">{$rf.created_at|date_format}</span>
																	<br/>
																{/foreach}
															</td>
														</tr>
														<tr>
															<th width="140">Poster</th>
															<td><a title="Find releases by this poster"
																   href="{{url("/search?searchadvr=&searchadvsubject=&searchadvposter={$release.fromname|escape:"htmlall"}&searchadvfilename=&searchadvdaysnew=&searchadvdaysold=&searchadvgroups=-1&searchadvcat=-1&searchadvsizefrom=-1&searchadvsizeto=-1&searchadvhasnfo=0&searchadvhascomments=0&search_type=adv")}}">{$release.fromname|escape:"htmlall"}</a>
															</td>
														</tr>
														<tr>
															<th width="140">Posted</th>
															<td>{{Timezone::convertToLocal(Illuminate\Support\Carbon::parse($release.postdate, config('app.timezone')), 'F j, Y H:i:s')}}
															</td>
														</tr>
														<tr>
															<th width="140">Added</th>
															<td>{{Timezone::convertToLocal(Illuminate\Support\Carbon::parse($release.adddate, config('app.timezone')), 'F j, Y H:i:s')}}
															</td>
														</tr>
														{if isset($isadmin)}
															<tr>
																<th width="140">Release
																	Info
																</th>
																<td>
																	{if !empty($regex->collection_regex_id)} Collection regex ID: {$regex->collection_regex_id}<br>{/if}
																	{if !empty($regex->naming_regex_id)} Naming regex ID: {$regex->naming_regex_id}<br>{/if}
                                                                    {if !empty($downloadedby) && count($downloadedby)>0} Release downloaded by following users:
                                                                        {foreach $downloadedby as $user}
                                                                            <br>
                                                                            <a href="{{url("/admin/user-edit?id={$user->user->id}")}}">{$user->user->username}</a>
                                                                        {/foreach}
                                                                    {/if}
																</td>
															</tr>
														{/if}
														</tbody>
													</table>
												</td>
											</tr>
											</tbody>
										</table>
									</div>
								</div>
							</div>
							<div id="pane2" class="tab-pane">
								{if $xxx}
									{if $xxx.trailers != ''}
										{$xxx.trailers}
									{/if}
								{/if}
								{if $movie && $release.videos_id <= 0}
									{if $movie.trailer != ''}
										{$movie.trailer}
									{/if}
								{/if}
							</div>
							<div id="pane3" class="tab-pane">
								<pre id="nfo">{$nfo.nfo}</pre>
							</div>
							<div id="pane4" class="tab-pane">
								{if isset($similars)}
									Similar:
									<ul>
										{foreach $similars as $similar}
											<li>
												<a title="View similar NZB details"
												   href="{{url("/details/{$similar.guid}")}}">{$similar.searchname|escape:"htmlall"}</a>
												<br/>
											</li>
										{/foreach}
									</ul>
									<br/>
									<a title="Search for similar Nzbs"
									   href="{{url("/search?id={$searchname|escape:"htmlall"}")}}">Search for
										similar NZBs...</a>
									<br/>
									</td>
									</tr>
								{/if}
							</div>
							<div id="comments" class="tab-pane">
								{if $comments|@count > 0}
									<table class="data table table-striped responsive-utilities jambo-table">
										<tr class="{cycle values=",alt"}">
											<th width="80">User</th>
											<th>Comment</th>
										</tr>
										{foreach $comments|@array_reverse:true as $comment}
										<tr>
											<td class="less" title="{$comment.created_at}">
												{if !$privateprofiles || isset($isadmin) || isset($ismod)}
													<a title="View {$comment.username}'s profile"
													   href="{{url("/profile?name={$comment.username}")}}">{$comment.username}</a>
												{else}
													{$comment.username}
												{/if}
												<br/>{$comment.created_at|daysago}
											</td>
											{if isset($comment.shared) && $comment.shared == 2}
												<td style="color:#6B2447">{$comment.text|escape:"htmlall"|nl2br}</td>
											{else}
												<td>{$comment.text|escape:"htmlall"|nl2br}</td>
											{/if}
											{/foreach}
									</table>
								{else}
									<div class="alert alert-info" role="alert">
										No comments yet...
									</div>
								{/if}
                                {{Form::open(['url' => "details/{$release.guid}"])}}
									<label for="txtAddComment">Add Comment:</label><br/>
									<textarea id="txtAddComment" name="txtAddComment" rows="6" cols="60"></textarea>
									<br/>
                                    {{Form::submit('Submit', ['class' => 'btn btn-success'])}}
								{{Form::close()}}
							</div>
							{if $release.jpgstatus == 1 && $userdata->can('preview') == true}
								<div id="pane6" class="tab-pane">
									<img src="{{url("/covers/sample/{$release.guid}_thumb.jpg")}}"
										 alt="{$release.searchname|escape:"htmlall"}"
										 data-bs-toggle="modal"
										 data-target="#modal-image"/>
								</div>
							{/if}
							{if ($release.haspreview == 1 && $userdata->can('preview') == true) || ($release.haspreview == 2 && $userdata->can('preview') == true)}
								<div id="pane7" class="tab-pane">
									<img src="{{url("/covers/preview/{$release.guid}_thumb.jpg")}}"
										 alt="{$release.searchname|escape:"htmlall"}"
										 data-bs-toggle="modal"
										 data-target="#modal-image"/>
								</div>
							{/if}
							{if $reVideo != false || $reAudio != false}
								<div id="pane8" class="tab-pane">
									<table style="width:100%;"
										   class="data table table-striped responsive-utilities jambo-table">
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
												<td class="right">{$reVideo.videowidth}
													x{$reVideo.videoheight}</td>
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
										{foreach $reAudio as $audio}
											<tr>
												<td><strong>Audio {$audio.audioid}</strong></td>
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
							{if isset($xxx.backdrop) && $xxx.backdrop == 1}
								<div id="pane9" class="tab-pane">
									<img src="{{url("/covers/xxx/{$xxx.id}-backdrop.jpg")}}"
										 alt="{$xxx.title|escape:"htmlall"}"
										 data-bs-toggle="modal"
										 data-target="#modal-image"/>
								</div>
							{/if}
							{if isset($game.backdrop) && $game.backdrop == 1}
								<div id="pane10" class="tab-pane">
									<img class="img-responsive"
										 src="{{url("/covers/games/{$game.id}-backdrop.jpg")}}"
										 width="500" border="0"
										 alt="{$game.title|escape:"htmlall"}"
										 data-bs-toggle="modal"
										 data-target="#modal-image"/>
								</div>
							{/if}
						</div>
					</div>
					<!-- /.tab-content -->
				</div>
				<!-- /.tabbable -->
			</div>
		</div>
	</div>
</div>
<div class="modal fade modal-image" id="modal-image" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-hidden="true"><i
							class="icons-office-52"></i></button>
			</div>
			<div class="modal-body">
				{if $movie && $release.videos_id <= 0 && $movie.cover == 1}
					<img src="{{url("/covers/movies/{$movie.imdbid}-cover.jpg")}}"
						 alt="{$movie.title|escape:"htmlall"}">
				{/if}
				{if $show && $release.videos_id > 0 && $show.image != "0"}
					<img src="{{url("/covers/tvshows/{$release.videos_id}.jpg")}}"
						 alt="{$show.title|escape:"htmlall"}"/>
				{/if}
				{if $anidb && $release.anidbid > 0 && $anidb.picture != ""}
					<img src="{{url("/covers/anime/{$anidb.anidbid}.jpg")}}"
						 alt="{$anidb.title|escape:"htmlall"}"/>
				{/if}
				{if $con && $con.cover == 1}
					<img src="{{url("/covers/console/{$con.id}.jpg")}}"
						 alt="{$con.title|escape:"htmlall"}"/>
				{/if}
				{if $music && $music.cover == 1}
					<img src="{{url("/covers/music/{$music.id}.jpg")}}"
						 alt="{$music.title|escape:"htmlall"}"/>
				{/if}
				{if $book && $book.cover == 1}
					<img src="{{url("/covers/book/{$book.id}.jpg")}}"
						 alt="{$book.title|escape:"htmlall"}"/>
				{/if}
				{if $xxx && $xxx.backdrop == 1}
					<a href="{{url("/covers/xxx/{$xxx.id}-backdrop.jpg")}}"
					   class="modal-image_back"><img class="modal-image_back"
													 src="{{url("/covers/xxx/{$xxx.id}-backdrop.jpg")}}"
													 alt="{$xxx.title|escape:"htmlall"}"/></a>
				{elseif $xxx && $xxx.cover == 1}
					<a href="{{url("/covers/xxx/{$xxx.id}-cover.jpg")}}"
					   class="modal-image"><img class="modal-image"
												src="{{url("/covers/xxx/{$xxx.id}-cover.jpg")}}"
												alt="{$xxx.title|escape:"htmlall"}"/></a>
				{/if}
			</div>
		</div>
	</div>
</div>
