<div class="card card-default shadow-sm mb-4">
			    <div class="card-header bg-light">
			        <div class="d-flex justify-content-between align-items-center">
			            <h3 class="mb-0"><i class="fa fa-tv me-2 text-primary"></i>TV Series</h3>
			            <div class="breadcrumb-wrapper">
			                <nav aria-label="breadcrumb">
			                    <ol class="breadcrumb mb-0 py-0">
			                        <li class="breadcrumb-item"><a href="{{url("{$site->home_link}")}}">Home</a></li>
			                        <li class="breadcrumb-item active">TV Series List</li>
			                    </ol>
			                </nav>
			            </div>
			        </div>
			    </div>

			    <div class="card-body">
			        <!-- Alphabet navigation -->
			        <div class="mb-3">
			            <div class="d-flex align-items-center flex-wrap gap-2">
			                <span class="fw-bold me-2">Jump to:</span>
			                <div class="btn-group btn-group-sm">
			                    <a href="{{url("/series/0-9")}}" class="btn {if $seriesletter == '0-9'}btn-primary{else}btn-outline-secondary{/if}">0-9</a>
			                    {foreach $seriesrange as $range}
			                        <a href="{{url("/series/{$range}")}}" class="btn {if $range == $seriesletter}btn-primary{else}btn-outline-secondary{/if}">{$range}</a>
			                    {/foreach}
			                </div>
			            </div>
			        </div>

			        <!-- Action buttons -->
			        <div class="d-flex justify-content-between align-items-center mb-4">
			            <div class="btn-group">
			                <a class="btn btn-primary" href="{{route('myshows')}}" data-bs-toggle="tooltip" data-bs-placement="top" title="List my watched shows">
			                    <i class="fa fa-list me-2"></i>My Shows
			                </a>
			                <a class="btn btn-success" href="{{url("/myshows/browse")}}" data-bs-toggle="tooltip" data-bs-placement="top" title="Browse your shows">
			                    <i class="fa fa-search me-2"></i>Find My Shows
			                </a>
			            </div>

			            <!-- Search form -->
			            <div class="search-form">
			                {{Form::open(['name' => 'showsearch', 'class' => 'd-flex', 'method' => 'get'])}}
			                    <div class="input-group">
			                        <input class="form-control" type="text" name="title"
			                               {if isset($serieslist.title)} value="{$serieslist.title}"{/if}
			                               placeholder="Search series" aria-label="Search series">
			                        <button class="btn btn-primary" type="submit" data-bs-toggle="tooltip" data-bs-placement="top" title="Search series">
			                            <i class="fa fa-search"></i>
			                        </button>
			                    </div>
			                {{Form::close()}}
			            </div>
			        </div>

			        {$site->adbrowse}

			        <!-- Series list -->
			        {if $serieslist|@count > 0}
			            <div class="table-responsive">
			                <table class="table table-striped table-hover">
			                    {foreach $serieslist as $sletter => $series}
			                        <thead class="table-light">
			                            <tr>
			                                <th colspan="5">
			                                    <h4 class="mb-0"><i class="fa fa-bookmark me-2 text-primary"></i>{$sletter}</h4>
			                                </th>
			                            </tr>
			                            <tr>
			                                <th class="text-start">Name</th>
			                                <th class="text-center" style="width:120px">Network</th>
			                                <th class="text-center" style="width:120px">Country</th>
			                                <th class="text-center" style="width:140px">Actions</th>
			                                <th class="text-center" style="width:200px">External Links</th>
			                            </tr>
			                        </thead>
			                        <tbody>
			                            {foreach $series as $s}
			                                <tr>
			                                    <td>
			                                        <div class="mb-1">
			                                            <a class="fw-semibold text-decoration-none" title="View series details" href="{{url("/series/{$s.id}")}}">
			                                                {if !empty($s.title)}{$s.title|escape:"htmlall"}{/if}
			                                            </a>
			                                        </div>
			                                        {if $s.prevdate != ''}
			                                            <span class="badge bg-info">
			                                                <i class="fa fa-calendar me-1"></i>Last: {$s.previnfo|escape:"htmlall"} aired {$s.prevdate|date_format}
			                                            </span>
			                                        {/if}
			                                    </td>
			                                    <td class="text-center">
			                                        {if $s.publisher}
			                                            <span class="badge bg-secondary">{$s.publisher|escape:"htmlall"}</span>
			                                        {/if}
			                                    </td>
			                                    <td class="text-center">
			                                        {if $s.countries_id}
			                                            <span class="badge bg-secondary">{$s.countries_id|escape:"htmlall"}</span>
			                                        {/if}
			                                    </td>
			                                    <td class="text-center">
			                                        {if $s.userseriesid != null}
			                                            <div class="btn-group btn-group-sm">
			                                                <a href="{{url("/myshows?action=edit&id={$s.id}&from={$smarty.server.REQUEST_URI|escape:"url"}")}}"
			                                                   class="myshows btn btn-warning" rel="edit" name="series{$s.id}"
			                                                   data-bs-toggle="tooltip" data-bs-placement="top" title="Edit this show">
			                                                    <i class="fa fa-edit"></i>
			                                                </a>
			                                                <a href="{{url("/myshows?action=delete&id={$s.id}&from={$smarty.server.REQUEST_URI|escape:"url"}")}}"
			                                                   class="myshows btn btn-danger" rel="remove" name="series{$s.id}"
			                                                   data-bs-toggle="tooltip" data-bs-placement="top" title="Remove from My Shows">
			                                                    <i class="fa fa-trash"></i>
			                                                </a>
			                                            </div>
			                                        {else}
			                                            <a href="{{url("/myshows?action=add&id={$s.id}&from={$smarty.server.REQUEST_URI|escape:"url"}")}}"
			                                               class="myshows btn btn-sm btn-success" rel="add" name="series{$s.id}"
			                                               data-bs-toggle="tooltip" data-bs-placement="top" title="Add to My Shows">
			                                                <i class="fa fa-plus me-1"></i>Add
			                                            </a>
			                                        {/if}
			                                    </td>
			                                    <td>
			                                        <div class="d-flex justify-content-center gap-2">
			                                            <a class="btn btn-sm btn-outline-primary" title="View series details" href="{{url("/series/{$s.id}")}}">
			                                                <i class="fa fa-tv"></i>
			                                            </a>

			                                            {if $s.id > 0}
			                                                <div class="btn-group btn-group-sm">
			                                                    {if $s.tvdb > 0}
			                                                        <a class="btn btn-sm btn-outline-secondary"
			                                                           title="View at TVDB" target="_blank"
			                                                           href="{$site->dereferrer_link}http://thetvdb.com/?tab=series&id={$s.tvdb}">
			                                                            TVDB
			                                                        </a>
			                                                    {/if}
			                                                    {if $s.tvmaze > 0}
			                                                        <a class="btn btn-sm btn-outline-secondary"
			                                                           title="View at TVMaze" target="_blank"
			                                                           href="{$site->dereferrer_link}http://tvmaze.com/shows/{$s.tvmaze}">
			                                                            TVMaze
			                                                        </a>
			                                                    {/if}
			                                                    {if $s.trakt > 0}
			                                                        <a class="btn btn-sm btn-outline-secondary"
			                                                           title="View at Trakt" target="_blank"
			                                                           href="{$site->dereferrer_link}http://www.trakt.tv/shows/{$s.trakt}">
			                                                            Trakt
			                                                        </a>
			                                                    {/if}
			                                                </div>

			                                                <a class="btn btn-sm btn-outline-warning"
			                                                   title="RSS Feed for {$s.title|escape:"htmlall"}"
			                                                   href="{{url("/rss/full-feed?show={$s.id}&amp;dl=1&amp;i={$userdata.id}&amp;api_token={$userdata.api_token}")}}">
			                                                    <i class="fa fa-rss"></i>
			                                                </a>
			                                            {/if}
			                                        </div>
			                                    </td>
			                                </tr>
			                            {/foreach}
			                        </tbody>
			                    {/foreach}
			                </table>
			            </div>
			        {else}
			            <div class="alert alert-info">
			                <i class="fa fa-info-circle me-2"></i>No results found for that search term.
			            </div>
			        {/if}
			    </div>
			</div>

			<script>
			{literal}
			    document.addEventListener('DOMContentLoaded', function() {
			        // Initialize tooltips
			        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
			        const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
			    });
			{/literal}
			</script>
