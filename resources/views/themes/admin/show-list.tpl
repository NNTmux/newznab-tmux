<div class="card">
				    <div class="card-header">
				        <div class="d-flex justify-content-between align-items-center">
				            <h4 class="mb-0">{$title}</h4>
				            <a href="{{url("/admin/show-add")}}" class="btn btn-outline-success">
				                <i class="fa fa-plus me-2"></i>Add New Show
				            </a>
				        </div>
				    </div>

				    <div class="card-body">
				        <form name="showsearch" method="get" action="{{url("/admin/show-list")}}" id="show-search-form" class="mb-4">
				            {{csrf_field()}}
				            <div class="row">
				                <div class="col-md-6 offset-md-6">
				                    <div class="input-group">
				                        <input type="text" class="form-control" placeholder="Search by show title"
				                               id="showname" name="showname" value="{$showname|escape:'html'}">
				                        <button type="submit" class="btn btn-primary">
				                            <i class="fa fa-search me-2"></i>Search
				                        </button>
				                    </div>
				                </div>
				            </div>
				        </form>

				        {if $tvshowlist}
				            <div class="table-responsive">
				                <table class="table table-striped table-hover align-middle">
				                    <thead class="thead-light">
				                        <tr>
				                            <th>ID</th>
				                            <th>Title</th>
				                            <th>Started</th>
				                            <th>Source</th>
				                            <th class="text-end">Actions</th>
				                        </tr>
				                    </thead>
				                    <tbody>
				                        {foreach from=$tvshowlist item=tvshow}
				                            <tr>
				                                <td>
				                                    <span class="badge bg-secondary">{$tvshow.id}</span>
				                                </td>
				                                <td>
				                                    <div class="mb-1">
				                                        <a href="{{url("/admin/show-edit?id={$tvshow.id}")}}" class="title fw-semibold">
				                                            {$tvshow.title|escape:"htmlall"}
				                                        </a>
				                                    </div>
				                                </td>
				                                <td>
				                                    <div class="d-flex align-items-center">
				                                        <i class="fa fa-calendar text-muted me-2"></i>
				                                        <span title="{$tvshow.started}">{$tvshow.started|date_format}</span>
				                                    </div>
				                                </td>
				                                <td>
				                                    {if $tvshow.source == 1}
				                                        <span class="badge bg-info">TVDB</span>
				                                    {elseif $tvshow.source == 2}
				                                        <span class="badge bg-primary">TVMaze</span>
				                                    {elseif $tvshow.source == 3}
				                                        <span class="badge bg-warning">TMDB</span>
				                                    {/if}
				                                </td>
				                                <td class="text-end">
				                                    <div class="btn-group btn-group-sm" role="group">
				                                        <a href="{{url("/admin/show-edit?id={$tvshow.id}")}}" class="btn btn-primary" data-bs-toggle="tooltip" title="Edit this show">
				                                            <i class="fa fa-pencil"></i>
				                                        </a>
				                                        <a href="{{url("/admin/show-delete?id={$tvshow.id}")}}" class="btn btn-danger" data-bs-toggle="tooltip" title="Delete show" onclick="return confirm('Are you sure you want to delete this show?')">
				                                            <i class="fa fa-trash"></i>
				                                        </a>
				                                        <a href="{{url("/admin/show-remove?id={$tvshow.id}")}}" class="btn btn-warning" data-bs-toggle="tooltip" title="Remove show ID from all releases" onclick="return confirm('Are you sure you want to remove this show ID from all releases?')">
				                                            <i class="fa fa-unlink"></i>
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
				                <i class="fa fa-info-circle me-2"></i>No TV Shows available. Use the Add New Show button to add some.
				            </div>
				        {/if}
				    </div>

				    {if $tvshowlist}
				        <div class="card-footer">
				            <div class="d-flex justify-content-between align-items-center">
				                <div>
				                    Showing {$tvshowlist->firstItem()} to {$tvshowlist->lastItem()} of {$tvshowlist->total()} TV shows
				                </div>
				                <div class="pagination-container overflow-auto">
				                    {$tvshowlist->onEachSide(5)->links()}
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
				    #show-search-form .row {
				        margin-right: 0;
				        margin-left: 0;
				    }

				    #show-search-form .col-md-6 {
				        padding-right: 0;
				        padding-left: 0;
				    }
				}
				{/literal}
				</style>
