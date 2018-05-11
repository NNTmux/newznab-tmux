<div class="header" xmlns="http://www.w3.org/1999/html" xmlns="http://www.w3.org/1999/html"
	 xmlns="http://www.w3.org/1999/html">
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
	<div class="box-body"
	<div class="row">
		<div class="col-lg-12 col-sm-12 col-xs-12">
			<div class="panel panel-default">
				<div class="panel-body pagination2">
					<div class="row">
						<div class="col-md-8">
							<div class="nzb_multi_operations">
								View: <strong>Covers</strong> | <a
										href="{$smarty.const.WWW_TOP}/browse/xxx/{$categorytitle}">List</a><br/>
								With Selected:
								<div class="btn-group">
									<button type="button"
											class="nzb_multi_operations_download btn btn-sm btn-success"
											data-toggle="tooltip" data-placement="top" title
											data-original-title="Download NZBs">
										<i class="fa fa-cloud-download"></i></button>
									<button type="button"
											class="nzb_multi_operations_cart btn btn-sm btn-info"
											data-toggle="tooltip" data-placement="top" title
											data-original-title="Send to my Download Basket">
										<i class="fa fa-shopping-basket"></i></button>

									{if isset($sabintegrated) && $sabintegrated !=""}
										<button type="button"
												class="nzb_multi_operations_sab btn btn-sm btn-primary"
												data-toggle="tooltip" data-placement="top" title
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
						<div class="col-md-4">
							{$results->links()}
						</div>
					</div>
					<hr>
					{foreach $resultsadd as $result}
						<!-- Iteratie: {counter} -->
						{if isset($result->category_name)}
							{assign var="catnamesplit" value=">"|explode:$result->category_name}
						{/if}
						{if $result@iteration is odd by 1}
							<!-- Begin Row -->
							<div class="row">
								<!-- Left -->
								<div class="col-md-6 small-gutter-right movie-height">
									<div class="panel panel-default">
										<div class="panel-body">
											<div class="row small-gutter-left">
												<div class="col-md-3 small-gutter-left">
													<a href="{$smarty.const.WWW_TOP}/xxx/?id={$result->id}"><img
																class="cover img-responsive img-rounded"
																src="{if $result->cover == 1}{$smarty.const.WWW_TOP}/covers/xxx/{$result->id}-cover.jpg{else}{$smarty.const.WWW_ASSETS}/images/no-cover.png{/if}"
																width="140" border="0"
																alt="{$result->title|escape:"htmlall"}"/>{if !empty($result->failed)}
														<i class="fa fa-exclamation-circle" style="color: red"
														   title="This release has failed to download for some users"></i>{/if}
													</a>
													{if $result->classused == "ade"}
														<a
																target="_blank"
																href="{$site->dereferrer_link}{$result->directurl}"
																name="viewade{$result->title}"
																title="View AdultdvdEmpire page"
														><img
																	src="{$smarty.const.WWW_ASSETS}/images/icons/ade.png"
																	width="100"></a>
													{/if}
													{if $result->classused == "adm"}
														<a
																target="_blank"
																href="{$site->dereferrer_link}{$result->directurl}"
																name="viewadm{$result->title}"
																title="View AdultDVDMarketplace page"
														><img
																	src="{$smarty.const.WWW_ASSETS}/images/icons/adm.png"
																	width="100"></a>
													{/if}
													{if $result->classused == "aebn"}
														<a
																target="_blank"
																href="{$site->dereferrer_link}{$result->directurl}"
																name="viewaebn{$result->title}"
																title="View AEBN page"
														><img src="{$smarty.const.WWW_ASSETS}/images/icons/aebn.png"
															  width="100"></a>
													{/if}
													{if $result->classused == "hotm"}
														<a
																target="_blank"
																href="{$site->dereferrer_link}{$result->directurl}"
																name="viewhm{$result->title}"
																title="View HotMovies page"
														><img src="{$smarty.const.WWW_ASSETS}/images/icons/hotmovies.png"
															  width="100"></a>
													{/if}
													{if $result->classused == "pop"}
														<a
																target="_blank"
																href="{$site->dereferrer_link}{$result->directurl}"
																name="viewpop{$result->id}"
																title="View Popporn page"
														><img src="{$smarty.const.WWW_ASSETS}/images/icons/popporn.png"
															  width="100"></a>
													{/if}
													{if $result->nfoid > 0}<a
														href="{$smarty.const.WWW_TOP}/nfo/{$result->guid}"
														title="View NFO" class="label label-primary"
														rel="nfo">NFO</a>{/if}
													<a class="label label-primary"
													   href="{$smarty.const.WWW_TOP}/browse/group?g={$result->group_name}"
													   title="Browse releases in {$result->group_name|replace:"alt.binaries":"a.b"}">Group</a>
													{if !empty($result->failed)}
														<span class="btn btn-default btn-xs"
															  title="This release has failed to download for some users">
															<i class="fa fa-thumbs-o-up"></i> {$result->grabs}
															Grab{if {$result->grabs} != 1}s{/if} / <i
																	class="fa fa-thumbs-o-down"></i> {$result->failed}
															Failed Download{if {$result->failed} > 1}s{/if}</span>
													{/if}
												</div>
												<div class="col-md-9 small-gutter-left table-responsive">
																<span class="release-title"><a class="text-muted"
																							   href="{$smarty.const.WWW_TOP}/xxx/?id={$result->id}">{$result->title|escape:"htmlall"}</a></span>

													<table class="data table table-striped responsive-utilities jambo-table">
														<tr>
															<td id="guid{$result->guid}">
																<label>
																	<input type="checkbox"
																		   class="flat"
																		   value="{$result->guid}"
																		   id="chksingle"/>
																</label>
																<span class="label label-primary">{if isset($catsplit[0])} {$catsplit[0]}{/if}</span>
																<span class="label label-danger">{if isset($catsplit[1])} {$catsplit[1]}{/if}</span>
																<span class="label label-primary">{$result->size|fsize_format:"MB"}</span>
																<span class="label label-primary">Posted {$result->postdate|timeago}
																	ago</span>
																<br>
																<div class="release-subtitle">{if $result->genre != ''}Genre: {$result->genre}, {/if}</div>
																<br>
																<div class="release-subtitle">{if $result->plot != ''}Plot: {$result->plot}, {/if}</div>
																<br>
																<div class="release-subtitle">{if $result->actors != ''}Cast: {$result->actors}, {/if}</div>
																<br>
																<div class="release-name text-muted"><a
																			href="{$smarty.const.WWW_TOP}/details/{$result->guid}">{$result->name|escape:"htmlall"}</a>
																</div>
																<div>
																	<a role="button" class="btn btn-default btn-xs"
																	   data-toggle="tooltip" data-placement="top" title
																	   data-original-title="Download NZB"
																	   href="{$smarty.const.WWW_TOP}/getnzb?id={$result->guid}"><i
																				class="fa fa-cloud-download"></i><span
																				class="badge"> {$result->grabs}
																			Grab{if $result->grabs != 1}s{/if}</span></a>
																	<a role="button" class="btn btn-default btn-xs"
																	   href="{$smarty.const.WWW_TOP}/details/{$result->guid}/#comments"><i
																				class="fa fa-comment-o"></i><span
																				class="badge"> {$result->comments}
																			Comment{if $result->comments != 1}s{/if}</span></a>
																	<span class="btn btn-hover btn-default btn-xs icon_cart text-muted"
																		  id="guid{$result->guid}"
																		  data-toggle="tooltip" data-placement="top"
																		  title
																		  data-original-title="Send to my download basket"><i
																				class="fa fa-shopping-basket"></i></span>
																	{if isset($sabintegrated) && $sabintegrated !=""}
																		<span class="btn btn-hover btn-default btn-xs icon_sab text-muted"
																			  id="guid{$result->guid}"
																			  data-toggle="tooltip" data-placement="top"
																			  title
																			  data-original-title="Send to my Queue"><i
																					class="fa fa-share"></i></span>
																	{/if}
																	{if !empty($result->failed)}
																		<span class="btn btn-default btn-xs"
																			  title="This release has failed to download for some users">
																	<i class="fa fa-thumbs-o-up"></i> {$result->grabs}
																			Grab{if {$result->grabs} != 1}s{/if}
																			/ <i
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
								</div>
								<!-- /Left -->
								{else}
								<!-- Right -->
								<div class="col-md-6 small-gutter-left movie-height">
									<div class="panel panel-default">
										<div class="panel-body">
											<div class="row small-gutter-left">
												<div class="col-md-3 small-gutter-left">
													<a href="{$smarty.const.WWW_TOP}/xxx/?id={$result->id}"><img
																class="cover img-responsive img-rounded"
																src="{if $result->cover == 1}{$smarty.const.WWW_TOP}/covers/xxx/{$result->id}-cover.jpg{else}{$smarty.const.WWW_ASSETS}/images/no-cover.png{/if}"
																width="140" border="0"
																alt="{$result->title|escape:"htmlall"}"/>{if !empty($result->failed)}
														<i class="fa fa-exclamation-circle" style="color: red"
														   title="This release has failed to download for some users"></i>{/if}
													</a>
													{if $result->classused == "ade"}
														<a
																target="_blank"
																href="{$site->dereferrer_link}{$result->directurl}"
																name="viewade{$result->title}"
																title="View AdultdvdEmpire page"
														><img
																	src="{$smarty.const.WWW_ASSETS}/images/icons/ade.png"
																	width="100"></a>
													{/if}
													{if $result->classused == "adm"}
														<a
																target="_blank"
																href="{$site->dereferrer_link}{$result->directurl}"
																name="viewadm{$result->title}"
																title="View AdultDVDMarketplace page"
														><img
																	src="{$smarty.const.WWW_ASSETS}/images/icons/adm.png"
																	width="100"></a>
													{/if}
													{if $result->classused == "aebn"}
														<a
																target="_blank"
																href="{$site->dereferrer_link}{$result->directurl}"
																name="viewaebn{$result->title}"
																title="View AEBN page"
														><img src="{$smarty.const.WWW_ASSETS}/images/icons/aebn.png"
															  width="100"></a>
													{/if}
													{if $result->classused == "hotm"}
														<a
																target="_blank"
																href="{$site->dereferrer_link}{$result->directurl}"
																name="viewhm{$result->title}"
																title="View HotMovies page"
														><img src="{$smarty.const.WWW_ASSETS}/images/icons/hotmovies.png"
															  width="100"></a>
													{/if}
													{if $result->classused == "pop"}
														<a
																target="_blank"
																href="{$site->dereferrer_link}{$result->directurl}"
																name="viewpop{$result->id}"
																title="View Popporn page"
														><img src="{$smarty.const.WWW_ASSETS}/images/icons/popporn.png"
															  width="100"></a>
													{/if}
													{if $result->nfoid > 0}<a
														href="{$smarty.const.WWW_TOP}/nfo/{$result->guid}"
														title="View NFO" class="label label-primary"
														rel="nfo">NFO</a>{/if}
													<a class="label label-primary"
													   href="{$smarty.const.WWW_TOP}/browse/group?g={$result->group_name}"
													   title="Browse releases in {$result->group_name|replace:"alt.binaries":"a.b"}">Group</a>
													{if !empty($result->failed)}
														<span class="btn btn-default btn-xs"
															  title="This release has failed to download for some users">
															<i class="fa fa-thumbs-o-up"></i> {$result->grabs}
															Grab{if {$result->grabs} != 1}s{/if} / <i
																	class="fa fa-thumbs-o-down"></i> {$result->failed}
															Failed Download{if {$result->failed} > 1}s{/if}</span>
													{/if}
												</div>
												<div class="col-md-9 small-gutter-left table-responsive">
																<span class="release-title"><a class="text-muted"
																							   href="{$smarty.const.WWW_TOP}/xxx/?id={$result->id}">{$result->title|escape:"htmlall"}</a></span>
													<table class="data table table-striped responsive-utilities jambo-table">
														<tr>
															<td id="guid{$result->guid}">
																<label>
																	<input type="checkbox"
																		   class="flat"
																		   value="{$result->guid}"
																		   id="chksingle"/>
																</label>
																<span class="label label-primary">{if isset($catsplit[0])} {$catsplit[0]}{/if}</span>
																<span class="label label-danger">{if isset($catsplit[1])} {$catsplit[1]}{/if}</span>
																<span class="label label-primary">{$result->size|fsize_format:"MB"}</span>
																<span class="label label-primary">Posted {$result->postdate|timeago}
																	ago</span>
																<br>
																<div class="release-subtitle">{if $result->genre != ''}Genre: {$result->genre}, {/if}</div>
																<br>
																<div class="release-subtitle">{if $result->plot != ''}Plot: {$result->plot}, {/if}</div>
																<br>
																<div class="release-subtitle">{if $result->actors != ''}Cast: {$result->actors}, {/if}</div>
																<br>
																<div class="release-name text-muted"><a
																			href="{$smarty.const.WWW_TOP}/details/{$result->guid}">{$result->name|escape:"htmlall"}</a>
																</div>
																<div>
																	<a role="button" class="btn btn-default btn-xs"
																	   data-toggle="tooltip" data-placement="top" title
																	   data-original-title="Download NZB"
																	   href="{$smarty.const.WWW_TOP}/getnzb?id={$result->guid}"><i
																				class="fa fa-cloud-download"></i><span
																				class="badge"> {$result->grabs}
																			Grab{if $result->grabs != 1}s{/if}</span></a>
																	<a role="button" class="btn btn-default btn-xs"
																	   href="{$smarty.const.WWW_TOP}/details/{$result->guid}/#comments"><i
																				class="fa fa-comment-o"></i><span
																				class="badge"> {$result->comments}
																			Comment{if $result->comments != 1}s{/if}</span></a>
																	<span class="btn btn-hover btn-default btn-xs icon_cart text-muted"
																		  id="guid{$result->guid}"
																		  data-toggle="tooltip" data-placement="top"
																		  title
																		  data-original-title="Send to my download basket"><i
																				class="fa fa-shopping-basket"></i></span>
																	{if isset($sabintegrated) && $sabintegrated !=""}
																		<span class="btn btn-hover btn-default btn-xs icon_sab text-muted"
																			  id="guid{$result->guid}"
																			  data-toggle="tooltip" data-placement="top"
																			  title
																			  data-original-title="Send to my Queue"><i
																					class="fa fa-share"></i></span>
																	{/if}
																	{if !empty($result->failed)}
																		<span class="btn btn-default btn-xs"
																			  title="This release has failed to download for some users">
																	<i class="fa fa-thumbs-o-up"></i> {$result->grabs}
																			Grab{if {$result->grabs} != 1}s{/if}
																			/ <i
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
								</div>
								<!-- /Right -->
							</div>
							<hr>
							<!-- End Row -->
						{/if}
					{/foreach}
					<div class="row">
						<div class="col-md-8">
							<div class="nzb_multi_operations">
								View: <strong>Covers</strong> | <a
										href="{$smarty.const.WWW_TOP}/browse/xxx/{$categorytitle}">List</a><br/>
								With Selected:
								<div class="btn-group">
									<button type="button"
											class="nzb_multi_operations_download btn btn-sm btn-success"
											data-toggle="tooltip" data-placement="top" title
											data-original-title="Download NZBs">
										<i class="fa fa-cloud-download"></i></button>
									<button type="button"
											class="nzb_multi_operations_cart btn btn-sm btn-info"
											data-toggle="tooltip" data-placement="top" title
											data-original-title="Send to my Download Basket">
										<i class="fa fa-shopping-basket"></i></button>

									{if isset($sabintegrated) && $sabintegrated !=""}
										<button type="button"
												class="nzb_multi_operations_sab btn btn-sm btn-primary"
												data-toggle="tooltip" data-placement="top" title
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
						<div class="col-md-4">
							{$results->links()}
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</form>
