<div class="card">
				    <div class="card-header">
				        <div class="d-flex justify-content-between align-items-center">
				            <div>
				                <h4 class="mb-0">{$title}</h4>
				            </div>
				            <div>
				                <a href="{{url("/admin/category_regexes-edit")}}" class="btn btn-sm btn-primary">
				                    <i class="fa fa-plus me-2"></i>Add New Regex
				                </a>
				            </div>
				        </div>
				    </div>

				    <div class="card-body">
				        <div class="alert alert-info mb-4">
				            <div class="d-flex">
				                <div class="me-3">
				                    <i class="fa fa-info-circle fa-2x"></i>
				                </div>
				                <div>
				                    <p class="mb-0">
				                        This page lists regular expressions used for categorizing releases.<br>
				                        You can recategorize all releases by running <code>misc/update/update_releases 6 true</code>
				                    </p>
				                </div>
				            </div>
				        </div>

				        <div id="message"></div>

				        <div class="row mb-4">
				            <div class="col-md-6">
				                <form name="groupsearch" action="" method="get">
				                    {{csrf_field()}}
				                    <div class="input-group">
				                        <span class="input-group-text"><i class="fa fa-search"></i></span>
				                        <input id="group" type="text" name="group" value="{$group}" class="form-control" placeholder="Search a group...">
				                        <button class="btn btn-primary" type="submit">Search</button>
				                    </div>
				                </form>
				            </div>
				        </div>

				        {if $regex}
				            <div class="mb-3">
				                {$regex->onEachSide(5)->links()}
				            </div>
				            <div class="table-responsive">
				                <table class="table table-striped table-hover align-middle">
				                    <thead class="thead-light">
				                        <tr>
				                            <th style="width: 70px">ID</th>
				                            <th>Group</th>
				                            <th>Description</th>
				                            <th>Regex</th>
				                            <th style="width: 90px">Ordinal</th>
				                            <th style="width: 100px">Status</th>
				                            <th style="width: 100px">Category</th>
				                            <th style="width: 120px" class="text-end">Actions</th>
				                        </tr>
				                    </thead>
				                    <tbody>
				                        {foreach from=$regex item=row}
				                            <tr id="row-{$row.id}">
				                                <td>{$row.id}</td>
				                                <td>
				                                    <code class="text-primary">{$row.group_regex}</code>
				                                </td>
				                                <td>
				                                    <span data-bs-toggle="tooltip" title="{$row.description}">{$row.description|truncate:50:"...":true}</span>
				                                </td>
				                                <td>
				                                    <code class="regex-code" data-bs-toggle="tooltip" title="{$row.regex|escape:html}">{$row.regex|escape:html|truncate:50:"...":true}</code>
				                                </td>
				                                <td class="text-center">{$row.ordinal}</td>
				                                <td class="text-center">
				                                    {if $row.status==1}
				                                        <span class="badge bg-success"><i class="fa fa-check-circle me-1"></i>Active</span>
				                                    {else}
				                                        <span class="badge bg-danger"><i class="fa fa-times-circle me-1"></i>Disabled</span>
				                                    {/if}
				                                </td>
				                                <td class="text-center">
				                                    <span class="badge bg-secondary rounded-pill">
				                                        <i class="fa fa-folder-open me-1"></i>{$row.categories_id}
				                                    </span>
				                                </td>
				                                <td class="text-end">
				                                    <div class="btn-group">
				                                        <a href="{{url("/admin/category_regexes-edit?id={$row.id}")}}" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Edit this regex">
				                                            <i class="fa fa-edit"></i>
				                                        </a>
				                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete({$row.id})" data-bs-toggle="tooltip" title="Delete this regex">
				                                            <i class="fa fa-trash"></i>
				                                        </button>
				                                    </div>
				                                </td>
				                            </tr>
				                        {/foreach}
				                    </tbody>
				                </table>
				            </div>
				            <div class="mt-4">
				                {$regex->onEachSide(5)->links()}
				            </div>
				        {else}
				            <div class="alert alert-warning">
				                <i class="fa fa-exclamation-triangle me-2"></i>No regex patterns found. Try a different search term or add a new regex.
				            </div>
				        {/if}
				    </div>

				    <div class="card-footer">
				        <div class="d-flex justify-content-between align-items-center">
				            <div>
				                <span class="text-muted">{if $regex}Total entries: {$regex->total()}{else}No entries{/if}</span>
				            </div>
				            <div>
				                <a href="{{url("/admin/category_regexes-edit")}}" class="btn btn-sm btn-primary">
				                    <i class="fa fa-plus me-2"></i>Add New Regex
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
				                <p>Are you sure you want to delete this regex? This action cannot be undone.</p>
				            </div>
				            <div class="modal-footer">
				                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
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

				    // Flash messages
				    window.showMessage = function(message, type = 'success') {
				        const messageDiv = document.getElementById('message');
				        messageDiv.innerHTML = `
				            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
				                <i class="fa ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'} me-2"></i>${message}
				                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
				            </div>
				        `;

				        // Auto hide after 5 seconds
				        setTimeout(() => {
				            const alert = messageDiv.querySelector('.alert');
				            if (alert) {
				                const bsAlert = new bootstrap.Alert(alert);
				                bsAlert.close();
				            }
				        }, 5000);
				    };
				});

				// Confirm delete modal
				let deleteId = null;
				const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));

				function confirmDelete(id) {
				    deleteId = id;
				    deleteModal.show();
				}

				document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
				    if (deleteId !== null) {
				        ajax_category_regex_delete(deleteId);
				        deleteModal.hide();
				    }
				});

				// AJAX delete function
				window.ajax_category_regex_delete = function(id) {
				    const xhr = new XMLHttpRequest();
				    xhr.open('GET', `{{url("/admin/category_regexes-delete?id=")}}`+id, true);
				    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

				    xhr.onload = function() {
				        if (xhr.status === 200) {
				            // Remove the row from the table
				            const row = document.getElementById('row-'+id);
				            if (row) {
				                row.style.transition = 'opacity 0.5s';
				                row.style.opacity = '0';
				                setTimeout(() => {
				                    row.remove();
				                }, 500);
				            }
				            showMessage('Regex successfully deleted', 'success');
				        } else {
				            showMessage('Error deleting regex. Please try again.', 'danger');
				        }
				    };

				    xhr.onerror = function() {
				        showMessage('Error deleting regex. Please try again.', 'danger');
				    };

				    xhr.send();
				};
				{/literal}
				</script>

				<style>
				{literal}
				/* Code styling for regex */
				code {
				    background-color: rgba(0, 0, 0, 0.05);
				    padding: 2px 5px;
				    border-radius: 3px;
				    font-family: monospace;
				    word-break: break-all;
				    font-size: 0.85rem;
				}

				.regex-code {
				    max-width: 300px;
				    display: inline-block;
				    overflow: hidden;
				    text-overflow: ellipsis;
				    white-space: nowrap;
				}

				/* Table improvements */
				.table td {
				    vertical-align: middle;
				}

				/* Badge styling */
				.badge {
				    font-weight: 500;
				    padding: 0.4em 0.6em;
				}

				/* Better button spacing */
				.btn-group > .btn {
				    padding: 0.25rem 0.5rem;
				}

				/* Responsive adjustments */
				@media (max-width: 767.98px) {
				    .regex-code {
				        max-width: 150px;
				    }

				    .table th, .table td {
				        padding: 0.5rem;
				    }
				}

				/* Improved hover effect */
				.table-hover tbody tr:hover {
				    background-color: rgba(0, 123, 255, 0.05) !important;
				}

				/* Pagination styling */
				.pagination {
				    margin-bottom: 0;
				}
				{/literal}
				</style>
