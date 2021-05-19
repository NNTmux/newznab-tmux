<div class="header">
	<div class="breadcrumb-wrapper">
		<ul class="breadcrumb">
			<li><a href="{{url("{$site->home_link}")}}">Home</a></li>
			/
			<a href="{{url("/{if preg_match('/^alt\.binaries|a\.b|dk\./i', $parentcat)}browse/group?g={else}browse/{/if}{if ($parentcat == 'music')}Audio{else}{$parentcat}{/if}")}}">{$parentcat}</a>
			/ {if ($catname != '' && $catname != 'all')} <a
				href="{url("/browse/{$parentcat}/{$catname}")}}">{$catname}</a>{/if}
		</ul>
	</div>
</div>
{$site->adbrowse}
{if count($results) > 0}
	{{Form::open(['id' => 'nzb_multi_operations_form', 'method' => 'get'])}}
		<div class="box-body">
			<div class="row">
				<div class="col-md-12 col-12">
					<div class="card card-default">
						<div class="card-body">
							<div class="row">
								<div class="col-md-4">
									{if isset($shows)}
										<p>
											<a href="{{route('series')}}"
											   title="View available TV series">Series List</a> |
											<a title="Manage your shows" href="{{route('myshows')}}">Manage
												My Shows</a> |
											<a title="All releases in your shows as an RSS feed"
											   href="{{url("/rss/myshows?dl=1&amp;i={$userdata.id}&amp;api_token={$userdata.api_token}")}}">Rss
												Feed</a>
										</p>
									{/if}
									<div class="nzb_multi_operations">
										{if isset($covgroup) && $covgroup != ''}View:
											<a href="{{url("/{$covgroup}/{$category}")}}">Covers
											</a>
											|
											<b>List</b>
											<br/>
										{/if}
										With Selected:
										<div class="btn-group">
											<button type="button"
													class="nzb_multi_operations_download btn btn-sm btn-success"
													data-bs-toggle="tooltip" data-placement="top" title
													data-original-title="Download NZBs">
												<i class="fa fa-cloud-download"></i></button>
											<button type="button"
													class="nzb_multi_operations_cart btn btn-sm btn-info"
													data-bs-toggle="tooltip" data-placement="top" title
													data-original-title="Send to my Download Basket">
												<i class="fa fa-shopping-basket"></i></button>

											{if isset($sabintegrated) && $sabintegrated !=""}
												<button type="button"
														class="nzb_multi_operations_sab btn btn-sm btn-success"
														data-bs-toggle="tooltip" data-placement="top" title
														data-original-title="Send to Queue">
													<i class="fa fa-share"></i></button>
											{/if}
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
                                <div class="col-md-8">
                                    {$results->onEachSide(5)->links()}
                                </div>
							</div>
							<hr>
							<div class="container">
								<table class="data table table-striped">
									<thead class="thead-light">
									<tr class="headings">
										<th><input id="check-all" type="checkbox" class="flat-all"></th>
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
									{foreach $resultsadd as $result}
										<tr id="guid{$result->guid}">
											<td><input id="chk{$result->guid|substr:0:7}"
													   type="checkbox" name="table_records" class="flat"
													   value="{$result->guid}"/></td>
											<td>
												<a href="{{url("/details/{$result->guid}")}}"
												   class="title">{$result->searchname|escape:"htmlall"|replace:".":" "}</a>{if !empty($result->failed)}
												<i class="fa fa-exclamation-circle" style="color: red"
												   title="This release has failed to download for some users"></i>{/if}
												<br/>
												<span class="badge bg-info">{$result->grabs}
													Grab{if $result->grabs != 1}s{/if}</span>
												{if $result->nfoid > 0}<span><a
															href="{{url("/nfo/{$result->guid}")}}"
															class="modal_nfo badge bg-info" rel="nfo">NFO</a>
													</span>{/if}
												{if $result->jpgstatus == 1 && $userdata->can('preview') == true}<span><a
															href="{{url("/covers/sample/{$result->guid}_thumb.jpg")}}"
															name="name{$result->guid}"
															data-fancybox
															class="badge bg-info"
															rel="preview">Sample</a></span>{/if}
												{if $result->haspreview == 1 && $userdata->can('preview') == true}<span><a
															href="{{url("/covers/preview/{$result->guid}_thumb.jpg")}}"
															name="name{$result->guid}"
															data-fancybox
															class="badge bg-info"
															rel="preview">Preview</a></span>{/if}
												{if $result->videos_id > 0}<span><a
															href="{{url("/series/{$result->videos_id}")}}"
															class="badge bg-info" rel="series">View TV</a>
													</span>{/if}
												{if !empty($result->firstaired)}<span
													class="seriesinfo badge bg-info" title="{$result->guid}">
													Aired {if $result->firstaired|strtotime > $smarty.now}in future{else}{$result->firstaired|daysago}{/if}</span>{/if}
												{if $result->anidbid > 0}<span><a class="badge bg-info"
																				 href="{{url("/anime?id={$result->anidbid}")}}">View
												Anime</a></span>{/if}
												{if !empty($result->failed)}<span class="badge bg-info">
													<i class="fa fa-thumbs-o-up"></i>
													{$result->grabs} Grab{if $result->grabs != 1}s{/if} /
													<i class="fa fa-thumbs-o-down"></i>
													{$result->failed} Failed Download{if $result->failed != 1}s{/if}
													</span>{/if}
												<span class="badge bg-info">{$result->group_name}</span>
												<span class="badge bg-info">{$result->fromname}</span>
                                                {if $lastvisit|strtotime<$result->adddate|strtotime}
                                                    <span class="badge bg-success">New</span>
                                                {/if}
											</td>
											<td><span class="badge bg-info">{$result->category_name}</span>
											</td>
											<td>{Illuminate\Support\Carbon::parse({{Timezone::convertToLocal(Illuminate\Support\Carbon::parse($result->postdate, config('app.timezone')), 'Y-m-d h:i:s')}})->diffForHumans()}</td>
											<td>{$result->size|filesize}</td>
                                            <td style="width:auto;text-align:center;white-space:nowrap;">
                                                <a title="View file list" href="{{url("/filelist/{$result.guid}")}}">{$result->totalpart}</a>
                                                <i class="far fa-file"></i>
                                                    {if $result->rarinnerfilecount > 0}
                                                    <div class="rarfilelist">
                                                        <i class="fas fa-search-plus"></i>
                                                    </div>
                                                    {/if}
                                            </td>
                                            <td>{$result->grabs}</td>
											<td>
												<a href="{{url("/getnzb?id={$result->guid}")}}"
												   class="icon_nzb text-muted"><i
															class="fa fa-cloud-download text-muted"
															data-bs-toggle="tooltip" data-placement="top" title
															data-original-title="Download NZB"></i></a>
												<a href="{{url("/details/{$result->guid}/#comments")}}"><i
															class="fa fa-comments-o text-muted"
															data-bs-toggle="tooltip" data-placement="top" title
															data-original-title="Comments"></i></a>
												<a href="#"><i
															id="guid{$result->guid}"
															class="icon_cart text-muted fa fa-shopping-basket"
															data-bs-toggle="tooltip"
															data-placement="top" title
															data-original-title="Send to my download basket"></i></a>
												{if isset($sabintegrated) && $sabintegrated !=""}
													<a href="#">
														<i id="guid{$result->guid}"
														   class="icon_sab text-muted fa fa-share"
														   data-bs-toggle="tooltip"
														   data-placement="top" title
														   data-original-title="Send to my Queue">
														</i>
													</a>
												{/if}
												{if $weHasVortex}
													<a href="#" class="icon_vortex text-muted"><i
																class="fa fa-share" data-bs-toggle="tooltip"
																data-placement="top"
																title data-original-title="Send to NZBVortex"></i></a>
												{/if}
											</td>
										</tr>
									{/foreach}
									</tbody>
								</table>
							</div>
							<hr>
							<div class="row">
								<div class="col-md-4">
									<div class="nzb_multi_operations">
										{if isset($covgroup) && $covgroup != ''}View:
											<a href="{{url("/{$covgroup}/{$category}")}}">Covers</a>
											|
											<b>List</b>
											<br/>
										{/if}
										With Selected:
										<div class="btn-group">
											<button type="button"
													class="nzb_multi_operations_download btn btn-sm btn-success"
													data-bs-toggle="tooltip" data-placement="top" title
													data-original-title="Download NZBs">
												<i class="fa fa-cloud-download"></i></button>
											<button type="button"
													class="nzb_multi_operations_cart btn btn-sm btn-info"
													data-bs-toggle="tooltip" data-placement="top" title
													data-original-title="Send to my Download Basket">
												<i class="fa fa-shopping-basket"></i></button>

											{if isset($sabintegrated) && $sabintegrated !=""}
												<button type="button"
														class="nzb_multi_operations_sab btn btn-sm btn-success"
														data-bs-toggle="tooltip" data-placement="top" title
														data-original-title="Send to Queue">
													<i class="fa fa-share"></i></button>
											{/if}
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
									<div class="col-md-8">
										{$results->onEachSide(5)->links()}
									</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	{{Form::close()}}
{else}
    No releases indexed yet!
{/if}
