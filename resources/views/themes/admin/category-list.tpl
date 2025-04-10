<div class="card">
			    <div class="card-header">
			        <div class="d-flex justify-content-between align-items-center">
			            <h4 class="mb-0">{$title}</h4>
			            <a href="{{url("/admin/category-add")}}" class="btn btn-sm btn-primary">
			                <i class="fa fa-plus me-2"></i>Add New Category
			            </a>
			        </div>
			    </div>

			    <div class="card-body">
			        <div class="alert alert-info mb-4">
			            <i class="fa fa-info-circle me-2"></i>
			            Make a category inactive to remove it from the menu. This does not prevent binaries being matched into an
			            appropriate category. Disable preview prevents ffmpeg being used for releases in the category.
			        </div>

			        <div class="table-responsive">
			            <table class="table table-striped table-hover align-middle">
			                <thead class="thead-light">
			                    <tr>
			                        <th>
			                            <div class="d-flex align-items-center gap-2">
			                                <span>ID</span>
			                                <div class="sort-controls">
			                                    <a href="?sort=id&order=asc" class="sort-icon {if isset($sort) && $sort == 'id' && $order == 'asc'}active{/if}" title="Sort Ascending">
			                                        <i class="fas fa-sort-numeric-down"></i>
			                                    </a>
			                                    <a href="?sort=id&order=desc" class="sort-icon {if isset($sort) && $sort == 'id' && $order == 'desc'}active{/if}" title="Sort Descending">
			                                        <i class="fas fa-sort-numeric-down-alt"></i>
			                                    </a>
			                                </div>
			                            </div>
			                        </th>
			                        <th>
			                            <div class="d-flex align-items-center gap-2">
			                                <span>Title</span>
			                                <div class="sort-controls">
			                                    <a href="?sort=title&order=asc" class="sort-icon {if isset($sort) && $sort == 'title' && $order == 'asc'}active{/if}" title="Sort Ascending">
			                                        <i class="fas fa-sort-alpha-down"></i>
			                                    </a>
			                                    <a href="?sort=title&order=desc" class="sort-icon {if isset($sort) && $sort == 'title' && $order == 'desc'}active{/if}" title="Sort Descending">
			                                        <i class="fas fa-sort-alpha-down-alt"></i>
			                                    </a>
			                                </div>
			                            </div>
			                        </th>
			                        <th>Parent</th>
			                        <th>Min Size</th>
			                        <th>Max Size</th>
			                        <th class="text-center">Status</th>
			                        <th class="text-center">Preview</th>
			                        <th class="text-end">Actions</th>
			                    </tr>
			                </thead>
			                <tbody>
			                    {foreach $categorylist as $category}
			                        <tr>
			                            <td>{$category.id}</td>
			                            <td>
			                                <a href="{{url("/admin/category-edit?id={$category.id}")}}" class="fw-medium text-decoration-none">
			                                    {$category.title}
			                                </a>
			                            </td>
			                            <td>
			                                {if $category != null}
			                                    <span class="badge bg-secondary rounded-pill">
			                                        {$category.parent.title}
			                                    </span>
			                                {else}
			                                    <span class="text-muted">N/A</span>
			                                {/if}
			                            </td>
			                            <td>
			                                {if $category.minsizetoformrelease != 0}
			                                    <div class="d-flex align-items-center">
			                                        <i class="fa fa-file-archive-o text-muted me-2"></i>
			                                        <span>{$category.minsizetoformrelease|filesize}</span>
			                                    </div>
			                                {else}
			                                    <span class="text-muted">—</span>
			                                {/if}
			                            </td>
			                            <td>
			                                {if $category.maxsizetoformrelease != 0}
			                                    <div class="d-flex align-items-center">
			                                        <i class="fa fa-file-archive-o text-muted me-2"></i>
			                                        <span>{$category.maxsizetoformrelease|filesize}</span>
			                                    </div>
			                                {else}
			                                    <span class="text-muted">—</span>
			                                {/if}
			                            </td>
			                            <td class="text-center">
			                                <span class="badge {if $category.status == "1"}bg-success{else}bg-danger{/if}">
			                                    {if $category.status == "1"}Active{else}Inactive{/if}
			                                </span>
			                            </td>
			                            <td class="text-center">
			                                <span class="badge {if $category.disablepreview == "1"}bg-warning text-dark{else}bg-success{/if}">
			                                    {if $category.disablepreview == "1"}Disabled{else}Enabled{/if}
			                                </span>
			                            </td>
			                            <td class="text-end">
			                                <div class="btn-group" role="group">
			                                    <a href="{{url("/admin/category-edit?id={$category.id}")}}" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="Edit Category">
			                                        <i class="fa fa-edit"></i>
			                                    </a>
			                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete({$category.id})" data-bs-toggle="tooltip" title="Delete Category">
			                                        <i class="fa fa-trash"></i>
			                                    </button>
			                                </div>
			                            </td>
			                        </tr>
			                    {/foreach}
			                </tbody>
			            </table>
			        </div>

			        {if count($categorylist) == 0}
			            <div class="alert alert-info">
			                <i class="fa fa-info-circle me-2"></i>No categories found.
			            </div>
			        {/if}
			    </div>

			    <div class="card-footer">
			        <div class="d-flex justify-content-between align-items-center">
			            <div>
			                <span class="text-muted">{if isset($categorylist)}Total: {count($categorylist)} categories{else}No categories{/if}</span>
			            </div>
			            <div>
			                <a href="{{url("/admin/category-add")}}" class="btn btn-sm btn-primary">
			                    <i class="fa fa-plus me-2"></i>Add New Category
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
			                <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
			                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			            </div>
			            <div class="modal-body">
			                <p>Are you sure you want to delete this category? This may impact site functionality and cannot be undone.</p>
			                <p class="text-danger"><strong>Warning:</strong> Deleting a category with child categories or releases will cause orphaned data.</p>
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
			    deleteLink.href = `{{url("/admin/category-delete?id=")}}${id}`;
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

			    .d-flex.justify-content-between {
			        flex-direction: column;
			        gap: 1rem;
			    }

			    .d-flex.justify-content-between > div:last-child {
			        align-self: flex-start;
			    }
			}

			/* Improved hover effect */
			.table-hover tbody tr:hover {
			    background-color: rgba(0, 123, 255, 0.05) !important;
			}

			/* Gap utility (similar to Bootstrap 5) */
			.gap-2 {
			    gap: 0.5rem;
			}
			{/literal}
			</style>
