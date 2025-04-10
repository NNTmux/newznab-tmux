<div class="card">
			    <div class="card-header">
			        <div class="d-flex justify-content-between align-items-center">
			            <div>
			                <h4 class="mb-0">{$title}</h4>
			            </div>
			            <div>
			                <a href="{{url("/admin/content-add")}}" class="btn btn-sm btn-primary">
			                    <i class="fa fa-plus me-2"></i>Add New Content
			                </a>
			            </div>
			        </div>
			    </div>

			    <div class="card-body">
			        <div class="row mb-4">
			            <div class="col-md-6">
			                <form name="contentsearch" action="" method="get">
			                    {{csrf_field()}}
			                    <div class="input-group">
			                        <span class="input-group-text"><i class="fa fa-search"></i></span>
			                        <input id="search" type="text" name="search" value="{$search|default:''}" class="form-control" placeholder="Search by title...">
			                        <button class="btn btn-primary" type="submit">Search</button>
			                    </div>
			                </form>
			            </div>
			            <div class="col-md-6 d-flex justify-content-md-end mt-3 mt-md-0">
			                <div class="btn-group">
			                    <a href="{{url("/admin/content-list")}}" class="btn btn-outline-secondary {if $status == 'all' || !$status}active{/if}">All</a>
			                    <a href="{{url("/admin/content-list?status=1")}}" class="btn btn-outline-secondary {if $status == '1'}active{/if}">Enabled</a>
			                    <a href="{{url("/admin/content-list?status=0")}}" class="btn btn-outline-secondary {if $status == '0'}active{/if}">Disabled</a>
			                </div>
			            </div>
			        </div>

			        {if count($contentlist) > 0}
			            <div class="table-responsive">
			                <table class="table table-striped table-hover align-middle">
			                    <thead class="thead-light">
			                        <tr>
			                            <th style="width:60px;">
			                                <div class="d-flex align-items-center">
			                                    <span>Ordinal</span>
			                                    <div class="sort-controls ms-2">
			                                        <a href="?sort=ordinal&dir=asc" class="sort-icon"><i class="fas fa-sort-numeric-down"></i></a>
			                                        <a href="?sort=ordinal&dir=desc" class="sort-icon"><i class="fas fa-sort-numeric-down-alt"></i></a>
			                                    </div>
			                                </div>
			                            </th>
			                            <th style="width:50px;">ID</th>
			                            <th>
			                                <div class="d-flex align-items-center">
			                                    <span>Title</span>
			                                    <div class="sort-controls ms-2">
			                                        <a href="?sort=title&dir=asc" class="sort-icon"><i class="fas fa-sort-alpha-down"></i></a>
			                                        <a href="?sort=title&dir=desc" class="sort-icon"><i class="fas fa-sort-alpha-down-alt"></i></a>
			                                    </div>
			                                </div>
			                            </th>
			                            <th>URL</th>
			                            <th>Type</th>
			                            <th>Status</th>
			                            <th>Role</th>
			                            <th>In Menu</th>
			                            <th>Body</th>
			                            <th class="text-end">Actions</th>
			                        </tr>
			                    </thead>
			                    <tbody>
			                        {foreach from=$contentlist item=content}
			                            <tr id="row-{$content->id}">
			                                <td>{$content->ordinal}</td>
			                                <td>{$content->id}</td>
			                                <td>
			                                    <a href="{{url("/admin/content-add?id={$content->id}")}}" class="fw-semibold text-decoration-none">
			                                        {$content->title}
			                                    </a>
			                                </td>
			                                <td>
			                                    <a href="{{url("/{$content->url}c{$content->id}")}}" target="_blank" class="d-flex align-items-center text-decoration-none" data-bs-toggle="tooltip" title="Preview in new window">
			                                        <span class="text-truncate" style="max-width: 200px;">{$content->url}c{$content->id}</span>
			                                        <i class="fa fa-external-link-alt ms-2 text-muted"></i>
			                                    </a>
			                                </td>
			                                <td>
			                                    {if $content->contenttype == "1"}
			                                        <span class="badge bg-info">Useful Link</span>
			                                    {elseif $content->contenttype == "2"}
			                                        <span class="badge bg-primary">Article</span>
			                                    {elseif $content->contenttype == "3"}
			                                        <span class="badge bg-success">Homepage</span>
			                                    {/if}
			                                </td>
			                                <td>
			                                    {if $content->status == "1"}
			                                        <span class="badge bg-success">Enabled</span>
			                                    {else}
			                                        <span class="badge bg-secondary">Disabled</span>
			                                    {/if}
			                                </td>
			                                <td>
			                                    {if $content->role == "0"}
			                                        <span class="badge bg-dark">Everyone</span>
			                                    {elseif $content->role == "1"}
			                                        <span class="badge bg-primary">Users</span>
			                                    {elseif $content->role == "2"}
			                                        <span class="badge bg-warning text-dark">Admins</span>
			                                    {/if}
			                                </td>
			                                <td>
			                                    {if $content->showinmenu == "1"}
			                                        <span class="badge bg-success"><i class="fa fa-check me-1"></i>Yes</span>
			                                    {else}
			                                        <span class="badge bg-secondary"><i class="fa fa-times me-1"></i>No</span>
			                                    {/if}
			                                </td>
			                                <td>
			                                    <span class="text-truncate d-inline-block" style="max-width: 200px;" data-bs-toggle="tooltip" title="{$content->body|escape:'htmlall'}">{$content->body|truncate:100|escape:'htmlall'}</span>
			                                </td>
			                                <td class="text-end">
			                                    <div class="btn-group">
			                                        <a href="{{url("/admin/content-add?id={$content->id}")}}" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="Edit content">
			                                            <i class="fa fa-edit"></i>
			                                        </a>
			                                        <a href="{{url("/{$content->url}c{$content->id}")}}" target="_blank" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Preview content">
			                                            <i class="fa fa-eye"></i>
			                                        </a>
			                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete({$content->id})" data-bs-toggle="tooltip" title="Delete content">
			                                            <i class="fa fa-trash-alt"></i>
			                                        </button>
			                                    </div>
			                                </td>
			                            </tr>
			                        {/foreach}
			                    </tbody>
			                </table>
			            </div>

			            {if isset($pagination)}
			                <div class="mt-4">
			                    {$pagination}
			                </div>
			            {/if}
			        {else}
			            <div class="alert alert-info">
			                <i class="fa fa-info-circle me-2"></i>No content items found. Try a different search term or add new content.
			            </div>
			        {/if}
			    </div>

			    <div class="card-footer">
			        <div class="d-flex justify-content-between align-items-center">
			            <div>
			                <span class="text-muted">{if isset($contentlist)}Total entries: {count($contentlist)}{else}No entries{/if}</span>
			            </div>
			            <div>
			                <a href="{{url("/admin/content-add")}}" class="btn btn-sm btn-primary">
			                    <i class="fa fa-plus me-2"></i>Add New Content
			                </a>
			            </div>
			        </div>
			    </div>
			</div>

			<!-- Delete Confirmation Modal -->
			<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
			    <div class="modal-dialog">
			        <div class="modal-content">
			            <div class="modal-header">
			                <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
			                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			            </div>
			            <div class="modal-body">
			                <p>Are you sure you want to delete this content item? This action cannot be undone.</p>
			            </div>
			            <div class="modal-footer">
			                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
			                <a href="#" id="confirmDeleteLink" class="btn btn-danger">Delete</a>
			            </div>
			        </div>
			    </div>
			</div>

			<script>
			{literal}
			document.addEventListener('DOMContentLoaded', function() {
			    // Initialize tooltips
			    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
			    tooltipTriggerList.map(function (tooltipTriggerEl) {
			        return new bootstrap.Tooltip(tooltipTriggerEl);
			    });
			});

			// Delete confirmation handling
			function confirmDelete(id) {
			    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
			    const deleteLink = document.getElementById('confirmDeleteLink');
			    deleteLink.href = `{{url("/admin/content-delete?id=")}}${id}`;
			    modal.show();
			}
			{/literal}
			</script>

			<style>
			{literal}
			/* Table improvements */
			.table td, .table th {
			    vertical-align: middle;
			}

			/* Sort controls styling */
			.sort-icon {
			    color: #aaa;
			    font-size: 0.8rem;
			    display: block;
			    line-height: 0.8;
			}
			.sort-icon.active {
			    color: #0d6efd;
			}
			.sort-controls {
			    display: flex;
			    flex-direction: column;
			}

			/* Badge styling */
			.badge {
			    font-weight: 500;
			    padding: 0.4em 0.6em;
			}

			/* Responsive adjustments */
			@media (max-width: 767.98px) {
			    .table th, .table td {
			        padding: 0.5rem;
			    }

			    .btn-group > .btn {
			        padding: 0.25rem 0.5rem;
			    }
			}

			/* Improved hover effect */
			.table-hover tbody tr:hover {
			    background-color: rgba(0, 123, 255, 0.05) !important;
			}
			{/literal}
			</style>
