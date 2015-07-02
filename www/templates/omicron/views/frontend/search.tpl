<div class="header">
	<h2>newznab-tmux > <strong>Search</strong></h2>

	<div class="breadcrumb-wrapper">
		<ol class="breadcrumb">
			<li><a href="{$smarty.const.WWW_TOP}{$site->home_link}">Home</a></li>
			/ Search
		</ol>
	</div>
</div>

<div style="display:none;text-align:right;">
	<a href="#"
	   onclick="if(jQuery(this).text()=='Advanced Search')jQuery(this).text('Basic Search');else jQuery(this).text('Advanced Search');jQuery('#sbasic,#sadvanced').toggle();return false;">{if $sadvanced}Basic{else}Advanced{/if}
		Search</a>
</div>
<div class="well well-sm">
	<form method="get" action="{$smarty.const.WWW_TOP}/search">
		<div id="sbasic" class="row">
			<div class="col-md-6">
				<input id="search" class="form-control" maxlength="50" name="search" value="{$search|escape:'html'}"
					   type="search" placeholder="What are you looking for?"/>
			</div>
			<div class="col-md-6">
				<input type="hidden" name="t" value="{if $category[0]!=""}{$category[0]}{else}-1{/if}" id="search_cat"/>
				<input type="hidden" name="search_type" value="basic" id="search_type"/>
				<input id="search_search_button" class="btn btn-primary" type="submit" value="Search"/>
			</div>
		</div>
	</form>
</div>

<form method="get" action="{$smarty.const.WWW_TOP}/search">
	<div id="sadvanced" {if not $sadvanced}style="display:none"{/if}>
		<center>
			<table class="data">
				<tr>
					<th><label for="searchadvr">Release Name</label>:</th>
					<td><input id="searchadvr" name="searchadvr" value="{$searchadvr|escape:'html'}" type="text"/></td>
				</tr>
				<tr>
					<th><label for="searchadvposter">Poster</label>:</th>
					<td><input id="searchadvposter" name="searchadvposter" value="{$searchadvposter|escape:'html'}"
							   type="text"/></td>
				</tr>
				<tr>
					<th><label for="searchadvgroups">Group</label>:</th>
					<td>{html_options id="searchadvgroups" name=searchadvgroups options=$grouplist selected=$selectedgroup}</td>
				</tr>
				<tr>
					<th><label for="searchadvcat">Category</label>:</th>
					<td>{html_options id="searchadvcat" name=searchadvcat options=$catlist selected=$selectedcat}</td>
				</tr>
				<tr>
					<th><label for="searchadvsizefrom">Size Between</label>:</th>
					<td>
						{html_options id="searchadvsizefrom" name=searchadvsizefrom options=$sizelist selected=$selectedsizefrom}
						and {html_options id="searchadvsizeto" name=searchadvsizeto options=$sizelist selected=$selectedsizeto}
					</td>
				</tr>
				<tr>
					<th></th>
					<td>
						<input type="hidden" name="search_type" value="adv" id="search_type"/>
						<input id="search_adv_button" type="submit" value="search"/>
					</td>
				</tr>
			</table>
		</center>
	</div>
</form>

{if $results|@count == 0 && $search != ""}
	<div class="alert alert-block">
		<h4>No result!</h4>
		Your search - <strong>{$search|escape:'htmlall'}</strong> - did not match any releases.
		<br/><br/>
		Suggestions:
		<br/><br/>
		<ul>
			<li>Make sure all words are spelled correctly.</li>
			<li>Try different keywords.</li>
			<li>Try more general keywords.</li>
			<li>Try fewer keywords.</li>
		</ul>
	</div>
{elseif $search == ""}
{else}

	{$site->adbrowse}
	<div class="row">
		<div class="box col-md-12">
			<div class="box-content">
				<form style="padding-top:10px;" id="nzb_multi_operations_form" method="get"
					  action="{$smarty.const.WWW_TOP}/search">
					<div class="row">
						<div class="col-xlg-12 portlets">
							<div class="panel">
								<div class="panel-content pagination2">
									<form id="nzb_multi_operations_form" action="get">
										<div class="row">
											<div class="col-md-8">
												{if isset($shows)}
													<p>
														<a href="{$smarty.const.WWW_TOP}/series"
														   title="View available TV series">Series List</a> |
														<a title="Manage your shows"
														   href="{$smarty.const.WWW_TOP}/myshows">Manage My Shows</a> |
														<a title="All releases in your shows as an RSS feed"
														   href="{$smarty.const.WWW_TOP}/rss?t=-3&amp;dl=1&amp;i={$userdata.id}&amp;r={$userdata.rsstoken}">Rss
															Feed</a>
													</p>
												{/if}
												<div class="nzb_multi_operations">
													{if isset($section) && $section != ''}View:
														<a href="{$smarty.const.WWW_TOP}/{$section}?t={$category}">Covers</a>
														|
														<b>List</b>
														<br/>
													{/if}
													With Selected:
													<div class="btn-group">
														<input type="button"
															   class="nzb_multi_operations_download btn btn-sm btn-success"
															   value="Download NZBs"/>
														<input type="button"
															   class="nzb_multi_operations_cart btn btn-sm btn-info"
															   value="Add to Cart"/>
														{if isset($sabintegrated)}
															<input type="button"
																   class="nzb_multi_operations_sab btn btn-sm btn-primary"
																   value="Send to Queue"/>
														{/if}
														{if isset($nzbgetintegrated)}
															<input type="button"
																   class="nzb_multi_operations_nzbget btn btn-sm btn-primary"
																   value="Send to NZBGet"/>
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
												{$pager}
											</div>
										</div>
										<table class="data table table-condensed table-striped responsive"
											   cellspacing="0" cellpadding="0" style="table-layout: auto;">
											<thead>
											<tr>
												<th><input id="chkSelectAll" type="checkbox" class="fa fa-check-square-o nzb_check_all"/>
												</th>
												<th>Name
													<a title="Sort Descending" href="{$orderbyname_desc}">
														<i class="fa-icon-caret-down text-muted"> </i>
													</a>
													<a title="Sort Ascending" href="{$orderbyname_asc}">
														<i class="fa-icon-caret-up text-muted"> </i>
													</a>
												</th>
												<th>Category</th>
												<th>Posted</th>
												<th>Size</th>
												<th>Action</th>
											</tr>
											</thead>
											<tbody>
											{foreach from=$results item=result}
												<tr id="guid{$result.guid}">
													<td class="check"><input id="chk{$result.guid|substr:0:7}"
																			 type="checkbox" class="fa fa-check-square-o nzb_check"
																			 name="id[]" value="{$result.guid}"/></td>
													<td>
														<a href="{$smarty.const.WWW_TOP}/details/{$result.guid}/{$result.searchname|escape:"seourl"}">{$result.searchname|escape:"htmlall"|replace:".":" "}</a>
														<br/>
														<span class="label label-default">{$result.grabs}
															Grab{if $result.grabs != 1}s{/if}</span> {if $result.nfoid > 0}
														<span class="label label-default"><a
																	href="{$smarty.const.WWW_TOP}/nfo/{$result.guid}"
																	class="text-muted">NFO</a>
									</span>
													</td>{/if}
													<td><span class="label label-default">{$result.category_name}</span>
													</td>
													<td>{$result.postdate|timeago}</td>
													<td>{$result.size|fsize_format:"MB"}</td>
													<td>
														<a href="{$smarty.const.WWW_TOP}/getnzb/{$result.guid}/{$result.searchname|escape:"url"}"><i
																	class="fa fa-download text-muted"></i></a>
														<a href="{$smarty.const.WWW_TOP}/details/{$result.guid}/#comments"><i
																	class="fa fa-comments-o text-muted"></i></a>
														<a href="#" class="icon_cart text-muted"><i
																	class="fa fa-shopping-cart"></i></a>
														{if isset($sabintegrated)}
															<a href="#" class="icon_sab text-muted"><i
																		class="fa fa-send-o"
																		title="Send to my Queue"></i></a>
														{/if}
													</td>
												</tr>
											{/foreach}
											</tbody>
										</table>

										{if $results|@count > 10}
											<div class="row">
												<div class="col-md-8">
													<div class="nzb_multi_operations">
														<small>With Selected:</small>
														<div class="btn-group">
															<input type="button"
																   class="nzb_multi_operations_download btn btn-sm btn-success"
																   value="Download NZBs"/>
															<input type="button"
																   class="nzb_multi_operations_cart btn btn-sm btn-info"
																   value="Add to Cart"/>
															{if isset($sabintegrated)}
																<input type="button"
																	   class="nzb_multi_operations_sab btn btn-sm btn-primary"
																	   value="Send to Queue"/>
															{/if}
															{if isset($nzbgetintegrated)}
																<input type="button"
																	   class="nzb_multi_operations_nzbget btn btn-sm btn-primary"
																	   value="Send to NZBGet"/>
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
													{$pager}
												</div>
											</div>
										{/if}

								</div>
							</div>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
{/if}