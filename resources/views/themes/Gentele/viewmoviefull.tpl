<div class="header">
	<h2>View > <strong>Movie</strong></h2>
	<div class="breadcrumb-wrapper">
		<ol class="breadcrumb">
			<li><a href="{$smarty.const.WWW_TOP}{$site->home_link}">Home</a></li>
			/ View Movie
		</ol>
	</div>
</div>
{if $results|@count > 0}
	<div class="box-body">
		{foreach $results as $result}
			<div class="well well-sm">
				<div id="moviefull" style="min-height:340px;">
					{if $result->cover == 1}
						<img class="pull-right" style="margin-right:50px; max-height:278px;"
							 alt="{$result->title|escape:"htmlall"} Logo"
							 src="{$smarty.const.WWW_TOP}/covers/movies/{$result->imdbid}-cover.jpg"/>
					{else}
						<img class="pull-right" style="margin-right:50px; max-height:278px;"
							 alt="{$result->title|escape:"htmlall"} Logo"
							 src="{$smarty.const.WWW_ASSETS}/images/nomoviecover.jpg"/>
					{/if}
					<span class="h1" style="display:inline;">{$result->title|escape:"htmlall"} ({$result->year})</span>
					<a class="btn btn-transparent btn-primary" target="_blank"
					   href="{$site->dereferrer_link}http://www.imdb.com/title/tt{$result->imdbid}/"
					   name="imdb{$result->imdbid}" title="View IMDB page">IMDB</a>
					<a class="btn btn-transparent btn-primary" target="_blank"
					   href="{$site->dereferrer_link}http://trakt.tv/search/imdb/tt{$result->imdbid}/"
					   name="trakt{$result->imdbid}" title="View Trakt page" rel="trakt">TRAKT</a>
					<h4>{if $result->genre != ''}{$result->genre|replace:"|":" / "}{/if}</h4>
					{if $result->tagline != ''}
						<p class="lead" style="margin-right:300px;">"{$result->tagline|escape:"htmlall"}"</p>
					{/if}
					<dl style="margin-right:300px;">
						{if $result->plot != ''}
							<dt>Plot</dt>
							<dd>{$result->plot|escape:"htmlall"}</dd>
						{/if}
						{if $result->rating != ''}
							<dt>Rating</dt>
							<dd>{$result->rating}
							/10 {if isset($result->ratingcount) && $result->ratingcount != ''}({$result->ratingcount|number_format} votes)</dd>{/if}
						{/if}
						{if $result->director != ''}
							<dt>Director</dt>
							<dd>{$result->director|replace:"|":", "}</dd>
						{/if}
						{if $result->actors != ''}
							<dt>Actors</dt>
							<dd>{$result->actors|replace:"|":", "}</dd>
						{/if}
					</dl>
				</div>
			</div>
			<form id="nzb_multi_operations_form" action="get">
				<div class="well well-sm">
					<div class="nzb_multi_operations">
						{if isset($section) && $section != ''}View:
							<a href="{$smarty.const.WWW_TOP}/{$section}?t={$category}">Covers</a>
							|
							<b>List</b>
							<br/>
						{/if}
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
					<div class="row">
						<div class="col-lg-12 col-sm-12 col-xs-12">
							<div class="panel panel-default">
								<div class="panel-body pagination2">
									<table style="width:100%;"
										   class="data table table-striped responsive-utilities jambo-table">
										<tr>
											<th><input id="check-all" type="checkbox" class="flat-all"/> Select All</th>
											<th>Name</th>
											<th>Category</th>
											<th>Posted</th>
											<th>Size</th>
											<th>Action</th>
										</tr>
										<tr class="{cycle values=",alt"}" id="guid{$result->guid}">
											<td><input id="guid{$result->guid}"
													   type="checkbox"
													   class="flat"
													   value="{$result->guid}"/></td>
											<td class="item">
												<a title="View details"
												   href="{$smarty.const.WWW_TOP}/details/{$result->guid}">{$result->searchname|escape:"htmlall"|replace:".":" "}</a>
												<br/>
												<span class="label label-primary">{$result->grabs}
													grab{if $result->grabs != 1}s{/if}</span>
												{if isset($result->nfoid) && $result->nfoid > 0}
													<span><a
															href="{$smarty.const.WWW_TOP}/nfo/{$result->guid}"
															class="modal_nfo label label-primary text-muted">NFO</a>
													</span>{/if}
												{if $result->password == 2}
													<i class="fa fa-icon-lock"></i>
												{elseif $result->password == 1}
													<i class="fa fa-icon-lock"></i>
												{/if}
											</td>
											<td class="less"><span
														class="label label-primary">{$result->catname}</span>
											</td>
											<td class="less mid"
												title="{$result->postdate}">{$result->postdate|timeago}</td>
											<td class="less right">{$result->size|fsize_format:"MB"}</td>
											<td>
												<a href="{$smarty.const.WWW_TOP}/getnzb?id={$result->guid}"
												   class="icon_nzb text-muted"><i
															class="fa fa-cloud-download text-muted"
															data-toggle="tooltip" data-placement="top" title
															data-original-title="Download NZB"></i></a>
												<a href="{$smarty.const.WWW_TOP}/details/{$result->guid}/#comments"><i
															class="fa fa-comments-o text-muted"
															data-toggle="tooltip" data-placement="top" title
															data-original-title="Comments"></i></a>
												<a href="#"><i
															id="guid{$result->guid}"
															class="icon_cart text-muted fa fa-shopping-basket"
															data-toggle="tooltip"
															data-placement="top" title
															data-original-title="Send to my download basket"></i></a>
												{if isset($sabintegrated) && $sabintegrated !=""}
													<a href="#">
														<i id="guid{$result->guid}"
														   class="icon_sab text-muted fa fa-share"
														   data-toggle="tooltip"
														   data-placement="top" title
														   data-original-title="Send to my Queue">
														</i>
													</a>
												{/if}
												{if $weHasVortex}
													<a href="#" class="icon_vortex text-muted"><i
																class="fa fa-share" data-toggle="tooltip"
																data-placement="top"
																title data-original-title="Send to NZBVortex"></i></a>
												{/if}
											</td>
										</tr>
									</table>
									<hr>
									{if $results|@count > 10}
										<div class="row">
											<div class="col-md-8">
												<div class="nzb_multi_operations">
													{if isset($section) && $section != ''}View:
														<a href="{$smarty.const.WWW_TOP}/{$section}?t={$category}">Covers</a>
														|
														<b>List</b>
														<br/>
													{/if}
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
											{if    count($results) > 0}
												<div class="col-md-4">
													{$results->links()}
												</div>
											{/if}
										</div>
									{/if}
								</div>
							</div>
			</form>
		{/foreach}
	</div>
{/if}
