<div class="well well-sm">
	{if {$site->adbrowse} != ''}
		{$site->adbrowse}
	{/if}
	<div class="header">
		<h2>{$site->title} > <strong>Search</strong></h2>
		<div class="breadcrumb-wrapper">
			<ol class="breadcrumb">
				<li><a href="{{url("{$site->home_link}")}}">Home</a></li>
				/ Search
			</ol>
		</div>
	</div>
	<div>
		<div style="text-align: center;">
			<a href="#" onclick="if (jQuery(this).text() == 'Advanced Search')
					jQuery(this).text('Basic Search');
				else
					jQuery(this).text('Advanced Search');
				jQuery('#sbasic,#sadvanced').toggle();
				return false;">{if $sadvanced}Basic{else}Click For Advanced{/if} Search
			</a>
		</div>
	</div>
	<br>
	<div class="well well-sm">
		<div style="text-align: center">
			<div>
				<br>
				{{Form::open(['url' => 'search', 'method' => 'get'])}}
					<div id="sbasic" class="row" style="text-align:center;{if $sadvanced} display:none;{/if}">
						<div class="col-md-6">
							<input id="search" class="form-inline" maxlength="500" name="search"
								   value="{$search|escape:'htmlall'}"
								   type="search" placeholder="What are you looking for?"/>
						</div>
						<div class="col-md-6">
							<input type="hidden" name="t" value="{if $category[0]!=""}{$category[0]}{else}-1{/if}"
								   id="search_cat"/>
							<input type="hidden" name="search_type" value="basic" id="search_type"/>
                            {{Form::submit('Search', ['class' => 'btn btn-success', 'id' => 'search_search_button'])}}
						</div>
					</div>
				{{Form::close()}}
			</div>
			{{Form::open(['url' => 'search', 'method' => 'get'])}}
				<div id="sadvanced" {if not $sadvanced}style="display:none"{/if}>
					<div style="text-align: center;">
						<table class="data table table-striped responsive-utilities jambo-table">
							<tr>
								<th><label for="searchadvr">Release Name:</label></th>
								<td><input class="searchadv" id="searchadvr" name="searchadvr"
										   value="{$searchadvr|escape:'htmlall'}"
										   type="text"></td>
							</tr>
							<tr>
								<th><label for="searchadvsubject">Usenet Name:</label></th>
								<td><input class="searchadv" id="searchadvsubject" name="searchadvsubject"
										   value="{$searchadvsubject|escape:'htmlall'}" type="text"></td>
							</tr>
							<tr>
								<th><label for="searchadvposter">Poster:</label></th>
								<td><input class="searchadv" id="searchadvposter" name="searchadvposter"
										   value="{$searchadvposter|escape:'htmlall'}" type="text"></td>
							</tr>
							<tr>
								<th><label for="searchadvfilename">Filename:</label></th>
								<td><input class="searchadv" id="searchadvfilename" name="searchadvfilename"
										   value="{$searchadvfilename|escape:'htmlall'}" type="text"/></td>
							</tr>
							<tr>
							<tr>
								<th><label for="searchadvdaysnew">Min age(days):</label></th>
								<td>
									<input class="searchdaysinput" id="searchadvdaysnew" name="searchadvdaysnew"
										   value="{$searchadvdaysnew|escape:'htmlall'}" type="text">
								</td>
							</tr>
							<tr>
								<th><label for="searchadvdaysold">Max age(days):</label></th>
								<td>
									<input class="searchdaysinput" id="searchadvdaysold" name="searchadvdaysold"
										   value="{$searchadvdaysold|escape:'htmlall'}" type="text">
								</td>
							</tr>
							<tr>
								<th><label for="searchadvgroups">Group:</label></th>
								<td>{html_options class="searchadvbtns" id="searchadvgroups" name="searchadvgroups" options=$grouplist selected=$selectedgroup}</td>
							</tr>
							<tr>
								<th><label for="searchadvcat">Category:</label></th>
								<td>{html_options class="searchadvbtns" id="searchadvcat" name="searchadvcat" options=$catlist selected=$selectedcat}</td>
							</tr>
							<tr>
								<th><label for="searchadvsizefrom">Min/Max Size:</label></th>
								<td>
									{html_options id="searchadvsizefrom" name="searchadvsizefrom" options=$sizelist selected=$selectedsizefrom}
									{html_options id="searchadvsizeto" name="searchadvsizeto" options=$sizelist selected=$selectedsizeto}
								</td>
							</tr>
							<tr>
								<td>
									<div style="float:right;"><input type="hidden" name="search_type" value="adv" id="search_type">
                                        {{Form::submit('Search', ['class' => 'btn btn-success', 'id' => 'search_adv_button'])}}
									</div>
								</td>
							</tr>
						</table>
					</div>
				</div>
			{{Form::close()}}
			{if $results|@count == 0 && ($search || $subject|| $searchadvr|| $searchadvsubject || $selectedgroup || $selectedsizefrom || $searchadvdaysold) != ""}
				<div style="text-align: center;">
					<div class="nosearchresults">
						Your search did not match any releases.
						<br><br>
						Suggestions:
						<br><br>
						<ul>
							<div style="text-align: center;">
								<li>Make sure all words are spelled correctly.</li>
							</div>
							<div style="text-align: center;">
								<li>Try different keywords.</li>
							</div>
							<div style="text-align: center;">
								<li>Try more general keywords.</li>
							</div>
							<div style="text-align: center;">
								<li>Try fewer keywords.</li>
							</div>
						</ul>
					</div>
				</div>
			{elseif ($search || $subject || $searchadvr || $searchadvsubject || $selectedgroup || $selectedsizefrom || $searchadvdaysold) == ""}
			{else}
				<div class="well well-sm">
					{{Form::open(['id' => 'nzb_multi_operations_form','style' => 'padding-top:10px;', 'method' => 'get', 'url' => 'search'])}}
						<div class="row">
							<div class="col-md-4">
								{if isset($shows)}
									<p>
										<a href="{{route('series')}}"
										   title="View available TV series">Series List</a> |
										<a title="Manage your shows"
										   href="{{route("myshows")}}">Manage My Shows</a> |
										<a title="All releases in your shows as an RSS feed"
										   href="{{url("/rss/myshows?dl=1&amp;i={$userdata.id}&amp;api_token={$userdata.api_token}")}}">Rss
											Feed</a>
									</p>
								{/if}
								<div class="nzb_multi_operations">
									{if isset($section) && $section != ''}View:
										<a href="{{url("/{$section}?t={$category}")}}">Covers</a>
										|
										<b>List</b>
										<br/>
									{/if}
									With Selected:
									<div class="btn-group">
										<button type="button"
												class="nzb_multi_operations_download btn btn-sm btn-success"
												data-bs-toggle="tooltip" data-bs-placement="top" title
												data-original-title="Download NZBs">
											<i class="fa fa-cloud-download"></i></button>
										<button type="button"
												class="nzb_multi_operations_cart btn btn-sm btn-info"
												data-bs-toggle="tooltip" data-bs-placement="top" title
												data-original-title="Send to my Download Basket">
											<i class="fa fa-shopping-basket"></i></button>
										{if isset($isadmin)}
											<input type="button"
												   class="nzb_multi_operations_edit btn btn-sm btn-warning"
												   value="Edit"/>
											<input type="button"
												   class="nzb_multi_operations_delete btn btn-sm btn-danger"
												   value="Delete"/>
										{/if}
									</div>
								</div>
							</div>
							{if count($results) > 0}
								<div class="col-md-8">
									{$results->onEachSide(5)->links()}
								</div>
							{/if}
						</div>
						<hr>
						<table class="data table table-striped responsive-utilities jambo-table" id="browsetable">
							<thead class="thead-light">
							<tr>
								<th><input id="check-all" type="checkbox" class="flat-all"/></th>
                                <th>Name<br>
                                    <a title="Sort Descending" href="{$orderbyname_desc}"><i class="fas fa-chevron-down"></i></a>
                                    <a title="Sort Ascending" href="{$orderbyname_asc}"><i class="fas fa-chevron-up"> </i></a>
                                </th>
                                <th class="column-title" style="display: table-cell;">Category<br>
                                    <a title="Sort Descending" href="{$orderbycat_desc}"><i class="fas fa-chevron-down"></i></a>
                                    <a title="Sort Ascending" href="{$orderbycat_asc}"><i class="fas fa-chevron-up"></i></a>
                                </th>
                                <th class="column-title" style="display: table-cell;">Posted<br>
                                    <a title="Sort Descending" href="{$orderbyposted_desc}"><i class="fas fa-chevron-down"></i></a>
                                    <a title="Sort Ascending" href="{$orderbyposted_asc}"><i class="fas fa-chevron-up"></i></a>
                                </th>
                                <th style="vertical-align:top;text-align:center;">Size<br>
                                    <a title="Sort Descending" href="{$orderbysize_desc}"><i class="fas fa-chevron-down"></i></a>
                                    <a title="Sort Ascending" href="{$orderbysize_asc}"><i class="fas fa-chevron-up"></i></a>
                                </th>
                                <th style="vertical-align:top;text-align:center;">Files<br>
                                    <a title="Sort Descending" href="{$orderbyfiles_desc}"><i class="fas fa-chevron-down"></i></a>
                                    <a title="Sort Ascending" href="{$orderbyfiles_asc}"><i class="fas fa-chevron-up"></i></a>
                                </th>
                                <th style="vertical-align:top;text-align:center;">Downloads<br>
                                    <a title="Sort Descending" href="{$orderbystats_desc}"><i class="fas fa-chevron-down"></i></a>
                                    <a title="Sort Ascending" href="{$orderbystats_asc}"><i class="fas fa-chevron-up"></i></a>
                                </th>
                                <th class="column-title no-link last" style="display: table-cell;">Action</th>
							</tr>
							</thead>
							<tbody>
							{foreach $results as $result}
								<tr class="{cycle values=",alt"}{if $lastvisit|strtotime<$result->adddate|strtotime} new{/if}"
									id="guid{$result->guid}">
									<td>
										<input id="chk{$result->guid|substr:0:7}" type="checkbox" class="flat"
											   value="{$result->guid}">
									</td>
									<td class="item">
										<label for="chk{$result->guid|substr:0:7}">
											<a class="title" title="View details"
											   href="{{url("/details/{$result->guid}")}}">{$result->searchname|escape:"htmlall"|truncate:150:"...":true}</a>{if !empty($result->failed)}
											<i class="fa fa-exclamation-circle" style="color: red"
											   title="This release has failed to download for some users"></i>{/if}
										</label value="Searchname">
										<div class="resextra">
											<div class="btns" style="float:right">
												{release_flag($result->searchname, browse)}
												{if $result->passwordstatus == 1}
													<img title="RAR/ZIP is Passworded."
														 src="{{asset("/assets/images/icons/lock.gif")}}"
														 alt="RAR/ZIP is Passworded.">
												{/if}
												{if $result->videostatus > 0}
													<a
															class="model_prev badge bg-info"
															href="{{url("/details/{$result->guid}")}}"
															title="This release has a video preview."
															rel="preview"
													><i class="icon-youtube-play"></i>
													</a>
												{/if}
												{if $result->nfoid > 0}
													<a href="{{url("/nfo/{$result->guid}")}}"
													   title="View Nfo"
													   class="modal_nfo badge bg-info" rel="nfo">Nfo</a>
												{/if}
												{if $result->imdbid > 0}
													<a href="#" name="name{$result->imdbid}" title="View movie info"
													   class="modal_imdb badge bg-info" rel="movie">Cover</a>
												{/if}
												{if $result->haspreview == 1 && $userdata->can('preview') == true}
												<a href="{{url("/covers/preview/{$result->guid}_thumb.jpg")}}"
												   name="name{$result->guid}"
												   data-fancybox
												   title="Screenshot of {$result->searchname|escape:"htmlall"}"
												   class="badge bg-info" rel="preview">Preview</a>{/if}
												{if $result->jpgstatus == 1 && $userdata->can('preview') == true}
												<a href="{{url("/covers/sample/{$result->guid}_thumb.jpg")}}"
												   name="name{$result->guid}"
												   data-fancybox
												   title="Sample of {$result->searchname|escape:"htmlall"}"
												   class="badge bg-info" rel="preview">Sample</a>{/if}
												{if $result->musicinfo_id > 0}
													<a href="#" name="name{$result->musicinfo_id}"
													   title="View music info"
													   class="modal_music badge bg-info" rel="music">Cover</a>
												{/if}
												{if $result->consoleinfo_id > 0}
													<a href="#" name="name{$result->consoleinfo_id}"
													   title="View console info"
													   class="modal_console badge bg-info" rel="console">Cover</a>
												{/if}
												{if $result->videos_id > 0}
													<a class="badge bg-info"
													   href="{{url("/series/{$result->videos_id}")}}"
													   title="View all episodes">View
														Series</a>
												{/if}
												{if $result->anidbid > 0}
													<a class="badge bg-info"
													   href="{{url("/anime?id={$result->anidbid}")}}"
													   title="View all episodes">View
														Anime</a>
												{/if}
												{if isset($result->firstaired) && $result->firstaired != ''}
													<span class="seriesinfo badge bg-info"
														  title="{$result->guid}">Aired {if $result->firstaired|strtotime > $smarty.now}in future{else}{$result->firstaired|daysago}{/if}</span>
												{/if}
												{if $result->group_name != ""}
													<a class="badge bg-info"
													   href="{{url("/browse/group?g={$result->group_name|escape:"htmlall"}")}}"
													   title="Browse {$result->group_name}">{$result->group_name|escape:"htmlall"|replace:"alt.binaries.":"a.b."}</a>
												{/if}
												{if !empty($result->failed)}<span class="badge bg-info">
													<i class="fa fa-thumbs-o-up"></i>
													{$result->grabs} Grab{if $result->grabs != 1}s{/if} /
													<i class="fa fa-thumbs-o-down"></i>
													{$result->failed} Failed Download{if $result->failed != 1}s{/if}
													</span>
												{/if}
											</div>
										</div>
									</td>
									<td class="category">
										<a title="Browse {$result->category_name}"
										   href="{{url("/browse/{$result->parent_category}/{$result->sub_category}")}}"> {$result->category_name}</a>
									</td>
									<td class="posted" title="{{Timezone::convertToLocal(Illuminate\Support\Carbon::parse($result->postdate, config('app.timezone')), 'Y-m-d h:i:s')}}">
                                        {{{Timezone::convertToLocal(Illuminate\Support\Carbon::parse($result->postdate, config('app.timezone')), 'Y-m-d h:i:s')}}|timeago}
									</td>
									<td class="size">
										{$result->size|filesize}
										{if $result->completion > 0}
											<br>
											{if $result->completion < 100}
												<span class="warning">{$result->completion}%</span>
											{else}
												{$result->completion}%
											{/if}
										{/if}
									</td>
									<td class="files">
										<a title="View file list"
										   href="{{url("/filelist/{$result->guid}")}}">{$result->totalpart}</a>
										{if $result->rarinnerfilecount > 0}
											<div class="rarfilelist">
												<img src="{{asset("/assets/images/icons/magnifier.png")}}"
													 alt="{$result->guid}">
											</div>
										{/if}
									</td>
									<td class="stats">
										<a title="View comments"
										   href="{{url("/details/{$result->guid}/#comments")}}">{$result->comments}
											cmt{if $result->comments != 1}s{/if}</a>
										<br>{$result->grabs} grab{if $result->grabs != 1}s{/if}
									</td>
									<td>
										<a href="{{url("/getnzb?id={$result->guid}")}}"
										   class="icon_nzb text-muted"><i
													class="fa fa-cloud-download text-muted"
													data-bs-toggle="tooltip" data-bs-placement="top" title
													data-original-title="Download NZB"></i></a>
										<a href="{{url("/details/{$result->guid}/#comments")}}"><i
													class="fa fa-comments-o text-muted"
													data-bs-toggle="tooltip" data-bs-placement="top" title
													data-original-title="Comments"></i></a>
                                        <a href="#"><i
                                                id="guid{$result->guid}"
                                                class="icon_cart text-muted fa fa-shopping-basket"
                                                data-bs-toggle="tooltip"
                                                data-bs-placement="top" title
                                                data-original-title="Send to my download basket"></i></a>
									</td>
								</tr>
							{/foreach}
							</tbody>
						</table>
						<br/>
						<div class="row">
							<div class="col-md-4">
								<div class="nzb_multi_operations">
									<small>With Selected:</small>
									<div class="btn-group">
										<button type="button"
												class="nzb_multi_operations_download btn btn-sm btn-success"
												data-bs-toggle="tooltip" data-bs-placement="top" title
												data-original-title="Download NZBs">
											<i class="fa fa-cloud-download"></i></button>
										<button type="button"
												class="nzb_multi_operations_cart btn btn-sm btn-info"
												data-bs-toggle="tooltip" data-bs-placement="top" title
												data-original-title="Send to my Download Basket">
											<i class="fa fa-shopping-basket"></i></button>
										{if isset($isadmin)}
											<input type="button"
												   class="nzb_multi_operations_edit btn btn-sm btn-warning"
												   value="Edit"/>
											<input type="button"
												   class="nzb_multi_operations_delete btn btn-sm btn-danger"
												   value="Delete"/>
										{/if}
									</div>
								</div>
							</div>
							{if    count($results) > 0}
								<div class="col-md-8">
									{$results->onEachSide(5)->links()}
								</div>
							{/if}
						</div>
						<br><br><br>
					{{Form::close()}}
				</div>
			{/if}
		</div>
