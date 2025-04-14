<div class="card">
		    <div class="card-header">
		        <div class="d-flex justify-content-between align-items-center">
		            <h4 class="mb-0">{$title}</h4>
		            <a href="{{url("/admin/anidb-add")}}" class="btn btn-outline-success">
		                <i class="fa fa-plus me-2"></i>Add New AniDB Entry
		            </a>
		        </div>
		    </div>

		    <div class="card-body">
		        <form name="anidbsearch" method="get" action="{{url("/admin/anidb-list")}}" id="anidb-search-form" class="mb-4">
		            {{csrf_field()}}
		            <div class="row">
		                <div class="col-md-6 offset-md-6">
		                    <div class="input-group">
		                        <input type="text" class="form-control" placeholder="Search by anime title"
		                               id="animetitle" name="animetitle" value="{$animetitle|escape:'html'}">
		                        <button type="submit" class="btn btn-primary">
		                            <i class="fa fa-search me-2"></i>Search
		                        </button>
		                    </div>
		                </div>
		            </div>
		        </form>

		        {if $anidblist}
		            <div class="table-responsive">
		                <table class="table table-striped table-hover align-middle">
		                    <thead class="thead-light">
		                        <tr>
		                            <th>AniDB ID</th>
		                            <th>Title</th>
		                            <th class="text-end">Actions</th>
		                        </tr>
		                    </thead>
		                    <tbody>
		                        {foreach from=$anidblist item=anidb}
		                            <tr>
		                                <td>
		                                    <a href="http://anidb.net/perl-bin/animedb.pl?show=anime&amp;aid={$anidb->anidbid}"
		                                       target="_blank" class="badge bg-secondary text-white" title="View in AniDB">
		                                        <i class="fa fa-film me-1"></i>{$anidb->anidbid}
		                                    </a>
		                                </td>
		                                <td>
		                                    <div class="mb-1">
		                                        <a href="{{url("/admin/anidb-edit?id={$anidb->anidbid}")}}" class="title fw-semibold">
		                                            {$anidb->title|escape:"htmlall"}
		                                        </a>
		                                    </div>
		                                </td>
		                                <td class="text-end">
		                                    <div class="btn-group btn-group-sm" role="group">
		                                        <a href="{{url("/admin/anidb-edit?id={$anidb->anidbid}")}}" class="btn btn-primary" data-bs-toggle="tooltip" title="Edit this anime">
		                                            <i class="fa fa-pencil"></i>
		                                        </a>
		                                        <a href="{{url("/admin/anidb-remove?id={$anidb->anidbid}")}}" class="btn btn-warning" data-bs-toggle="tooltip" title="Remove from releases" onclick="return confirm('Are you sure you want to remove this AniDB ID from all releases?')">
		                                            <i class="fa fa-unlink"></i>
		                                        </a>
		                                        <a href="{{url("/admin/anidb-delete?id={$anidb->anidbid}")}}" class="btn btn-danger" data-bs-toggle="tooltip" title="Delete anime" onclick="return confirm('Are you sure you want to delete this AniDB entry?')">
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
		                <i class="fa fa-info-circle me-2"></i>No AniDB entries available. Use the Add New AniDB Entry button to add some.
		            </div>
		        {/if}
		    </div>

		    {if $anidblist}
		        <div class="card-footer">
		            <div class="d-flex justify-content-between align-items-center">
		                <div>
		                    Showing {$anidblist->firstItem()} to {$anidblist->lastItem()} of {$anidblist->total()} anime entries
		                </div>
		                <div class="pagination-container overflow-auto">
		                    {$anidblist->onEachSide(5)->links()}
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
		    #anidb-search-form .row {
		        margin-right: 0;
		        margin-left: 0;
		    }

		    #anidb-search-form .col-md-6 {
		        padding-right: 0;
		        padding-left: 0;
		    }
		}
		{/literal}
		</style>
