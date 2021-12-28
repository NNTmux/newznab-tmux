<div class="header">
	<h2>View > <strong>XXX Movie</strong></h2>
	<div class="breadcrumb-wrapper">
		<ol class="breadcrumb">
			<li><a href="{{url("{$site->home_link}")}}">Home</a></li>
			/ View XXX
		</ol>
	</div>
</div>
{if $results|@count > 0}
	<div class="box-body">
		{foreach $resultsadd as $result}
			<div class="well well small">
				<div id="moviefull" style="min-height:340px;">
					{if $result->cover == 1}
						<img class="float-right" style="margin-right:50px; max-height:278px;"
							 alt="{$result->title|escape:"htmlall"} Logo"
							 src="{{url("/covers/xxx/{$result->id}-cover.jpg")}}"/>
					{else}
						<img class="float-right" style="margin-right:50px; max-height:278px;"
							 alt="{$result->title|escape:"htmlall"} Logo"
							 src="{{asset("/images/nomoviecover.jpg")}}"/>
					{/if}
					<span class="h1" style="display:inline;">{$result->title|escape:"htmlall"}</span>
					<h4>{if $result->genre != ''}{$result->genre|replace:"|":" / "}{/if}</h4>
					{if $result->tagline != ''}
						<p class="lead" style="margin-right:300px;">"{$result->tagline|escape:"htmlall"}"</p>
					{/if}
					<dl style="margin-right:300px;">
						{if isset($result->plot) && $result->plot != ''}
							<dt>Plot</dt>
							<dd>{$result->plot|escape:"htmlall"}</dd>
						{/if}
						{if isset($result->rating) && $result->rating != ''}
							<dt>Rating</dt>
							<dd>{$result->rating}
							/10 {if isset($result->ratingcount) && $result->ratingcount != ''}({$result->ratingcount|number_format} votes)</dd>{/if}
						{/if}
						{if isset($result->director) && $result->director != ''}
							<dt>Director</dt>
							<dd>{$result->director|replace:"|":", "}</dd>
						{/if}
						{if isset($result->actors) && $result->actors != ''}
							<dt>Actors</dt>
							<dd>{$result->actors|replace:"|":", "}</dd>
						{/if}
					</dl>
				</div>
			</div>
			{{Form::open(['id' => 'nzb_multi_operations_form', 'method' => 'get'])}}
				<div class="well well-sm">
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

							{if isset($sabintegrated) && $sabintegrated !=""}
								<button type="button"
										class="nzb_multi_operations_sab btn btn-sm btn-success"
										data-bs-toggle="tooltip" data-bs-placement="top" title
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
						<div class="col-lg-12 col-sm-12 col-12">
							<div class="card card-default">
								<div class="card-body pagination2">
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
										{assign var="msplits" value=","|explode:$result->grp_release_id}
										{assign var="mguid" value=","|explode:$result->grp_release_guid}
										{assign var="mnfo" value=","|explode:$result->grp_release_nfoid}
										{assign var="mgrp" value=","|explode:$result->grp_release_grpname}
										{assign var="mname" value="#"|explode:$result->grp_release_name}
										{assign var="mpostdate" value=","|explode:$result->grp_release_postdate}
										{assign var="msize" value=","|explode:$result->grp_release_size}
										{assign var="mtotalparts" value=","|explode:$result->grp_release_totalparts}
										{assign var="mcomments" value=","|explode:$result->grp_release_comments}
										{assign var="mgrabs" value=","|explode:$result->grp_release_grabs}
										{assign var="mpass" value=","|explode:$result->grp_release_password}
										{assign var="minnerfiles" value=","|explode:$result->grp_rarinnerfilecount}
										{assign var="mhaspreview" value=","|explode:$result->grp_haspreview}
										{assign var="mcatname" value=","|explode:$result->grp_release_catname}
										{foreach $msplits as $m}
											<tr class="{cycle values=",alt"}" id="guid{$mguid[$m@index]}">
												<td><input id="guid{$mguid[$m@index]}"
														   type="checkbox"
														   class="flat"
														   value="{$mguid[$m@index]}"/></td>
												<td class="item">
													<a title="View details"
													   href="{{url("/details/{$mguid[$m@index]}")}}">{$mname[$m@index]|escape:"htmlall"|replace:".":" "}</a>
													<br/>
													<span class="badge bg-info">{$mgrabs[$m@index]}
														grab{if $mgrabs[$m@index] != 1}s{/if}</span>
													{if isset($mnfo[$m@index]) && $mnfo[$m@index] > 0}<span><a
																href="{{url("/nfo/{$mguid[$m@index]}")}}"
																class="modal_nfo badge bg-info text-muted">NFO</a>
														</span>{/if}
													{if $mpass[$m@index] == 2}
														<i class="fa fa-lock"></i>
													{elseif $mpass[$m@index] == 1}
														<i class="fa fa-lock"></i>
													{/if}
												</td>
												<td class="less"><span
															class="badge bg-info">{$mcatname[$m@index]}</span>
												</td>
												<td class="less mid"
													title="{$mpostdate[$m@index]}">{$mpostdate[$m@index]|timeago}</td>
												<td class="less right">{$msize[$m@index]|filesize}</td>
												<td>
													<a href="{{url("/getnzb?id={$mguid[$m@index]}")}}" class="icon_nzb text-muted"><i
																class="fa fa-cloud-download text-muted"
																data-bs-toggle="tooltip" data-bs-placement="top" title
																data-original-title="Download NZB"></i></a>
													<a href="{{url("/details/{$mguid[$m@index]}/#comments")}}"><i
																class="fa fa-comments-o text-muted"
																data-bs-toggle="tooltip" data-bs-placement="top" title
																data-original-title="Comments"></i></a>
													<a <a href="{{url("/cart/add?id={$mguid[$m@index]}")}}" target="_blank"><i
																class="text-muted fa fa-shopping-basket" data-bs-toggle="tooltip"
																data-bs-placement="top" title
																data-original-title="Send to my download basket"></i></a>
													{if isset($sabintegrated) && $sabintegrated !=""}
														<a href="#">
															<i	id="guid{$mguid[$m@index]}"
																  class="icon_sab text-muted fa fa-share"
																  data-bs-toggle="tooltip"
																  data-bs-placement="top" title
																  data-original-title="Send to my Queue">
															</i>
														</a>
													{/if}
													{if $weHasVortex}
														<a href="#" class="icon_vortex text-muted"><i
																	class="fa fa-share" data-bs-toggle="tooltip" data-bs-placement="top"
																	title data-original-title="Send to NZBVortex"></i></a>
													{/if}
												</td>
											</tr>
										{/foreach}
									</table>
									<hr>
									{if $results|@count > 10}
										<div class="row">
											<div class="col-md-4">
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

														{if isset($sabintegrated) && $sabintegrated !=""}
															<button type="button"
																	class="nzb_multi_operations_sab btn btn-sm btn-light"
																	data-bs-toggle="tooltip" data-bs-placement="top" title
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
									{/if}
								</div>
							</div>
			{{Form::close()}}
		{/foreach}
	</div>
{/if}
