<div class="card">
				    <div class="card-header">
				        <div class="d-flex justify-content-between align-items-center">
				            <h4 class="mb-0">{$title}</h4>
				            <a href="{{url("/admin/movie-add")}}" class="btn btn-outline-success">
				                <i class="fa fa-plus me-2"></i>Add New Movie
				            </a>
				        </div>
				    </div>

				    <div class="card-body">
				        <form name="moviesearch" method="get" action="{{url("/admin/movie-list")}}" id="movie-search-form" class="mb-4">
				            {{csrf_field()}}
				            <div class="row">
				                <div class="col-md-6 offset-md-6">
				                    <div class="input-group">
				                        <input type="text" class="form-control" placeholder="Search by title, IMDB ID, or TMDb ID"
				                               id="moviesearch" name="moviesearch" value="{$lastSearch|escape:'html'}">
				                        <button type="submit" class="btn btn-primary">
				                            <i class="fa fa-search me-2"></i>Search
				                        </button>
				                    </div>
				                </div>
				            </div>
				        </form>

				        {if $movielist}
				            <div class="table-responsive">
				                <table class="table table-striped table-hover align-middle">
				                    <thead class="thead-light">
				                        <tr>
				                            <th>
				                                <div class="d-flex align-items-center gap-2">
				                                    <span>IMDB ID</span>
				                                </div>
				                            </th>
				                            <th>
				                                <div class="d-flex align-items-center gap-2">
				                                    <span>TMDb ID</span>
				                                </div>
				                            </th>
				                            <th>
				                                <div class="d-flex align-items-center gap-2">
				                                    <span>Title</span>
				                                </div>
				                            </th>
				                            <th class="text-center">Cover</th>
				                            <th class="text-center">Backdrop</th>
				                            <th>
				                                <div class="d-flex align-items-center gap-2">
				                                    <span>Created</span>
				                                </div>
				                            </th>
				                            <th class="text-end">Actions</th>
				                        </tr>
				                    </thead>
				                    <tbody>
				                        {foreach $movielist as $movie}
				                            <tr>
				                                <td>
				                                    <a href="http://www.imdb.com/title/tt{$movie->imdbid}" target="_blank" class="badge bg-secondary text-white" title="View in IMDB">
				                                        <i class="fa fa-film me-1"></i>tt{$movie->imdbid}
				                                    </a>
				                                </td>
				                                <td>
				                                    <a href="http://www.themoviedb.org/movie/{$movie->tmdbid}" target="_blank" class="badge bg-info text-white" title="View in TMDb">
				                                        <i class="fa fa-database me-1"></i>{$movie->tmdbid}
				                                    </a>
				                                </td>
				                                <td>
				                                    <div class="mb-1">
				                                        <a href="{{url("/admin/movie-edit?id={$movie->imdbid}")}}" class="title fw-semibold">
				                                            {$movie->title} ({$movie->year})
				                                        </a>
				                                    </div>
				                                </td>
				                                <td class="text-center">
				                                    {if !empty($movie->cover)}
				                                        <span class="badge bg-success"><i class="fa fa-check me-1"></i>Yes</span>
				                                    {else}
				                                        <span class="badge bg-danger"><i class="fa fa-times me-1"></i>No</span>
				                                    {/if}
				                                </td>
				                                <td class="text-center">
				                                    {if !empty($movie->backdrop)}
				                                        <span class="badge bg-success"><i class="fa fa-check me-1"></i>Yes</span>
				                                    {else}
				                                        <span class="badge bg-danger"><i class="fa fa-times me-1"></i>No</span>
				                                    {/if}
				                                </td>
				                                <td>
				                                    <div class="d-flex align-items-center">
				                                        <i class="fa fa-calendar text-muted me-2"></i>
				                                        <span title="{$movie->created_at}">{$movie->created_at|date_format}</span>
				                                    </div>
				                                </td>
				                                <td class="text-end">
				                                    <div class="btn-group btn-group-sm" role="group">
				                                        <a href="{{url("/admin/movie-edit?id={$movie->imdbid}")}}" class="btn btn-primary" data-bs-toggle="tooltip" title="Edit this movie">
				                                            <i class="fa fa-pencil"></i>
				                                        </a>
				                                        <a href="{{url("/admin/movie-add?id={$movie->imdbid}&amp;update=1")}}" class="btn btn-warning" data-bs-toggle="tooltip" title="Update metadata">
				                                            <i class="fa fa-refresh"></i>
				                                        </a>
				                                        <a href="{{url("/admin/movie-delete?id={$movie->imdbid}")}}" class="btn btn-danger" data-bs-toggle="tooltip" title="Delete movie" onclick="return confirm('Are you sure you want to delete this movie?')">
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
				                <i class="fa fa-info-circle me-2"></i>No movies available. Use the Add New Movie button to add some.
				            </div>
				        {/if}
				    </div>

				    {if $movielist}
				        <div class="card-footer">
				            <div class="d-flex justify-content-between align-items-center">
				                <div>
				                    Showing {$movielist->firstItem()} to {$movielist->lastItem()} of {$movielist->total()} movies
				                </div>
				                <div class="pagination-container overflow-auto">
				                    {$movielist->onEachSide(5)->links()}
				                </div>
				            </div>
				        </div>
				    {/if}
				</div>

				<script>
				{literal}
				document.addEventListener('DOMContentLoaded', function() {
				    // Initialize tooltips
				    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
				    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
				        return new bootstrap.Tooltip(tooltipTriggerEl);
				    });
				});
				{/literal}
				</script>

				<style>
				{literal}
				/* Improve table responsiveness */
				.table-responsive {
				    overflow-x: auto;
				    -webkit-overflow-scrolling: touch;
				}

				/* Badge styling */
				.badge {
				    font-weight: 500;
				    padding: 0.4em 0.6em;
				}

				/* Pagination container */
				.pagination-container {
				    max-width: 100%;
				}

				/* Improve action buttons on small screens */
				@media (max-width: 767.98px) {
				    .btn-group .btn {
				        padding: 0.375rem 0.5rem;
				    }

				    .card-footer .d-flex {
				        flex-direction: column;
				        gap: 0.5rem;
				    }

				    .pagination-container {
				        justify-content: center !important;
				    }
				}

				/* Improve search form on small screens */
				@media (max-width: 767.98px) {
				    #movie-search-form .row {
				        margin-right: 0;
				        margin-left: 0;
				    }

				    #movie-search-form .col-md-6 {
				        padding-right: 0;
				        padding-left: 0;
				    }
				}
				{/literal}
				</style>
