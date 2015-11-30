<h2>Browse Games</h2>
<div class="well well-small">
	<center>
		<form class="form-inline" name="browseby" action="console" style="margin:0;">
			<i class="fa fa-font fa-midt"></i>
			<input class="input input-medium" id="title" type="text" name="title" value="{$title}" placeholder="Title" />
			<i class="fa fa-desktop fa-midt"></i>
			<input class="input input-medium" id="platform" type="text" name="platform" value="{$platform}" placeholder="Platform" />
			<i class="fa fa-inbox fa-midt"></i>
			<select class="input input-small" id="genre" name="genre">
				<option class="grouping" value=""></option>
				{foreach from=$genres item=gen}
					<option {if $gen.id == $genre}selected="selected"{/if} value="{$gen.id}">{$gen.title}</option>
				{/foreach}
			</select>
			<i class="fa fa-flag fa-midt"></i>
			<select class="input input-small" id="category" name="t">
				<option class="grouping" value="1000"></option>
				{foreach from=$catlist item=ct}
					<option {if $ct.id==$category}selected="selected"{/if} value="{$ct.id}">{$ct.title}</option>
				{/foreach}
			</select>
			<input class="btn btn-success" type="submit" value="Go" />
		</form>
	</center>
</div>
{$site->adbrowse}
{if $results|@count > 0}
<form id="nzb_multi_operations_form" action="get">
	<div class="well well-small">
		<div class="nzb_multi_operations">
			<table width="100%">
				<tr>
					<td width="30%">
						With Selected:
						<div class="btn-group">
							<input type="button" class="nzb_multi_operations_download btn btn-small btn-success" value="Download NZBs" />
							<input type="button" class="nzb_multi_operations_cart btn btn-small btn-info" value="Add to Cart" />
							{if $sabintegrated}<input type="button" class="nzb_multi_operations_sab btn btn-small btn-primary" value="Send to queue" />{/if}
							{if isset($nzbgetintegrated)}<input type="button" class="nzb_multi_operations_nzbget btn btn-small btn-primary" value="Send to NZBGet" />{/if}
						</div>
					</td>
					<td width="50%">
						<center>
							{$pager}
						</center>
					</td>
					<td width="20%">
						<div class="pull-right">
							{if $isadmin}
								Admin:
								<div class="btn-group">
									<input type="button" class="nzb_multi_operations_edit btn btn-small btn-warning" value="Edit" />
									<input type="button" class="nzb_multi_operations_delete btn btn-small btn-danger" value="Delete" />
								</div>
								&nbsp;
							{/if}
							<a href="{$smarty.const.WWW_TOP}/browse?t={$category}"><i class="fa fa-align-justify"></i></a>
							&nbsp;
						</div>
					</td>
				</tr>
			</table>
		</div>
	</div>
	<table style="width:100%;" class="data highlight icons table table-striped" id="coverstable">
		<tr>
			<th width="130">
				<input type="checkbox" class="nzb_check_all" />
			</th>
			<th width="140" >title<br/>
				<a title="Sort Descending" href="{$orderbytitle_desc}">
					<i class="fa fa-caret-down"></i>
				</a>
				<a title="Sort Ascending" href="{$orderbytitle_asc}">
					<i class="fa fa-caret-up"></i>
				</a>
			</th>
			<th>genre<br/>
				<a title="Sort Descending" href="{$orderbygenre_desc}">
					<i class="fa fa-caret-down"></i>
				</a>
				<a title="Sort Ascending" href="{$orderbygenre_asc}">
					<i class="fa fa-caret-up"></i>
				</a>
			</th>
			<th>release date<br/>
				<a title="Sort Descending" href="{$orderbyreleasedate_desc}">
					<i class="fa fa-caret-down"></i>
				</a>
				<a title="Sort Ascending" href="{$orderbyreleasedate_asc}">
					<i class="fa fa-caret-up"></i>
				</a>
			</th>
			<th>posted<br/>
				<a title="Sort Descending" href="{$orderbyposted_desc}">
					<i class="fa fa-caret-down"></i>
				</a>
				<a title="Sort Ascending" href="{$orderbyposted_asc}">
					<i class="fa fa-caret-up"></i>
				</a>
			</th>
			<th>size<br/>
				<a title="Sort Descending" href="{$orderbysize_desc}">
					<i class="fa fa-caret-down"></i>
				</a>
				<a title="Sort Ascending" href="{$orderbysize_asc}">
					<i class="fa fa-caret-up"></i>
				</a>
			</th>
			<th>files<br/>
				<a title="Sort Descending" href="{$orderbyfiles_desc}">
					<i class="fa fa-caret-down"></i>
				</a>
				<a title="Sort Ascending" href="{$orderbyfiles_asc}">
					<i class="fa fa-caret-up"></i>
				</a>
			</th>
			<th>stats<br/>
				<a title="Sort Descending" href="{$orderbystats_desc}">
					<i class="fa fa-caret-down"></i>
				</a>
				<a title="Sort Ascending" href="{$orderbystats_asc}">
					<i class="fa fa-caret-up"></i>
				</a>
			</th>
		</tr>
					{foreach from=$results item=result}
						{assign var="msplits" value=","|explode:$result.grp_release_id}
						{assign var="mguid" value=","|explode:$result.grp_release_guid}
						{assign var="mnfo" value=","|explode:$result.grp_release_nfoid}
						{assign var="mgrp" value=","|explode:$result.grp_release_grpname}
						{assign var="mname" value="#"|explode:$result.grp_release_name}
						{assign var="mpostdate" value=","|explode:$result.grp_release_postdate}
						{assign var="msize" value=","|explode:$result.grp_release_size}
						{assign var="mtotalparts" value=","|explode:$result.grp_release_totalparts}
						{assign var="mcomments" value=","|explode:$result.grp_release_comments}
						{assign var="mgrabs" value=","|explode:$result.grp_release_grabs}
						{assign var="mfailed" value=","|explode:$result.grp_release_failed}
						{assign var="mpass" value=","|explode:$result.grp_release_password}
						{assign var="minnerfiles" value=","|explode:$result.grp_rarinnerfilecount}
						{assign var="mhaspreview" value=","|explode:$result.grp_haspreview}
						{foreach from=$msplits item=m name=loop}
							{if $smarty.foreach.loop.first}
								<div class="panel panel-default">
									<div class="panel-body">
										<div class="row">
											<td colspan="2">
												<a title="View details"
												   href="{$smarty.const.WWW_TOP}/details/{$mguid[$m@index]}">
													<img src="{$smarty.const.WWW_TOP}/covers/games/{if $result.cover == 1}{$result.gamesinfo_id}.jpg{else}no-cover.jpg{/if}"
														 width="140" border="0"
														 alt="{$result.title|escape:"htmlall"}"/>{if $mfailed[$m@index] > 0} <i class="fa fa-exclamation-circle" style="color: red" title="This release has failed to download for some users"></i>{/if}
												</a>
												{if $result.classused == "gb"}<a class="label label-default"
																				 target="_blank"
																				 href="{$site->dereferrer_link}{$result.url}"
																				 name="giantbomb{$result.gamesinfo_id}"
																				 title="View GiantBomb page">
														GiantBomb</a>{/if}
												{if $result.classused == "steam"}<a class="label label-default"
																					target="_blank"
																					href="{$site->dereferrer_link}{$result.url|escape:"htmlall"}"
																					name="steam{$result.gamesinfo_id}"
																					title="View Steam page">
														Steam</a>{/if}
												{if $result.classused == "gl"}<a class="label label-default"
																				 target="_blank"
																				 href="{$site->dereferrer_link}{$result.url|escape:"htmlall"}"
																				 name="greenlight{$result.gamesinfo_id}"
																				 title="View greenlight page">
														GreenLight</a>{/if}
												{if $result.classused == "desura"}<a class="label label-default"
																					 target="_blank"
																					 href="{$site->dereferrer_link}{$result.url|escape:"htmlall"}"
																					 name="desura{$result.gamesinfo_id}"
																					 title="View Desura page">
														Desura</a>{/if}
												{if $result.nfoid > 0}<a
													href="{$smarty.const.WWW_TOP}/nfo/{$mguid[$m@index]}|escape:"htmlall"}"
													title="View NFO" class="label label-default" rel="nfo">
														NFO</a>{/if}
												<a class="label label-default"
												   href="{$smarty.const.WWW_TOP}/browse?g={$result.group_name}"
												   title="Browse releases in {$result.group_name|replace:"alt.binaries":"a.b"}">Group</a>
												{if $mfailed[$m@index] > 0}
													<span class="btn btn-default btn-xs" title="This release has failed to download for some users">
														<i class ="fa fa-thumbs-o-up"></i> {$mgrabs[$m@index]} Grab{if {$mgrabs[$m@index]} != 1}s{/if} / <i class ="fa fa-thumbs-o-down"></i> {$mfailed[$m@index]} Failed Download{if {$mfailed[$m@index]} != 1}s{/if}</span>												{/if}
											</td>
											<td colspan="10">
												<h4><a title="View details"
													   href="{$smarty.const.WWW_TOP}/details/{$mguid[$m@index]}">{$result.title|escape:"htmlall"}</a>
													(<a class="title" title="{$result.year}"
														href="{$smarty.const.WWW_TOP}/games?year={$result.year}">{$result.year}</a>)
												</h4>
												<table class="data table table-responsive">
													<tr>
														<td id="guid{$mguid[$m@index]}">
															<label>
															<input type="checkbox"
																   class="nzb_check"
																   value="{$mguid[$m@index]}" id="chksingle"/>
															</label>
															<span class="label label-default">{$msize[$m@index]|fsize_format:"MB"}</span>
																	<span class="label label-default">Posted {$mpostdate[$m@index]|timeago}
																		ago</span>
															{if isset($isadmin)}<a class="label label-warning"
																				   href="{$smarty.const.WWW_TOP}/admin/release-edit.php?id={$result.grp_release_id}&amp;from={$smarty.server.REQUEST_URI}"
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
															{if isset($result.publisher) && $result.publisher != ""}
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
																{$result.review|stripslashes|escape:'htmlall'}
																<br/>
															{/if}
															<div>
																<a role="button" class="btn btn-default btn-xs"
																   href="{$smarty.const.WWW_TOP}/getnzb/{$mguid[$m@index]}|escape:"htmlall"}"><i
																			class="fa fa-cloud-download"></i><span
																			class="badge">{$mgrabs[$m@index]}
																		Grab{if $mgrabs[$m@index] != 1}s{/if}</span></a>
																<a role="button" class="btn btn-default btn-xs"
																   href="{$smarty.const.WWW_TOP}/details/{$mguid[$m@index]}#comments"><i
																			class="fa fa-comment-o"></i><span
																			class="badge">{$mcomments[$m@index]}
																		Comment{if $mcomments[$m@index] != 1}s{/if}</span></a>
																<span class="btn btn-hover btn-default btn-xs icon icon_cart text-muted"
																	  title="Add to Cart"><i
																			class="fa fa-shopping-basket"></i></span>
																{if isset($sabintegrated)}
																	<span class="btn btn-hover btn-default btn-xs icon icon_sab text-muted"
																		  title="Send to my Queue"><i
																				class="fa fa-share"></i></span>
																{/if}
															</div>
														</td>
													</tr>
												</table>
											</td>
										</div>
									</div>
								</div>
							{/if}
						{/foreach}
					{/foreach}
		</table>
					<hr>
					<div class="row">
						<td colspan="8">
							<div class="nzb_multi_operations">
								View: <strong>Covers</strong> | <a
										href="{$smarty.const.WWW_TOP}/browse?t={$category}">List</a><br/>
								Check all: <input type="checkbox" class="nntmux_check_all"/> <br/>
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
						</td>
						<td colspan="4">
							{$pager}
						</td>
					</div>
</form>
{/if}
