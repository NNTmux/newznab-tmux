<div class="card card-default shadow-sm mb-4">
					    <div class="card-header bg-light">
					        <div class="d-flex justify-content-between align-items-center">
					            <h3 class="mb-0"><i class="fa fa-film me-2 text-primary"></i>My Movies</h3>
					            <div class="breadcrumb-wrapper">
					                <nav aria-label="breadcrumb">
					                    <ol class="breadcrumb mb-0 py-0">
					                        <li class="breadcrumb-item"><a href="{{url("{$site->home_link}")}}">Home</a></li>
					                        <li class="breadcrumb-item active">My Movies</li>
					                    </ol>
					                </nav>
					            </div>
					        </div>
					    </div>

					    <div class="card-body">
					        <div class="alert alert-info mb-4">
					            <i class="fa fa-info-circle me-2"></i>
					            Using 'My Movies' you can search for movies, and add them to a wishlist. If the movie becomes available it will be added to an
					            <strong><a href="{{url("/rss/mymovies?dl=1&amp;i={$userdata.id}&amp;api_token={$userdata.api_token}")}}" class="alert-link">
					                <i class="fa fa-rss me-1"></i>RSS Feed
					            </a></strong>
					            you can use to automatically download. You can
					            <strong><a href="{{route('mymovies')}}" class="alert-link">
					                <i class="fa fa-list me-1"></i>Manage Your Movie List
					            </a></strong>
					            to remove old items.
					        </div>

					        <div class="d-flex justify-content-between align-items-center mb-3">
					            <a href="{{url("/rss/mymovies?dl=1&amp;i={$userdata.id}&amp;api_token={$userdata.api_token}")}}" class="btn btn-outline-secondary">
					                <i class="fa fa-rss me-2"></i>RSS Feed
					            </a>
					        </div>

					        {if $movies|@count > 0}
					            <div class="table-responsive">
					                <table class="table table-striped table-hover">
					                    <thead class="table-light">
					                        <tr>
					                            <th style="width:140px">Cover</th>
					                            <th>Information</th>
					                            <th>Category</th>
					                            <th>Added</th>
					                            <th class="text-end">Actions</th>
					                        </tr>
					                    </thead>
					                    <tbody>
					                        {foreach $movies as $movie}
					                            <tr>
					                                <td>
					                                    <div class="text-center">
					                                        <img class="img-fluid rounded shadow-sm" style="max-width:120px"
					                                             src="{{url("/covers/movies/{if $movie.cover == 1}{$movie.imdbid}-cover.jpg{else}no-cover.jpg{/if}")}}"
					                                             alt="{$movie.title|escape:"htmlall"}"/>
					                                    </div>
					                                </td>
					                                <td>
					                                    <div class="mb-2">
					                                        <h5 class="mb-1">
					                                            <a href="{{url("/Movies?imdb={$movie.imdbid}")}}" class="text-decoration-none" data-bs-toggle="tooltip" data-bs-placement="top" title="View movie details">
					                                                {$movie.title|escape:"htmlall"} ({$movie.year})
					                                            </a>
					                                        </h5>

					                                        {if isset($movie.tagline) && $movie.tagline != ''}
					                                            <div class="fst-italic text-muted mb-2">{$movie.tagline}</div>
					                                        {/if}
					                                    </div>

					                                    {if isset($movie.plot) && $movie.plot != ''}
					                                        <p class="mb-2">{$movie.plot}</p>
					                                    {/if}

					                                    <div class="d-flex flex-wrap gap-3 mt-2">
					                                        {if isset($movie.genre) && $movie.genre != ''}
					                                            <div>
					                                                <span class="fw-bold text-secondary"><i class="fa fa-tag me-1"></i>Genre:</span> {$movie.genre}
					                                            </div>
					                                        {/if}

					                                        {if isset($movie.director) && $movie.director != ''}
					                                            <div>
					                                                <span class="fw-bold text-secondary"><i class="fa fa-video-camera me-1"></i>Director:</span> {$movie.director}
					                                            </div>
					                                        {/if}

					                                        {if isset($movie.actors) && $movie.actors != ''}
					                                            <div class="w-100 mt-1">
					                                                <span class="fw-bold text-secondary"><i class="fa fa-users me-1"></i>Starring:</span> {$movie.actors}
					                                            </div>
					                                        {/if}
					                                    </div>

					                                    <div class="mt-2">
					                                        <a class="badge bg-warning text-dark" target="_blank"
					                                           href="{$site->dereferrer_link}http://www.imdb.com/title/tt{$movie.imdbid}"
					                                           data-bs-toggle="tooltip" data-bs-placement="top" title="View on IMDB">
					                                            <i class="fa fa-external-link me-1"></i>IMDB
					                                        </a>
					                                    </div>
					                                </td>
					                                <td>
					                                    <span class="badge bg-secondary rounded-pill">
					                                        <i class="fa fa-folder-open me-1"></i>{if $movie.categoryNames != ''}{$movie.categoryNames|escape:"htmlall"}{else}All{/if}
					                                    </span>
					                                </td>
					                                <td>
					                                    <div class="d-flex align-items-center" title="Added on {$movie.created_at}">
					                                        <i class="fa fa-calendar text-muted me-2"></i>
					                                        {$movie.created_at|date_format}
					                                    </div>
					                                </td>
					                                <td class="text-end">
					                                    <div class="btn-group">
					                                        <a class="btn btn-sm btn-warning mymovies"
					                                           href="{{url("/mymovies?id=edit&imdb={$movie.imdbid}")}}" rel="edit"
					                                           name="movies{$movie.imdbid}" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit Categories">
					                                            <i class="fa fa-edit"></i>
					                                        </a>
					                                        <a class="btn btn-sm btn-danger mymovies"
					                                           href="{{url("/mymovies?id=delete&imdb={$movie.imdbid}")}}" rel="remove"
					                                           name="movies{$movie.imdbid}" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove from My Movies">
					                                            <i class="fa fa-trash"></i>
					                                        </a>
					                                    </div>
					                                </td>
					                            </tr>
					                        {/foreach}
					                    </tbody>
					                </table>
					            </div>
					        {else}
					            <div class="alert alert-info">
					                <i class="fa fa-info-circle me-2"></i>No movies bookmarked yet. Add movies from movie pages.
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
