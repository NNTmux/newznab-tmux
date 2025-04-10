<div class="card">
				    <div class="card-header">
				        <div class="d-flex justify-content-between align-items-center">
				            <h4 class="mb-0">{$title}</h4>
				            <div class="d-flex gap-2">
				                <a href="{{url("/admin/group-list-active")}}" class="btn btn-sm btn-outline-primary">
				                    <i class="fa fa-check-circle me-2"></i>Active Groups
				                </a>
				                <a href="{{url("/admin/group-list-inactive")}}" class="btn btn-sm btn-outline-secondary">
				                    <i class="fa fa-times-circle me-2"></i>Inactive Groups
				                </a>
				                <a href="{{url("/admin/group-list")}}" class="btn btn-sm btn-outline-info">
				                    <i class="fa fa-list me-2"></i>All Groups
				                </a>
				            </div>
				        </div>
				    </div>

				    <div class="card-body">
				        <div class="alert alert-info mb-4">
				            <i class="fa fa-info-circle me-2"></i>
				            Below is a list of all usenet groups available to be indexed. Click 'Activate' to start indexing a group.
				            Backfill works independently of active.
				        </div>

				        {if isset($msg) && $msg != ''}
				            <div class="alert alert-success" id="message">{$msg}</div>
				        {/if}

				        {if $grouplist}
				            <div class="row mb-4">
				                <div class="col-lg-4 col-md-6">
				                    {{Form::open(['name' => 'groupsearch', 'class' => 'mb-0'])}}
				                        <div class="input-group">
				                            <span class="input-group-text"><i class="fa fa-search"></i></span>
				                            <input id="groupname" type="text" name="groupname" value="{$groupname}" class="form-control" placeholder="Search for group...">
				                            <button type="submit" class="btn btn-primary">Go</button>
				                        </div>
				                    {{Form::close()}}
				                </div>
				                <div class="col-lg-4 col-md-6 d-flex justify-content-center align-items-center">
				                    <div class="pagination-container overflow-auto w-100 d-flex justify-content-center">
				                        {$grouplist->onEachSide(5)->links()}
				                    </div>
				                </div>
				                <div class="col-lg-4 col-md-12 d-flex flex-column justify-content-center align-items-lg-end align-items-center mt-3 mt-lg-0">
				                    <div class="btn-group" role="group">
				                        <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#resetAllModal">
				                            <i class="fa fa-refresh me-1"></i> Reset All
				                        </button>
				                        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#purgeAllModal">
				                            <i class="fa fa-trash me-1"></i> Purge All
				                        </button>
				                    </div>
				                </div>
				            </div>

				            <div class="table-responsive">
				                <table class="table table-striped table-hover align-middle">
				                    <thead class="thead-light">
				                        <tr>
				                            <th>
				                                <div class="d-flex align-items-center">
				                                    <span>Group</span>
				                                </div>
				                            </th>
				                            <th>First Post</th>
				                            <th>Last Post</th>
				                            <th>Last Updated</th>
				                            <th class="text-center">Status</th>
				                            <th class="text-center">Backfill</th>
				                            <th class="text-center">Releases</th>
				                            <th class="text-center">Min Files</th>
				                            <th class="text-center">Min Size</th>
				                            <th class="text-center">Backfill Days</th>
				                            <th class="text-center">Actions</th>
				                        </tr>
				                    </thead>
				                    <tbody>
				                        {foreach from=$grouplist item=group}
				                            <tr id="grouprow-{$group.id}">
				                                <td>
				                                    <a href="{{url("/admin/group-edit?id={$group.id}")}}" class="text-decoration-none fw-semibold">{$group.name|replace:"alt.binaries":"a.b"}</a>
				                                    <div class="text-muted small">{$group.description}</div>
				                                </td>
				                                <td>
				                                    <div class="d-flex flex-column">
				                                        <span>{$group.first_record_postdate}</span>
				                                        <small class="text-muted">{$group.first_record_postdate|timeago}</small>
				                                    </div>
				                                </td>
				                                <td>{$group.last_record_postdate}</td>
				                                <td>
				                                    <span data-bs-toggle="tooltip" title="{$group.last_updated}">{$group.last_updated|timeago} ago</span>
				                                </td>
				                                <td class="text-center" id="group-{$group.id}">
				                                    {if $group.active=="1"}
				                                        <button type="button" onclick="ajax_group_status({$group.id}, 0)" class="btn btn-sm btn-success">
				                                            <i class="fa fa-check-circle me-1"></i>Active
				                                        </button>
				                                    {else}
				                                        <button type="button" onclick="ajax_group_status({$group.id}, 1)" class="btn btn-sm btn-outline-secondary">
				                                            <i class="fa fa-times-circle me-1"></i>Inactive
				                                        </button>
				                                    {/if}
				                                </td>
				                                <td class="text-center" id="backfill-{$group.id}">
				                                    {if $group.backfill=="1"}
				                                        <button type="button" onclick="ajax_backfill_status({$group.id}, 0)" class="btn btn-sm btn-info">
				                                            <i class="fa fa-check-circle me-1"></i>Enabled
				                                        </button>
				                                    {else}
				                                        <button type="button" onclick="ajax_backfill_status({$group.id}, 1)" class="btn btn-sm btn-outline-secondary">
				                                            <i class="fa fa-times-circle me-1"></i>Disabled
				                                        </button>
				                                    {/if}
				                                </td>
				                                <td class="text-center">
				                                    <span class="badge bg-secondary">{$group.num_releases}</span>
				                                </td>
				                                <td class="text-center">
				                                    {if $group.minfilestoformrelease==""}
				                                        <span class="text-muted">n/a</span>
				                                    {else}
				                                        <span class="badge bg-secondary">{$group.minfilestoformrelease}</span>
				                                    {/if}
				                                </td>
				                                <td class="text-center">
				                                    {if $group.minsizetoformrelease==""}
				                                        <span class="text-muted">n/a</span>
				                                    {else}
				                                        <span class="badge bg-secondary">{$group.minsizetoformrelease|filesize}</span>
				                                    {/if}
				                                </td>
				                                <td class="text-center">
				                                    <span class="badge bg-secondary">{$group.backfill_target}</span>
				                                </td>
				                                <td class="text-center" id="groupdel-{$group.id}">
				                                    <div class="btn-group btn-group-sm" role="group">
				                                        <a href="{{url("/admin/group-edit?id={$group.id}")}}" class="btn btn-primary" data-bs-toggle="tooltip" title="Edit this group">
				                                            <i class="fa fa-pencil"></i>
				                                        </a>
				                                        <button type="button" onclick="ajax_group_reset({$group.id})" class="btn btn-warning" data-bs-toggle="tooltip" title="Reset this group">
				                                            <i class="fa fa-refresh"></i>
				                                        </button>
				                                        <button type="button" onclick="confirmGroupDelete({$group.id})" class="btn btn-danger" data-bs-toggle="tooltip" title="Delete this group">
				                                            <i class="fa fa-trash"></i>
				                                        </button>
				                                        <button type="button" onclick="confirmGroupPurge({$group.id})" class="btn btn-danger" data-bs-toggle="tooltip" title="Purge this group">
				                                            <i class="fa fa-eraser"></i>
				                                        </button>
				                                    </div>
				                                </td>
				                            </tr>
				                        {/foreach}
				                    </tbody>
				                </table>
				            </div>
				        {else}
				            <div class="alert alert-warning">
				                <i class="fa fa-exclamation-triangle me-2"></i>No groups available (eg. none have been added).
				            </div>
				        {/if}
				    </div>

				    {if $grouplist}
				        <div class="card-footer">
				            <div class="row">
				                <div class="col-lg-4 col-md-6">
				                    {{Form::open(['name' => 'groupsearch', 'class' => 'mb-0'])}}
				                        <div class="input-group">
				                            <span class="input-group-text"><i class="fa fa-search"></i></span>
				                            <input id="groupname" type="text" name="groupname" value="{$groupname}" class="form-control" placeholder="Search for group...">
				                            <button type="submit" class="btn btn-primary">Go</button>
				                        </div>
				                    {{Form::close()}}
				                </div>
				                <div class="col-lg-4 col-md-6 d-flex justify-content-center align-items-center mt-3 mt-lg-0">
				                    <div class="pagination-container overflow-auto w-100 d-flex justify-content-center">
				                        {$grouplist->onEachSide(5)->links()}
				                    </div>
				                </div>
				                <div class="col-lg-4 col-md-12 d-flex justify-content-lg-end justify-content-center mt-3 mt-lg-0">
				                    <div class="text-muted">
				                        Showing {$grouplist->count()} of {$grouplist->total()} groups
				                    </div>
				                </div>
				            </div>
				        </div>
				    {/if}
				</div>

				<!-- Reset All Confirmation Modal -->
				<div class="modal fade" id="resetAllModal" tabindex="-1" aria-labelledby="resetAllModalLabel" aria-hidden="true">
				    <div class="modal-dialog">
				        <div class="modal-content">
				            <div class="modal-header">
				                <h5 class="modal-title" id="resetAllModalLabel">Confirm Reset All Groups</h5>
				                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				            </div>
				            <div class="modal-body">
				                <div class="alert alert-warning">
				                    <i class="fa fa-exclamation-triangle me-2"></i>
				                    <strong>Warning:</strong> This will reset all groups, deleting all collections/binaries/parts. This action does not delete releases.
				                </div>
				                <p>Are you sure you want to proceed?</p>
				            </div>
				            <div class="modal-footer">
				                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				                <button type="button" class="btn btn-warning" onclick="ajax_all_reset()">Reset All Groups</button>
				            </div>
				        </div>
				    </div>
				</div>

				<!-- Purge All Confirmation Modal -->
				<div class="modal fade" id="purgeAllModal" tabindex="-1" aria-labelledby="purgeAllModalLabel" aria-hidden="true">
				    <div class="modal-dialog">
				        <div class="modal-content">
				            <div class="modal-header">
				                <h5 class="modal-title" id="purgeAllModalLabel">Confirm Purge All Groups</h5>
				                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				            </div>
				            <div class="modal-body">
				                <div class="alert alert-danger">
				                    <i class="fa fa-exclamation-triangle me-2"></i>
				                    <strong>Warning:</strong> This will delete all releases, collections/binaries/parts from all groups.
				                </div>
				                <p>This action cannot be undone. Are you sure you want to proceed?</p>
				            </div>
				            <div class="modal-footer">
				                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				                <button type="button" class="btn btn-danger" onclick="ajax_all_purge()">Purge All Groups</button>
				            </div>
				        </div>
				    </div>
				</div>

				<!-- Delete Group Confirmation Modal -->
				<div class="modal fade" id="deleteGroupModal" tabindex="-1" aria-labelledby="deleteGroupModalLabel" aria-hidden="true">
				    <div class="modal-dialog">
				        <div class="modal-content">
				            <div class="modal-header">
				                <h5 class="modal-title" id="deleteGroupModalLabel">Confirm Delete Group</h5>
				                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				            </div>
				            <div class="modal-body">
				                <div class="alert alert-danger">
				                    <i class="fa fa-exclamation-triangle me-2"></i>
				                    <strong>Warning:</strong> This will delete the group from this list.
				                </div>
				                <p>Are you sure you want to proceed?</p>
				            </div>
				            <div class="modal-footer">
				                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete Group</button>
				            </div>
				        </div>
				    </div>
				</div>

				<!-- Purge Group Confirmation Modal -->
				<div class="modal fade" id="purgeGroupModal" tabindex="-1" aria-labelledby="purgeGroupModalLabel" aria-hidden="true">
				    <div class="modal-dialog">
				        <div class="modal-content">
				            <div class="modal-header">
				                <h5 class="modal-title" id="purgeGroupModalLabel">Confirm Purge Group</h5>
				                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				            </div>
				            <div class="modal-body">
				                <div class="alert alert-danger">
				                    <i class="fa fa-exclamation-triangle me-2"></i>
				                    <strong>Warning:</strong> This will delete all releases, binaries/parts in the selected group.
				                </div>
				                <p>This action cannot be undone. Are you sure you want to proceed?</p>
				            </div>
				            <div class="modal-footer">
				                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				                <button type="button" class="btn btn-danger" id="confirmPurgeBtn">Purge Group</button>
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

				// Group delete confirmation
				function confirmGroupDelete(id) {
				    const modal = new bootstrap.Modal(document.getElementById('deleteGroupModal'));
				    const confirmBtn = document.getElementById('confirmDeleteBtn');

				    confirmBtn.onclick = function() {
				        ajax_group_delete(id);
				        modal.hide();
				    };

				    modal.show();
				}

				// Group purge confirmation
				function confirmGroupPurge(id) {
				    const modal = new bootstrap.Modal(document.getElementById('purgeGroupModal'));
				    const confirmBtn = document.getElementById('confirmPurgeBtn');

				    confirmBtn.onclick = function() {
				        ajax_group_purge(id);
				        modal.hide();
				    };

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

				/* Badge styling */
				.badge {
				    font-weight: 500;
				    padding: 0.4em 0.6em;
				}

				/* Button improvements */
				.btn-group-sm > .btn {
				    padding: 0.25rem 0.5rem;
				    font-size: 0.875rem;
				}

				/* Gap utility (similar to Bootstrap 5) */
				.gap-2 {
				    gap: 0.5rem !important;
				}

				/* Responsive adjustments */
				@media (max-width: 767.98px) {
				    .table th, .table td {
				        padding: 0.5rem;
				    }

				    .card-header .d-flex {
				        flex-direction: column;
				        gap: 0.5rem;
				    }

				    .card-header .d-flex > div {
				        margin-top: 0.5rem;
				    }

				    .pagination-container {
				        overflow-x: auto;
				        justify-content: start !important;
				    }
				}

				/* Improved hover effect */
				.table-hover tbody tr:hover {
				    background-color: rgba(0, 123, 255, 0.05) !important;
				}
				{/literal}
				</style>
