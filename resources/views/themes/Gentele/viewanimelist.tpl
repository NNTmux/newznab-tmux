<div class="card card-default shadow-sm mb-4">
	    <div class="card-header bg-light">
	        <div class="d-flex justify-content-between align-items-center">
	            <h3 class="mb-0"><i class="fa fa-film me-2 text-primary"></i>Anime List</h3>
	            <div class="breadcrumb-wrapper">
	                <nav aria-label="breadcrumb">
	                    <ol class="breadcrumb mb-0 py-0">
	                        <li class="breadcrumb-item"><a href="{{url("{$site->home_link}")}}">Home</a></li>
	                        <li class="breadcrumb-item active">Anime List</li>
	                    </ol>
	                </nav>
	            </div>
	        </div>
	    </div>

	    <div class="card-body">
	        <div class="row mb-4">
	            <!-- Alphabet navigation -->
	            <div class="col-md-8">
	                <div class="d-flex align-items-center flex-wrap gap-2">
	                    <span class="fw-bold me-2">Jump to:</span>
	                    <div class="btn-group btn-group-sm">
	                        <a href="{{url("/animelist")}}" class="btn {if $animeletter == '0-9'}btn-primary{else}btn-outline-secondary{/if}">0-9</a>
	                        {foreach $animerange as $range}
	                            <a href="{{url("/animelist?id={$range}")}}" class="btn {if $range == $animeletter}btn-primary{else}btn-outline-secondary{/if}">{$range}</a>
	                        {/foreach}
	                    </div>
	                </div>
	            </div>

	            <!-- Search form -->
	            <div class="col-md-4">
	                {{Form::open(['class' => 'd-flex', 'method' => 'get', 'name' => 'anidbsearch'])}}
	                    <div class="input-group">
	                        <input type="text" class="form-control" name="title" id="title"
	                               value="{$animetitle}" placeholder="Search anime..." aria-label="Search anime">
	                        <button class="btn btn-primary" type="submit" data-bs-toggle="tooltip" data-bs-placement="top" title="Search anime">
	                            <i class="fa fa-search"></i>
	                        </button>
	                    </div>
	                {{Form::close()}}
	            </div>
	        </div>

	        {$site->adbrowse}

	        {if $animelist|@count > 0}
	            {foreach $animelist as $aletter => $anime}
	                <div class="mb-4">
	                    <div class="d-flex justify-content-between align-items-center mb-3">
	                        <h4 class="mb-0"><i class="fa fa-bookmark me-2 text-primary"></i>{$aletter}</h4>
	                    </div>

	                    <div class="table-responsive">
	                        <table class="table table-striped table-hover">
	                            <thead class="table-light">
	                                <tr>
	                                    <th width="35%">Name</th>
	                                    <th>Type</th>
	                                    <th width="35%">Categories</th>
	                                    <th>Rating</th>
	                                    <th class="text-center">Actions</th>
	                                </tr>
	                            </thead>
	                            <tbody>
	                                {foreach $anime as $a}
	                                    <tr>
	                                        <td>
	                                            <div class="mb-1">
	                                                <a class="title fw-semibold" href="{{url("/anime?id={$a->anidbid}")}}" data-bs-toggle="tooltip" data-bs-placement="top" title="View anime details">
	                                                    {$a->title|escape:"htmlall"}
	                                                </a>
	                                            </div>
	                                            {if $a->startdate != ''}
	                                                <div class="mt-2">
	                                                    <span class="badge bg-info text-dark">
	                                                        <i class="fa fa-calendar me-1"></i>
	                                                        {$a->startdate|date_format}
	                                                        {if $a->enddate != ''} - {$a->enddate|date_format}{/if}
	                                                    </span>
	                                                </div>
	                                            {/if}
	                                        </td>
	                                        <td>
	                                            {if $a->type != ''}
	                                                <span class="badge bg-secondary">
	                                                    <i class="fa fa-tag me-1"></i>{$a->type|escape:"htmlall"}
	                                                </span>
	                                            {/if}
	                                        </td>
	                                        <td>
	                                            {if $a->categories != ''}
	                                                <div class="d-flex flex-wrap gap-2">
	                                                    {foreach explode('|', $a->categories) as $category}
	                                                        <span class="badge bg-primary">{$category|trim}</span>
	                                                    {/foreach}
	                                                </div>
	                                            {/if}
	                                        </td>
	                                        <td>
	                                            {if $a->rating != ''}
	                                                <div class="d-flex align-items-center">
	                                                    <span class="badge bg-warning text-dark">
	                                                        <i class="fa fa-star me-1"></i>{$a->rating}
	                                                    </span>
	                                                </div>
	                                            {/if}
	                                        </td>
	                                        <td class="text-center">
	                                            <div class="d-flex justify-content-center gap-2">
	                                                <a href="{{url("/anime?id={$a->anidbid}")}}" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" data-bs-placement="top" title="View details">
	                                                    <i class="fa fa-eye"></i>
	                                                </a>
	                                                <a href="{$site->dereferrer_link}http://anidb.net/perl-bin/animedb.pl?show=anime&aid={$a->anidbid}" target="_blank" class="btn btn-sm btn-info" data-bs-toggle="tooltip" data-bs-placement="top" title="View on AniDB">
	                                                    <i class="fa fa-external-link"></i>
	                                                </a>
	                                                <a href="{{url("/rss/full-feed?anidb={$a->anidbid}&amp;dl=1&amp;i={$userdata.id|default:''}&amp;api_token={$userdata.api_token|default:''}")}}" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" data-bs-placement="top" title="RSS feed">
	                                                    <i class="fa fa-rss"></i>
	                                                </a>
	                                            </div>
	                                        </td>
	                                    </tr>
	                                {/foreach}
	                            </tbody>
	                        </table>
	                    </div>
	                </div>
	            {/foreach}
	        {else}
	            <div class="alert alert-info">
	                <i class="fa fa-info-circle me-2"></i>No results found for this query.
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
