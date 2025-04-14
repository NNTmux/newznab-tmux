<div class="card">
		    <div class="card-header">
		        <div class="d-flex justify-content-between align-items-center">
		            <h4 class="mb-0">{$title}</h4>
		            <a href="{{url("/admin/game-add")}}" class="btn btn-outline-success">
		                <i class="fa fa-plus me-2"></i>Add New Game
		            </a>
		        </div>
		    </div>

		    <div class="card-body">
		        <form name="gamesearch" method="get" action="{{url("/admin/game-list")}}" id="game-search-form" class="mb-4">
		            {{csrf_field()}}
		            <div class="row">
		                <div class="col-md-6 offset-md-6">
		                    <div class="input-group">
		                        <input type="text" class="form-control" placeholder="Search by title or genre"
		                               id="gamesearch" name="gamesearch" value="{$lastSearch|escape:'html'}">
		                        <button type="submit" class="btn btn-primary">
		                            <i class="fa fa-search me-2"></i>Search
		                        </button>
		                    </div>
		                </div>
		            </div>
		        </form>

		        {if $gamelist}
		            <div class="table-responsive">
		                <table class="table table-striped table-hover align-middle">
		                    <thead class="thead-light">
		                        <tr>
		                            <th>ID</th>
		                            <th>Title</th>
		                            <th>Genre</th>
		                            <th>Created</th>
		                            <th class="text-end">Actions</th>
		                        </tr>
		                    </thead>
		                    <tbody>
		                        {foreach from=$gamelist item=game}
		                            <tr>
		                                <td>
		                                    <span class="badge bg-secondary">{$game.id}</span>
		                                </td>
		                                <td>
		                                    <div class="mb-1">
		                                        <a href="{{url("/admin/game-edit?id={$game.id}")}}" class="title fw-semibold">
		                                            {$game.title|escape:"htmlall"}
		                                        </a>
		                                    </div>
		                                </td>
		                                <td>
		                                    <span class="badge bg-info">{$game.genretitle|escape:"htmlall"}</span>
		                                </td>
		                                <td>
		                                    <div class="d-flex align-items-center">
		                                        <i class="fa fa-calendar text-muted me-2"></i>
		                                        <span title="{$game.created_at}">{$game.created_at|date_format}</span>
		                                    </div>
		                                </td>
		                                <td class="text-end">
		                                    <div class="btn-group btn-group-sm" role="group">
		                                        <a href="{{url("/admin/game-edit?id={$game.id}")}}" class="btn btn-primary" data-bs-toggle="tooltip" title="Edit this game">
		                                            <i class="fa fa-pencil"></i>
		                                        </a>
		                                        <a href="{{url("/admin/game-update?id={$game.id}")}}" class="btn btn-warning" data-bs-toggle="tooltip" title="Update metadata">
		                                            <i class="fa fa-refresh"></i>
		                                        </a>
		                                        <a href="{{url("/admin/game-delete?id={$game.id}")}}" class="btn btn-danger" data-bs-toggle="tooltip" title="Delete game" onclick="return confirm('Are you sure you want to delete this game?')">
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
		                <i class="fa fa-info-circle me-2"></i>No games available. Use the Add New Game button to add some.
		            </div>
		        {/if}
		    </div>

		    {if $gamelist}
		        <div class="card-footer">
		            <div class="d-flex justify-content-between align-items-center">
		                <div>
		                    Showing {$gamelist->firstItem()} to {$gamelist->lastItem()} of {$gamelist->total()} games
		                </div>
		                <div class="pagination-container overflow-auto">
		                    {$gamelist->onEachSide(5)->links()}
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
		    #game-search-form .row {
		        margin-right: 0;
		        margin-left: 0;
		    }

		    #game-search-form .col-md-6 {
		        padding-right: 0;
		        padding-left: 0;
		    }
		}
		{/literal}
		</style>
