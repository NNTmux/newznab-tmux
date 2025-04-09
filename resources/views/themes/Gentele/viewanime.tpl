<div class="card card-default shadow-sm mb-4">
			    {if isset($nodata) && $nodata !=''}
			        <div class="card-header bg-light">
			            <h3 class="mb-0"><i class="fa fa-film me-2 text-primary"></i>View Anime</h3>
			            <p class="mt-2 mb-0">{$nodata}</p>
			        </div>
			    {else}
			        <div class="card-header bg-light">
			            <div class="d-flex justify-content-between align-items-center">
			                <h3 class="mb-0"><i class="fa fa-film me-2 text-primary"></i>View Anime</h3>
			                <div class="breadcrumb-wrapper">
			                    <nav aria-label="breadcrumb">
			                        <ol class="breadcrumb mb-0 py-0">
			                            <li class="breadcrumb-item"><a href="{{url("{$site->home_link}")}}">Home</a></li>
			                            <li class="breadcrumb-item"><a href="{{url("/animelist")}}">Anime List</a></li>
			                            <li class="breadcrumb-item active">{$animeTitle|escape:"htmlall"|truncate:30}</li>
			                        </ol>
			                    </nav>
			                </div>
			            </div>
			        </div>

			        <div class="card-body">
			            <div class="row">
			                <div class="col-md-4 mb-4 mb-md-0">
			                    <div class="text-center">
			                        <img class="img-fluid rounded shadow-sm" alt="{$animeTitle} Picture" src="{{url("/covers/anime/{$animeAnidbid}.jpg")}}"/>

			                        <div class="mt-3">
			                            <div class="btn-group w-100">
			                                <a class="btn btn-primary" href="{$site->dereferrer_link}http://anidb.net/perl-bin/animedb.pl?show=anime&amp;aid={$animeAnidbid}" target="_blank" data-bs-toggle="tooltip" data-bs-placement="top" title="View on AniDB">
			                                    <i class="fa fa-external-link me-2"></i>View on AniDB
			                                </a>
			                                <a class="btn btn-outline-primary" href="{{url("/rss/full-feed?anidb={$animeAnidbid}&amp;dl=1&amp;i={$userdata.id}&amp;api_token={$userdata.api_token}")}}" data-bs-toggle="tooltip" data-bs-placement="top" title="Get RSS feed for this anime">
			                                    <i class="fa fa-rss me-2"></i>RSS Feed
			                                </a>
			                            </div>

			                            {if isset($isadmin)}
			                                <a class="btn btn-warning w-100 mt-2" href="{{url("/admin/anidb-edit/{$animeAnidbid}")}}" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit AniDB data">
			                                    <i class="fa fa-edit me-2"></i>Edit Anime
			                                </a>
			                            {/if}
			                        </div>
			                    </div>
			                </div>

			                <div class="col-md-8">
			                    <h1 class="mb-3">{$animeTitle}</h1>

			                    <div class="mb-3">
			                        {if $animeType != ''}
			                            <span class="badge bg-secondary me-2"><i class="fa fa-tag me-1"></i>{$animeType|escape:"htmlall"}</span>
			                        {/if}

			                        {if $animeRating != ''}
			                            <span class="badge bg-warning text-dark me-2">
			                                <i class="fa fa-star me-1"></i>Rating: {$animeRating|escape:"htmlall"}
			                            </span>
			                        {/if}

			                        {if $animeStartDate != '' && $animeStartDate != '1970-01-01'}
			                            <span class="badge bg-info text-dark me-2">
			                                <i class="fa fa-calendar-check-o me-1"></i>Started: {$animeStartDate|date_format:"%d %b %Y"}
			                            </span>
			                        {/if}

			                        {if $animeEndDate != '' && $animeEndDate != '1970-01-01'}
			                            <span class="badge bg-info text-dark">
			                                <i class="fa fa-calendar-times-o me-1"></i>Ended: {$animeEndDate|date_format:"%d %b %Y"}
			                            </span>
			                        {/if}
			                    </div>

			                    {if $animeCategories != ''}
			                        <div class="mb-3">
			                            <h5><i class="fa fa-folder-open me-2 text-secondary"></i>Categories</h5>
			                            <div class="d-flex flex-wrap gap-2">
			                                {foreach explode(', ', $animeCategories) as $category}
			                                    <span class="badge bg-primary">{$category}</span>
			                                {/foreach}
			                            </div>
			                        </div>
			                    {/if}

			                    <div class="mb-3">
			                        <h5><i class="fa fa-file-text-o me-2 text-secondary"></i>Description</h5>
			                        <div class="description-content">
			                            <span class="descinitial">{$animeDescription|escape:"htmlall"|nl2br|magicurl|truncate:"1000":" </span><a class=\"btn btn-sm btn-outline-secondary descmore\" href=\"#\">Show more</a>"}
			                            {if $animeDescription|strlen > 1000}
			                                <span class="descfull d-none">{$animeDescription|escape:"htmlall"|nl2br|magicurl}</span>
			                            {else}
			                                </span>
			                            {/if}
			                        </div>
			                    </div>

			                    {if $animeCharacters != ''}
			                        <div class="mb-3">
			                            <h5><i class="fa fa-users me-2 text-secondary"></i>Characters</h5>
			                            <p>{$animeCharacters|escape:"htmlall"}</p>
			                        </div>
			                    {/if}

			                    {if $animeCreators !=''}
			                        <div class="mb-3">
			                            <h5><i class="fa fa-user me-2 text-secondary"></i>Created by</h5>
			                            <p>{$animeCreators|escape:"htmlall"}</p>
			                        </div>
			                    {/if}

			                    {if $animeRelated != ''}
			                        <div class="mb-3">
			                            <h5><i class="fa fa-link me-2 text-secondary"></i>Related Anime</h5>
			                            <p>{$animeRelated|escape:"htmlall"}</p>
			                        </div>
			                    {/if}
			                </div>
			            </div>
			        </div>
			    {/if}
			</div>

			{if !isset($nodata) || $nodata == ''}
			    <!-- Releases section -->
			    <div class="card card-default shadow-sm mb-4">
			        <div class="card-header bg-light">
			            <h4 class="mb-0"><i class="fa fa-download me-2 text-primary"></i>Available Releases</h4>
			        </div>

			        <div class="card-body py-3">
			            {{Form::open(['id' => 'nzb_multi_operations_form', 'method' => 'get'])}}
			                <div class="mb-3">
			                    <div class="d-flex align-items-center">
			                        <span class="me-2">With Selected:</span>
			                        <div class="btn-group">
			                            <button type="button" class="nzb_multi_operations_download btn btn-sm btn-success" data-bs-toggle="tooltip" data-bs-placement="top" title="Download NZBs">
			                                <i class="fa fa-cloud-download me-1"></i>Download
			                            </button>
			                            <button type="button" class="nzb_multi_operations_cart btn btn-sm btn-info" data-bs-toggle="tooltip" data-bs-placement="top" title="Send to my Download Basket">
			                                <i class="fa fa-shopping-basket me-1"></i>Add to Cart
			                            </button>
			                        </div>
			                    </div>
			                </div>

			                <div class="table-responsive">
			                    <table class="table table-striped table-hover">
			                        <thead class="table-light">
			                            <tr>
			                                <th style="width: 30px">
			                                    <input id="check-all" type="checkbox" class="flat-all">
			                                </th>
			                                <th>Name</th>
			                                <th>Category</th>
			                                <th>Posted</th>
			                                <th>Size</th>
			                                <th>Files</th>
			                                <th>Downloads</th>
			                                <th class="text-end">Actions</th>
			                            </tr>
			                        </thead>
			                        <tbody>
			                            <!-- Table content populated by PHP -->
			                        </tbody>
			                    </table>
			                </div>
			            {{Form::close()}}
			        </div>
			    </div>
			{/if}

			<script>
			{literal}
			document.addEventListener('DOMContentLoaded', function() {
			    // Initialize tooltips
			    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
			    const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

			    // Show more/less description
			    const descMore = document.querySelector('.descmore');
			    if (descMore) {
			        descMore.addEventListener('click', function(e) {
			            e.preventDefault();
			            const descInitial = document.querySelector('.descinitial');
			            const descFull = document.querySelector('.descfull');

			            if (descFull.classList.contains('d-none')) {
			                descInitial.classList.add('d-none');
			                descFull.classList.remove('d-none');
			                this.innerHTML = '<i class="fa fa-compress me-1"></i>Show less';
			            } else {
			                descInitial.classList.remove('d-none');
			                descFull.classList.add('d-none');
			                this.innerHTML = '<i class="fa fa-expand me-1"></i>Show more';
			            }
			        });
			    }
			});
			{/literal}
			</script>
