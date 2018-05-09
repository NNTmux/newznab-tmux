<div class="header">
	<div class="breadcrumb-wrapper">
		<ol class="breadcrumb">
			<li><a href="{$smarty.const.WWW_TOP}{$site->home_link}">Home</a></li>
			/ {$catname|escape:"htmlall"}
		</ol>
	</div>
</div>
<div class="well well-sm">
	{include file='search-filter.tpl'}
</div>
<form id="nzb_multi_operations_form" action="get">
	<div class="box-body">
		<div class="row">
			<div class="col-lg-12 col-sm-12 col-xs-12">
				<div class="panel panel-default">
					<div class="panel-body pagination2">
						<div class="row">
							<div class="col-md-8">
								<div class="nzb_multi_operations">
									View: <strong>Covers</strong> | <a
											href="{$smarty.const.WWW_TOP}/browse/console/{$categorytitle}">List</a><br/>
									Check all: <input type="checkbox" class="square-all"/> <br/>
									With Selected:
									<div class="btn-group">
										<input type="button"
											   class="nzb_multi_operations_download btn btn-sm btn-success"
											   value="Download NZBs"/>
										<input type="button"
											   class="nzb_multi_operations_cart btn btn-sm btn-info"
											   value="Send to my Download Basket"/>
										{if isset($sabintegrated) && $sabintegrated !=""}
											<input type="button"
												   class="nzb_multi_operations_sab btn btn-sm btn-primary"
												   value="Send to Queue"/>
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
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</form>
<div class="box-body">
	<div class="row">
		<div class="col-lg-12 col-sm-12 col-xs-12">
			<div class="panel panel-default">
				<div class="panel-body">
					<div class="row">
						<div class="col-md-8">
							{if count($results) > 0}
								<div class="col-md-4">
									{$results->links()}
								</div>
								{foreach $resultsadd as $result}
									<div class="panel panel-default">
										<div class="panel-body">
											<div class="row">
												<div class="col-md-2 small-gutter-left">
													<a title="View details"
													   href="{$smarty.const.WWW_TOP}/details/{$result.grp_release_guid}">
														<img src="{$smarty.const.WWW_TOP}/covers/console/{if $result.cover == 1}{$result.consoleinfo_id}.jpg{else}{$smarty.const.WWW_ASSETS}/images/no-cover.png{/if}"
															 class="img-responsive img-rounded"
															 width="140" border="0"
															 alt="{$result.title|escape:"htmlall"}"/>{if !empty($result.grp_release_failed)}
														<i class="zmdi zmdi-alarm" style="color: red"
														   title="This release has failed to download for some users"></i>{/if}
													</a>
													{if $result.url != ""}<a class="label label-default"
																			 target="_blank"
																			 href="{$site->dereferrer_link}{$result.url}"
																			 name="amazon{$result.consoleinfo_id}"
																			 title="View Amazon page">
															Amazon</a>{/if}
													{if $result.nfoid > 0}<a
														href="{$smarty.const.WWW_TOP}/nfo/{$result.grp_release_guid}"
														title="View NFO" class="modal_nfo label label-default"
														rel="nfo">
															NFO</a>{/if}
													<a class="label label-default"
													   href="{$smarty.const.WWW_TOP}/browse/group?g={$result.group_name}"
													   title="Browse releases in {$result.group_name|replace:"alt.binaries":"a.b"}">Group</a>
													{if !empty($result.grp_release_failed)}
														<span class="btn btn-default btn-xs"
															  title="This release has failed to download for some users">
														<i class="zmdi zmdi-thumbs-o-up"></i> {$result.grp_release_grabs}
															Grab{if {$result.grp_release_grabs} != 1}s{/if} / <i
																	class="zmdi zmdi-thumbs-o-down"></i> {$result.grp_release_failed}
															Failed Download{if {$result.grp_release_failed} > 1}s{/if}</span>
													{/if}
												</div>
												<div class="col-md-10 small-gutter-left">
													<h4><a title="View details"
														   href="{$smarty.const.WWW_TOP}/details/{$result.grp_release_guid}">{$result.title|escape:"htmlall"}</a>
													</h4>
													<table>
														<tr>
															<td id="guid{$result.grp_release_guid}">
																<label>
																	<input type="checkbox"
																		   class="square"
																		   value="{$result.grp_release_guid}"
																		   id="chksingle"/>
																</label>
																<span class="label label-default">{$result.grp_release_size|fsize_format:"MB"}</span>
																<span class="label label-default">Posted {$result.grp_release_postdate|timeago}
																	ago</span>
																{if isset($isadmin)}
																<a
																		class="label label-warning"
																		href="{$smarty.const.WWW_TOP}/admin/release-edit?id={$result.grp_release_id}&amp;from={$smarty.server.REQUEST_URI}"
																		title="Edit release">
																		Edit</a>{/if}
																<br/>
																{if isset($result.genre) && $result.genre != ""}
																	<b>Genre:</b>
																	{$result.genre}
																	<br/>
																{/if}
																{if isset($result.esrb) && $result.esrb != ""}
																	<b>Rating:</b>
																	{$result.esrb}
																	<br/>
																{/if}
																{if isset($result.publisger) && $result.publisher != ""}
																	<b>Publisher:</b>
																	{$result.publisher}
																	<br/>
																{/if}
																{if isset($result.releasedate) && $result.releasedate != ""}
																	<b>Released:</b>
																	{$result.releasedate|date_format}
																	<br/>
																{/if}
																{if isset($result.review) && $result.review != ""}
																	<b>Review:</b>
																	{$result.review|escape:'htmlall'}
																	<br/>
																{/if}
																<div>
																	<a role="button"
																	   class="btn btn-default btn-xs"
																	   href="{$smarty.const.WWW_TOP}/getnzb?id={$result.grp_release_guid}"><i
																				class="zmdi zmdi-cloud-download"></i><span
																				class="badge">{$result.grp_release_grabs}
																			Grab{if $result.grp_release_grabs != 1}s{/if}</span></a>
																	<a role="button"
																	   class="btn btn-default btn-xs"
																	   href="{$smarty.const.WWW_TOP}/details/{$result.grp_release_guid}#comments"><i
																				class="zmdi zmdi-comment-o"></i><span
																				class="badge">{$result.grp_release_comments}
																			Comment{if $result.grp_release_comments != 1}s{/if}</span></a>
																	<span
																			id="guid{$result.grp_release_guid}"
																			class="btn btn-hover btn-default btn-xs icon_cart text-muted"
																			title="Send to my Download Basket"><i
																				class="zmdi zmdi-shopping-basket"></i>
																		</span>
																	{if isset($sabintegrated) && $sabintegrated !=""}
																		<span
																				id="guid{$result.grp_release_guid}"
																				class="btn btn-hover btn-default btn-xs icon_sab text-muted"
																				title="Send to my Queue"><i
																					class="zmdi zmdi-share"></i>
																			</span>
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
							{else}
								No console releases with covers available!
							{/if}
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<form id="nzb_multi_operations_form" action="get">
	<div class="box-body">
		<div class="row">
			<div class="col-lg-12 col-sm-12 col-xs-12">
				<div class="panel panel-default">
					<div class="panel-body pagination2">
						<div class="row">
							<div class="col-md-8">
								<div class="nzb_multi_operations">
									View: <strong>Covers</strong> | <a
											href="{$smarty.const.WWW_TOP}/browse/{$category->title}">List</a><br/>
									Check all: <input type="checkbox" class="square-all"/> <br/>
									With Selected:
									<div class="btn-group">
										<input type="button"
											   class="nzb_multi_operations_download btn btn-sm btn-success"
											   value="Download NZBs"/>
										<input type="button"
											   class="nzb_multi_operations_cart btn btn-sm btn-info"
											   value="Send to my Download Basket"/>
										{if isset($sabintegrated) && $sabintegrated !=""}
											<input type="button"
												   class="nzb_multi_operations_sab btn btn-sm btn-primary"
												   value="Send to Queue"/>
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
							{if    count($results) > 0}
								<div class="col-md-4">
									{$results->links()}
								</div>
							{/if}
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</form>
