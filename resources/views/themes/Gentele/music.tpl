<div class="header">
	<div class="breadcrumb-wrapper">
		<ol class="breadcrumb">
			<li><a href="{{url({$site->home_link})}}">Home</a></li>
			/  {if !empty({$catname->parent->title})}<a href="{{url("browse/{$catname->parent->title}")}}">{$catname->parent->title}</a>{else}<a href="{{url("/browse/{$catname->title}")}}">{$catname->title}</a>{/if}
			/ {if !empty({$catname->parent->title})}<a href="{{url("/browse/{$catname->title}")}}">{$catname->title}</a>{else}All{/if}
		</ol>
	</div>
</div>
<div class="card card-header">
	{include file='search-filter.tpl'}
</div>
{{Form::open(['id' => 'nzb_multi_operations_form', 'method' => 'get'])}}
	<div class="box-body"
	<div class="row">
		<div class="col-lg-12 col-sm-12 col-12">
			<div class="card card-default">
				<div class="card-body pagination2">
					<div class="row">
						<div class="col-md-4">
							<div class="nzb_multi_operations">
								View: <strong>Covers</strong> | <a
										href="{{url("/browse/Audio/{$categorytitle}")}}">List</a><br/>
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
						<div class="col-md-8">
							{$results->onEachSide(5)->links()}
						</div>
					</div>
					<hr>
					{foreach $resultsadd as $result}
						<div class="card card-default">
							<div class="card-body">
								<div class="row">
									<div class="col-md-2 small-gutter-left">
										<a title="View details"
										   href="{{url("/details/{$result->guid}")}}">
											<img src="{{url("/covers/music/{if $result->cover == 1}{$result->musicinfo_id}.jpg{else}{{url("/images/no-cover.png")}}{/if}")}}"
												 class="img-fluid rounded"
												 width="140" border="0"
												 alt="{$result->artist|escape:"htmlall"} - {$result->title|escape:"htmlall"}"/>{if !empty($result->failed)}
											<i class="fa fa-exclamation-circle" style="color: red"
											   title="This release has failed to download for some users"></i>{/if}
										</a>
										{if $result->url != ""}<a class="badge bg-info"
																 target="_blank"
																 href="{$site->dereferrer_link}{$result->url}"
																 name="amazon{$result->musicinfo_id}"
																 title="View Amazon/iTunes page">
												Amazon</a>{/if}
										{if $result->nfoid > 0}<a
											href="{{url("/nfo/{$result->guid}")}}"
											title="View NFO" class="badge bg-info" rel="nfo">
												NFO</a>{/if}
										<a class="badge bg-info"
										   href="{{url("/browse/group?g={$result->group_name}")}}"
										   title="Browse releases in {$result->group_name|replace:"alt.binaries":"a.b"}">Group</a>
										{if !empty($result->failed)}
											<span class="btn btn-light btn-xs"
												  title="This release has failed to download for some users">
														<i class="fa fa-thumbs-o-up"></i> {$result->grabs}
												Grab{if {$result->grabs} != 1}s{/if} / <i
														class="fa fa-thumbs-o-down"></i> {$result->failed}
												Failed Download{if {$result->failed} > 1}s{/if}</span>
										{/if}
									</div>
									<div class="col-md-10 small-gutter-left">
										<h4><a title="View details"
											   href="{{url("/details/{$result->guid}")}}">{$result->artist|escape:"htmlall"}
												- {$result->title|escape:"htmlall"}</a> (<a class="title"
																						   title="{$result->year}"
																						   href="{{url("/music?year={$result->year}")}}">{$result->year}</a>)
										</h4>
										<table class="data table table-striped responsive-utilities jambo-table">
											<tr>
												<td id="guid{$result->guid}">
													<label>
														<input type="checkbox"
															   class="flat"
															   value="{$result->guid}" id="chksingle"/>
													</label>
													<span class="badge bg-info">{$result->size|filesize}</span>
													<span class="badge bg-info">Posted {$result->postdate|timeago}
														ago</span>
													{if isset($isadmin)}<a class="badge bg-warning"
																		   href="{{url("/admin/release-edit?id={$result->guid}")}}"
																		   title="Edit release">
															Edit</a>{/if}
													<br/>
													{if $result->genre != ""}
														<b>Genre:</b>
														<a href="{{url("/music/?genre={$result->genres_id}")}}">{$result->genre|escape:"htmlall"}</a>
														<br/>
													{/if}
													{if $result->publisher != ""}
														<b>Publisher:</b>
														{$result->publisher|escape:"htmlall"}
														<br/>
													{/if}
													{if $result->releasedate != ""}
														<b>Released:</b>
														{$result->releasedate|date_format}
														<br/>
													{/if}
													<div>
														<a role="button" class="btn btn-light btn-xs"
														   data-bs-toggle="tooltip" data-bs-placement="top" title
														   data-original-title="Download NZB"
														   href="{{url("/getnzb?id={$result->guid}")}}"><i
																	class="fa fa-cloud-download"></i><span
																	class="badge bg-info"> {$result->grabs}
																Grab{if $result->grabs != 1}s{/if}</span></a>
														<a role="button" class="btn btn-light btn-xs"
														   href="{{url("/details/{$result->guid}/#comments")}}"><i
																	class="fa fa-comment-o"></i><span
																	class="badge bg-info"> {$result->comments}
																Comment{if $result->comments != 1}s{/if}</span></a>
                                                        <a href="{{url("/cart/add?id={$result->guid}")}}" target="_blank"><span class="btn btn-hover btn-light btn-xs icon text-muted"
															  data-bs-toggle="tooltip" data-bs-placement="top"
															  data-original-title="Send to my download basket"><i
                                                                    class="fa fa-shopping-basket"></i></span></a>
														{if !empty($result->failed)}
															<span class="btn btn-light btn-xs"
																  title="This release has failed to download for some users">
																		<i class="fa fa-thumbs-o-up"></i> {$result->grabs}
																Grab{if {$result->grabs} != 1}s{/if} / <i
																		class="fa fa-thumbs-o-down"></i> {$result->failed}
																Failed Download{if {$result->failed} > 1}s{/if}</span>
														{/if}
													</div>
												</td>
											</tr>
										</table>
									</div>
								</div>
							</div>
						</div>
					{/foreach}
					<hr>
					<div class="row">
						<div class="col-md-4">
							<div class="nzb_multi_operations">
								View: <strong>Covers</strong> | <a
										href="{{url("/browse/Audio/{$categorytitle}")}}">List</a><br/>
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
						<div class="col-md-8">
							{$results->onEachSide(5)->links()}
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
{{Form::close()}}
