<div class="well well-small"
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
				<li><a href="{{url("{$site->home_link}")}}">Home</a></li>
				/ Anime
			</ol>
		</div>
	</div>
	<div class="well well-sm">
		<h1>{$animeTitle}
			{if isset($isadmin)}
				<a class="btn btn-xs btn-warning" title="Edit AniDB data"
				   href="{{url("/admin/anidb-edit/{$animeAnidbid}")}}">Edit</a>
			{/if}
		</h1>
		{if animePicture != ''}
			<div style="text-align: center;">
				<img class="shadow img img-polaroid" alt="{$animeTitle} Picture"
					 src="{{url("/covers/anime/{$animeAnidbid}.jpg")}}"/>
			</div>
			<br/>
		{/if}
		<p>
			{if $animeType != ''}({$animeType|escape:"htmlall"}){/if}<br>
			{if $animeCategories != ''}<b>{$animeCategories}</b><br/>{/if}
			<span class="descinitial">{$animeDescription|escape:"htmlall"|nl2br|magicurl|truncate:"1500":" </span><a class=\"descmore\" href=\"#\"> more...</a>"}
				{if $animeDescription|strlen > 1500}<span
						class="descfull">{$animeDescription|escape:"htmlall"|nl2br|magicurl}</span>{else}</span>{/if}
		</p>
		<p>
			{if $animeCharacters != ''}<br>Characters: {$animeCharacters|escape:"htmlall"}{/if}
			<br>
			{if $animeCreators !=''}<br><b>Created by: {$animeCreators|escape:"htmlall"}</b>{/if}
		</p>
		<p>
			{if $animeStartDate != '' && $animeStartDate != '1970-01-01'}
				<br>
				<b>Started:
				{$animeStartDate|escape:"htmlall"}</b>{/if}
			{if $animeEndDate != '' && $animeEndDate != '1970-01-01'}
				<br>
				<b>Ended:
				{$animeEndDate|escape:"htmlall"}</b>{/if}
			{if $animeRating != ''}<br><b>AniDB Rating: {$animeRating|escape:"htmlall"}</b>{/if}
			{if $animeRelated != ''}<br><i>Related Anime: {$animeRelated|escape:"htmlall"}</i><br/>{/if}
		</p>
		<div style="text-align: center;">
			<div class="btn-group">
				<a class="btn btn-sm btn-success"
				   href="{$site->dereferrer_link}http://anidb.net/perl-bin/animedb.pl?show=anime&amp;aid={$animeAnidbid}"
				   title="View AniDB">View AniDB</a>
				<a class="btn btn-sm btn-success"
				   href="{{url("/rss/full-feed?anidb={$animeAnidbid}&amp;dl=1&amp;i={$userdata.id}&amp;api_token={$userdata.api_token}")}}">RSS
					feed for this Anime <i class="fa fa-rss"></i></a>
			</div>
		</div>
	</div>
	{{Form::open(['id' => 'nzb_multi_operations_form', 'method' => 'get'])}}
		<div class="well well-sm">
			<div class="nzb_multi_operations">
				With Selected:
				<div class="btn-group">
					<button type="button"
							class="nzb_multi_operations_download btn btn-sm btn-success"
							data-bs-toggle="tooltip" data-bs-placement="top" title data-original-title="Download NZBs">
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
			<div class="row">
				<div class="col-lg-12 col-sm-12 col-12">
					<div class="card card-default">
						<div class="card-body pagination2">
							<table style="width:100%;" class="data table table-striped responsive-utilities jambo-table"
								   id="browsetable">
								<tr>
									<th><input id="check-all" type="checkbox" class="flat-all"/> Select All</th>
									<th>Name</th>
									<th>Category</th>
									<th>Posted</th>
									<th>Size</th>
									<th>Action</th>
								</tr>
								{foreach $animeEpisodeTitles as $result}
									<tr class="{cycle values=",alt"}">
										<td>
											<input id="guid{$result->guid}"
												   type="checkbox" class="flat"
												   value="{$result->guid}"/></td>
										<td>
											<a title="View details"
											   href="{{url("/details/{$result->guid}")}}">{$result->searchname|escape:"htmlall"|replace:".":" "}</a>
											<div>
												<div>
													{if $result->nfoid > 0}<span><a
																href="{{url("/nfo/{$result->guid}")}}"
																class="modal_nfo badge bg-info text-muted">NFO</a>
														</span>{/if}
													{if $result->haspreview == 1 && $userdata->can('preview') == true}<a
														href="{{url("/covers/preview/{$result->guid}_thumb.jpg")}}"
														name="name{$result->guid}"
														title="Screenshot of {$result->searchname|escape:"htmlall"}"
														class="badge bg-info" rel="preview">Preview</a>{/if}
													<span class="badge bg-info">{$result->grabs}
														Grab{if $result->grabs != 1}s{/if}</span>
													{if $result->reid > 0}<span class="mediainfo badge bg-info"
																			   title="{$result->guid}">Media</span>{/if}
												</div>
											</div>
										</td>
										<td><span class="badge bg-info">{$result->category_name}</span></td>
										<td width="40" title="{{Timezone::convertToLocal(Illuminate\Support\Carbon::parse($result->postdate, config('app.timezone')), 'Y-m-d h:i:s')}}">{{{Timezone::convertToLocal(Illuminate\Support\Carbon::parse($result->postdate), 'Y-m-d h:i:s')}}|timeago}</td>
										<td>{$result->size|filesize}</td>
										<td class="icon_nzb"><a
													href="{{url("/getnzb?id={$result->guid}")}}"><i
														class="fa fa-cloud-download text-muted" data-bs-toggle="tooltip"
														data-bs-placement="top" title
														data-original-title="Download NZB"></i></a>
											<a href="{{url("/details/{$result->guid}/#comments")}}"><i
														class="fa fa-comments-o text-muted" data-bs-toggle="tooltip"
														data-bs-placement="top" title
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
							</table>
						</div>
					</div>
				</div>
			</div>
		</div>
	{{Form::close()}}
{/if}
