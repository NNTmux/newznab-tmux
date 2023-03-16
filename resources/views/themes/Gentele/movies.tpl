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
	<div class="box-body"
	<div class="row">
		<div class="col-lg-12 col-sm-12 col-12">
			<div class="card card-default">
				<div class="card-body">
					<div class="row">
						<div class="col-md-4">
                            {{Form::open(['id' => 'nzb_multi_operations_form', 'method' => 'get'])}}
							<div class="nzb_multi_operations">
								View: <strong>Covers</strong> | <a
										href="{{url("/browse/Movies/{$categorytitle}")}}">List</a><br/>
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
						</div>
                        {{Form::close()}}
						<div class="col-md-8">
							{$results->onEachSide(5)->links()}
						</div>
					</div>
					<hr>
					{foreach $results as $result}
						<!-- Iteratie: {counter} -->
						{if isset($result->category_name)}
							{assign var="catnamesplit" value=">"|explode:$result->category_name}
						{/if}
						{if $result@iteration is odd by 1}
							<!-- Begin Row -->
							<div class="row">
								<!-- Left -->
								<div class="col-md-6 small-gutter-right movie-height">
									<div class="card card-default">
										<div class="card-body">
											<div class="row small-gutter-left">
												<div class="col-md-3 small-gutter-left">
													{foreach $result->languages as $movielanguage}
													{release_flag($movielanguage, browse)}
													{/foreach}
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
													{assign var="mfailed" value=","|explode:$result->grp_release_failed}
													{assign var="mpass" value=","|explode:$result->grp_release_password}
													{assign var="minnerfiles" value=","|explode:$result->grp_rarinnerfilecount}
													{assign var="mhaspreview" value=","|explode:$result->grp_haspreview}
													{foreach $msplits as $m}
													{if $m@first}
													<a href="{{url("/Movies?imdb={$result->imdbid}")}}"><img
																class="cover shadow img-fluid rounded"
																src="{if isset($result->cover) && $result->cover == 1}{{url("/covers/movies/{$result->imdbid}-cover.jpg")}}{else}{{asset("/assets/images/no-cover.png")}}{/if}"
																width="140" border="0"
																alt="{$result->title|escape:"htmlall"}"/> {if !empty($mfailed[$m@index])}
															<i class="fa fa-exclamation-circle" style="color: red"
															   title="This release has failed for some users"></i>
														{/if}</a>
													<a target="_blank"
													   href="{$site->dereferrer_link}http://www.imdb.com/title/tt{$result->imdbid}/"
													   name="imdb{$result->imdbid}" title="View IMDB page"
													   class="badge bg-info" rel="imdb">IMDB</a>
													<a target="_blank"
													   href="{$site->dereferrer_link}http://trakt.tv/search/imdb/tt{$result->imdbid}/"
													   name="trakt{$result->imdbid}" title="View Trakt page"
													   class="badge bg-info" rel="trakt">TRAKT</a>
                                                    {if (!empty($result->tmdbid))}
                                                        <a class="badge bg-info" rel="tmdb" target="_blank"
                                                           href="{$site->dereferrer_link}http://www.themoviedb.org/movie/{$result->tmdbid}"
                                                           name="tmdb{$result->tmdbid}" title="View TheMovieDB page">TMDB</a>
                                                    {/if}
													{if $mnfo[$m@index] > 0}<a
															href="{{url("/nfo/{$mguid[$m@index]}")}}"
															title="View NFO" class="modal_nfo badge bg-info"
															rel="nfo">NFO</a>{/if}
													<a class="badge bg-info"
													   href="{{url("/browse/group?g={$mgrp[$m@index]}")}}"
													   title="Browse releases in {$mgrp[$m@index]|replace:"alt.binaries":"a.b"}">Group</a>
													<a class="badge bg-info" href="{{url("/mymovies?id=add&imdb={$result->imdbid}&from={$smarty.server.REQUEST_URI|escape:"url"}")}}" rel="add" name="movies{$result->imdbid}" title="Add to My Movies">Add</a>
												</div>
												<div class="col-md-9 small-gutter-left table-responsive">
																<span class="release-title"><a class="text-muted"
																							   href="{{url("/Movies?imdb={$result->imdbid}")}}">{$result->title|escape:"htmlall"}</a></span>
													<div class="release-subtitle">{if $result->genre != ''}
															<b>Genre: </b>
															{$result->genre}, {/if}</div>
													<div class="release-subtitle">{if $result->plot != ''}{$result->plot} {/if}</div>
													<div class="release-subtitle">{if $result->director != ''}<b>Director: </b>{$result->director} {/if}
													</div>
													<div class="release-subtitle">{if $result->actors != ''}
															<b>Starring: </b>
															{$result->actors} {/if}</div>
													<table class="data table table-striped responsive-utilities jambo-table">
														<tr>
															<td id="guid{$mguid[$m@index]}">
																<label>
																	<input type="checkbox"
																		   class="flat"
																		   value="{$mguid[$m@index]}"
																		   id="chksingle"/>
																</label>
																<span class="badge bg-info">{if isset($catsplit[0])} {$catsplit[0]}{/if}</span>
																<span class="badge bg-danger">{if isset($catsplit[1])} {$catsplit[1]}{/if}</span>
																<span class="badge bg-info">{$result->year}</span>
																<span class="badge bg-info">{if $result->rating != ''}{$result->rating}/10{/if}</span>
																<span class="badge bg-info">{if $result->rtrating != ''}RottenTomatoes Score {$result->rtrating}{/if}</span>
																<span class="badge bg-info">{$msize[$m@index]|filesize}</span>
																<span class="badge bg-info">Posted {$mpostdate[$m@index]|timeago}
																	ago</span>
																<br/><br/><br/>
																<div class="release-name text-muted"><a
																			href="{{url("/details/{$mguid[$m@index]}")}}">{$mname[$m@index]|escape:"htmlall"}</a>
																</div>
																<div>
																	<a role="button" class="btn btn-light btn-xs"
																	   data-bs-toggle="tooltip" data-bs-placement="top" title
																	   data-original-title="Download NZB"
																	   href="{{url("/getnzb?id={$mguid[$m@index]}")}}"><i
																				class="fa fa-cloud-download"></i><span
																				class="badge bg-info"> {$mgrabs[$m@index]}
																			Grab{if $mgrabs[$m@index] != 1}s{/if}</span></a>
																	<a role="button" class="btn btn-light btn-xs"
																	   href="{{url("/details/{$mguid[$m@index]}/#comments")}}"><i
																				class="fa fa-comment-o"></i><span
																				class="badge bg-info"> {$mcomments[$m@index]}
																			Comment{if $mcomments[$m@index] != 1}s{/if}</span></a>
                                                                    <a href="{{url("/cart/add?id={$mguid[$m@index]}")}}" target="_blank"><span class="btn btn-hover btn-light btn-xs text-muted"
																		  data-bs-toggle="tooltip" data-bs-placement="top" title
																		  data-original-title="Send to my download basket"><i
                                                                                class="fa fa-shopping-basket"></i></span></a>
																	{if !empty($mfailed[$m@index])}
																		<span class="btn btn-light btn-xs"
																			  title="This release has failed to download for some users">
																	<i class="fa fa-thumbs-o-up"></i> {$mgrabs[$m@index]}
																			Grab{if {$mgrabs[$m@index]} != 1}s{/if} / <i
																					class="fa fa-thumbs-o-down"></i> {$mfailed[$m@index]}
																			Failed Download{if {$mfailed[$m@index]} > 1}s{/if}</span>
																	{/if}
																</div>
															</td>
														</tr>
													</table>
													{/if}
													{/foreach}
												</div>
											</div>
										</div>
									</div>
								</div>
								<!-- /Left -->
								{else}
								<!-- Right -->
								<div class="col-md-6 small-gutter-left movie-height">
									<div class="card card-default">
										<div class="card-body">
											<div class="row small-gutter-left">
												<div class="col-md-3 small-gutter-left">
													{foreach $result->languages as $movielanguage}
													{release_flag($movielanguage, browse)}
													{/foreach}
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
													{assign var="mfailed" value=","|explode:$result->grp_release_failed}
													{assign var="mpass" value=","|explode:$result->grp_release_password}
													{assign var="minnerfiles" value=","|explode:$result->grp_rarinnerfilecount}
													{assign var="mhaspreview" value=","|explode:$result->grp_haspreview}
													{foreach $msplits as $m}
													{if $m@first}
													<a href="{{url("/Movies?imdb={$result->imdbid}")}}"><img
																class="cover shadow img-fluid rounded"
																src="{if isset($result->cover) && $result->cover == 1}{{url("/covers/movies/{$result->imdbid}-cover.jpg")}}{else}{{url("/images/no-cover.png")}}{/if}"
																width="140" border="0"
																alt="{$result->title|escape:"htmlall"}"/> {if !empty($mfailed[$m@index])}
															<i class="fa fa-exclamation-circle" style="color: red"
															   title="This release has failed to download for some users"></i>
														{/if}</a>
													<a target="_blank"
													   href="{$site->dereferrer_link}http://www.imdb.com/title/tt{$result->imdbid}/"
													   name="imdb{$result->imdbid}" title="View IMDB page"
													   class="badge bg-info" rel="imdb">IMDB</a>
													<a target="_blank"
													   href="{$site->dereferrer_link}http://trakt.tv/search/imdb/tt{$result->imdbid}/"
													   name="trakt{$result->imdbid}" title="View Trakt page"
													   class="badge bg-info" rel="trakt">TRAKT</a>
                                                    {if (!empty($result->tmdbid))}
                                                        <a class="badge bg-info" rel="tmdb" target="_blank"
                                                           href="{$site->dereferrer_link}http://www.themoviedb.org/movie/{$result->tmdbid}"
                                                           name="tmdb{$result->tmdbid}" title="View TheMovieDB page">TMDB</a>
                                                    {/if}
													{if $mnfo[$m@index] > 0}<span><a
																href="{{url("/nfo/{$mguid[$m@index]}")}}"
																title="View NFO" class="modal_nfo badge bg-info">NFO</a>
														</span>{/if}
													<a class="badge bg-info"
													   href="{{url("/browse/group?g={$mgrp[$m@index]}")}}"
													   title="Browse releases in {$mgrp[$m@index]|replace:"alt.binaries":"a.b"}">Group</a>
													<a class="badge bg-info" href="{{url("/mymovies?id=add&imdb={$result->imdbid}&from={$smarty.server.REQUEST_URI|escape:"url"}")}}" rel="add" name="movies{$result->imdbid}" title="Add to My Movies">Add</a>
												</div>
												<div class="col-md-9 small-gutter-left table-responsive">
																<span class="release-title"><a class="text-muted"
																							   href="{{url("/Movies?imdb={$result->imdbid}")}}">{$result->title|escape:"htmlall"}</a></span>
													<div class="release-subtitle">{if $result->genre != ''}
															<b>Genre: </b>
															{$result->genre}, {/if}</div>
													<div class="release-subtitle">{if $result->plot != ''}{$result->plot} {/if}</div>
													<div class="release-subtitle">{if $result->director != ''}<b>Director: </b>{$result->director} {/if}
													</div>
													<div class="release-subtitle">{if $result->actors != ''}
															<b>Starring: </b>
															{$result->actors} {/if}</div>
													<table class="data table table-striped responsive-utilities jambo-table">
														<tr>
															<td id="guid{$mguid[$m@index]}">
																<label>
																	<input type="checkbox"
																		   class="flat"
																		   value="{$mguid[$m@index]}"
																		   id="chksingle"/>
																</label>
																<span class="badge bg-info">{if isset($catsplit[0])} {$catsplit[0]}{/if}</span>
																<span class="badge bg-danger">{if isset($catsplit[1])} {$catsplit[1]}{/if}</span>
																<span class="badge bg-info">{$result->year}</span>
																<span class="badge bg-info">{if $result->rating != ''}{$result->rating}/10{/if}</span>
																<span class="badge bg-info">{if $result->rtrating != ''}RottenTomatoes Score {$result->rtrating}{/if}</span>
																<span class="badge bg-info">{$msize[$m@index]|filesize}</span>
																<span class="badge bg-info">Posted {$mpostdate[$m@index]|timeago}
																	ago</span>
																<br/><br/><br/>
																<div class="release-name text-muted"><a
																			href="{{url("/details/{$mguid[$m@index]}")}}">{$mname[$m@index]|escape:"htmlall"}</a>
																</div>
																<div>
																	<a role="button" class="btn btn-light btn-xs"
																	   data-bs-toggle="tooltip" data-bs-placement="top" title
																	   data-original-title="Download NZB"
																	   href="{{url("/getnzb?id={$mguid[$m@index]}")}}"><i
																				class="fa fa-cloud-download"></i><span
																				class="badge bg-info"> {$mgrabs[$m@index]}
																			Grab{if $mgrabs[$m@index] != 1}s{/if}</span></a>
																	<a role="button" class="btn btn-light btn-xs"
																	   href="{{url("/details/{$mguid[$m@index]}/#comments")}}"><i
																				class="fa fa-comment-o"></i><span
																				class="badge bg-info"> {$mcomments[$m@index]}
																			Comment{if $mcomments[$m@index] != 1}s{/if}</span></a>
                                                                    <a href="{{url("/cart/add?id={$mguid[$m@index]}")}}" target="_blank"><span class="btn btn-hover btn-light btn-xs text-muted"
																		  data-bs-toggle="tooltip" data-bs-placement="top" title
																		  data-original-title="Send to my download basket"><i
                                                                                class="fa fa-shopping-basket"></i></span></a>
																	{if !empty($mfailed[$m@index])}
																		<span class="btn btn-light btn-xs"
																			  title="This release has failed to download for some users">
																	<i class="fa fa-thumbs-o-up"></i> {$mgrabs[$m@index]}
																			Grab{if {$mgrabs[$m@index]} != 1}s{/if} / <i
																					class="fa fa-thumbs-o-down"></i> {$mfailed[$m@index]}
																			Failed Download{if {$mfailed[$m@index]} > 1}s{/if}</span>
																	{/if}
																</div>
															</td>
														</tr>
													</table>
													{/if}
													{/foreach}
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
						<div class="col-md-4">
							<div class="nzb_multi_operations">
								View: <strong>Covers</strong> | <a
										href="{{url("/browse/Movies/{$categorytitle}")}}">List</a>
								<br/>
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
						</div>
						<div class="col-md-8">
							{$results->onEachSide(5)->links()}
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
