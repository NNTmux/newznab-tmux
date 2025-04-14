<div class="card">
				    <div class="card-header">
				        <div class="d-flex justify-content-between align-items-center">
				            <h4 class="mb-0">{$title}</h4>
				            <div class="btn-group">
				                <a href="{{url("/admin/release-add")}}" class="btn btn-outline-success">
				                    <i class="fa fa-plus me-2"></i>Add Release
				                </a>
				            </div>
				        </div>
				    </div>

				    <div class="card-body">
				        <form name="releasesearch" method="get" action="{{url("/admin/release-list")}}" id="release-search-form" class="mb-4">
				            {{csrf_field()}}
				            <div class="row">
				                <div class="col-md-6 offset-md-6">
				                    <div class="input-group">
				                        <input type="text" class="form-control" placeholder="Search by name or category"
				                               id="releasesearch" name="releasesearch" value="{$lastSearch|escape:'html'}">
				                        <button type="submit" class="btn btn-primary">
				                            <i class="fa fa-search me-2"></i>Search
				                        </button>
				                    </div>
				                </div>
				            </div>
				        </form>

				        {if $releaselist}
				            <div class="table-responsive">
				                <table class="table table-striped table-hover align-middle">
				                    <thead class="thead-light">
				                        <tr>
				                            <th>Name</th>
				                            <th>Category</th>
				                            <th class="text-end">Size</th>
				                            <th class="text-center">Files</th>
				                            <th>Post Date</th>
				                            <th>Add Date</th>
				                            <th class="text-center">Grabs</th>
				                            <th class="text-end">Actions</th>
				                        </tr>
				                    </thead>
				                    <tbody>
				                        {foreach $releaselist as $release}
				                            <tr>
				                                <td>
				                                    <a href="{{url("/admin/release-edit?id={$release->guid}")}}" class="title fw-semibold text-truncate d-inline-block" style="max-width: 300px;" title="{$release->name}">
				                                        {$release->searchname|escape:"htmlall"}
				                                    </a>
				                                </td>
				                                <td>
				                                    <span class="badge bg-secondary">{$release->category_name}</span>
				                                </td>
				                                <td class="text-end">
				                                    <span class="badge bg-info">{$release->size|filesize}</span>
				                                </td>
				                                <td class="text-center">
				                                    <a href="{{url("/admin/release-files?id={$release->guid}")}}" class="badge bg-primary">
				                                        <i class="fa fa-file me-1"></i>{$release->totalpart}
				                                    </a>
				                                </td>
				                                <td>
				                                    <div class="d-flex align-items-center">
				                                        <i class="fa fa-calendar text-muted me-2"></i>
				                                        <span title="{$result->postdate}">{$result->postdate|date_format}</span>
				                                    </div>
				                                </td>
				                                <td>
				                                    <div class="d-flex align-items-center">
				                                        <i class="fa fa-calendar-plus-o text-muted me-2"></i>
				                                        <span title="{$result->adddate}">{$result->adddate|date_format}</span>
				                                    </div>
				                                </td>
				                                <td class="text-center">
				                                    <span class="badge bg-success">
				                                        <i class="fa fa-download me-1"></i>{$release->grabs}
				                                    </span>
				                                </td>
				                                <td class="text-end">
				                                    <div class="btn-group btn-group-sm" role="group">
				                                        <a href="{{url("/admin/release-edit?id={$release->guid}")}}" class="btn btn-primary" data-bs-toggle="tooltip" title="Edit release">
				                                            <i class="fa fa-pencil"></i>
				                                        </a>
				                                        <a href="{{url("/admin/release-files?id={$release->guid}")}}" class="btn btn-info" data-bs-toggle="tooltip" title="View files">
				                                            <i class="fa fa-file-text-o"></i>
				                                        </a>
				                                        <a href="{{url("/admin/release-delete/{$release->guid}")}}" class="btn btn-danger" data-bs-toggle="tooltip" title="Delete release" onclick="return confirm('Are you sure you want to delete this release?')">
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
				                <i class="fa fa-info-circle me-2"></i>No releases available.
				            </div>
				        {/if}
				    </div>

				    {if $releaselist}
				        <div class="card-footer">
				            <div class="d-flex justify-content-between align-items-center">
				                <div>
				                    Showing {$releaselist->firstItem()} to {$releaselist->lastItem()} of {$releaselist->total()} releases
				                </div>
				                <div class="pagination-container overflow-auto">
				                    {$releaselist->onEachSide(5)->links()}
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
				    #release-search-form .row {
				        margin-right: 0;
				        margin-left: 0;
				    }

				    #release-search-form .col-md-6 {
				        padding-right: 0;
				        padding-left: 0;
				    }
				}
				{/literal}
				</style>
