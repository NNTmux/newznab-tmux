<div class="card">
			    <div class="card-header">
			        <div class="d-flex justify-content-between align-items-center">
			            <h4 class="mb-0">{$title}</h4>
			            <a href="{{url("/admin/role-add")}}" class="btn btn-primary">
			                <i class="fa fa-plus-circle me-2"></i>Create New Role
			            </a>
			        </div>
			    </div>

			    <div class="card-body">
			        <div class="table-responsive">
			            <table class="table table-striped table-hover">
			                <thead class="thead-light">
			                    <tr>
			                        <th>
			                            <div class="d-flex align-items-center gap-2">
			                                <span>Name</span>
			                                <div class="sort-controls">
			                                    <a href="?sort=name&amp;order=asc" class="sort-icon {if isset($sort) && $sort == 'name' && $order == 'asc'}active{/if}" title="Sort Ascending">
			                                        <i class="fas fa-sort-alpha-down"></i>
			                                    </a>
			                                    <a href="?sort=name&amp;order=desc" class="sort-icon {if isset($sort) && $sort == 'name' && $order == 'desc'}active{/if}" title="Sort Descending">
			                                        <i class="fas fa-sort-alpha-down-alt"></i>
			                                    </a>
			                                </div>
			                            </div>
			                        </th>
			                        <th class="text-center">API Requests</th>
			                        <th class="text-center">API Rate</th>
			                        <th class="text-center">Download Limit</th>
			                        <th class="text-center">Invites</th>
			                        <th class="text-center">Permissions</th>
			                        <th class="text-center">Donation</th>
			                        <th class="text-center">Add Years</th>
			                        <th class="text-center">Default</th>
			                        <th class="text-end">Actions</th>
			                    </tr>
			                </thead>
			                <tbody>
			                    {foreach $userroles as $role}
			                        <tr>
			                            <td>
			                                <a href="{{url("/admin/role-edit?id={$role.id}")}}" class="fw-semibold text-primary">{$role.name}</a>
			                            </td>
			                            <td class="text-center">
			                                <span class="badge bg-secondary rounded-pill">{$role.apirequests}</span>
			                            </td>
			                            <td class="text-center">
			                                <span class="badge bg-secondary rounded-pill">{$role.rate_limit}</span>
			                            </td>
			                            <td class="text-center">
			                                <span class="badge bg-secondary rounded-pill">{$role.downloadrequests}</span>
			                            </td>
			                            <td class="text-center">
			                                <span class="badge bg-secondary rounded-pill">{$role.defaultinvites}</span>
			                            </td>
			                            <td class="text-center">
			                                <div class="d-flex flex-wrap justify-content-center gap-1">
			                                    {if $role->hasPermissionTo('preview') == true}
			                                        <span class="badge bg-success" data-bs-toggle="tooltip" title="Can Preview">
			                                            <i class="fa fa-eye"></i>
			                                        </span>
			                                    {/if}
			                                    {if $role->hasPermissionTo('hideads') == true}
			                                        <span class="badge bg-info" data-bs-toggle="tooltip" title="Hide Ads">
			                                            <i class="fa fa-ban"></i>
			                                        </span>
			                                    {/if}
			                                </div>
			                            </td>
			                            <td class="text-center">
			                                {if $role.donation > 0}
			                                    <span class="badge bg-success">${$role.donation}</span>
			                                {else}
			                                    <span class="badge bg-secondary">$0</span>
			                                {/if}
			                            </td>
			                            <td class="text-center">
			                                {if $role.addyears > 0}
			                                    <span class="badge bg-info">{$role.addyears} {if $role.addyears == 1}Year{else}Years{/if}</span>
			                                {else}
			                                    <span class="badge bg-secondary">0</span>
			                                {/if}
			                            </td>
			                            <td class="text-center">
			                                {if $role.isdefault == "1"}
			                                    <span class="badge bg-warning text-dark" data-bs-toggle="tooltip" title="Default Role">
			                                        <i class="fa fa-check"></i>
			                                    </span>
			                                {else}
			                                    <span class="badge bg-light text-dark">
			                                        <i class="fa fa-times"></i>
			                                    </span>
			                                {/if}
			                            </td>
			                            <td class="text-end">
			                                <div class="d-flex justify-content-end gap-2">
			                                    <a href="{{url("/admin/role-edit?id={$role.id}")}}" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Edit Role">
			                                        <i class="fa fa-edit"></i>
			                                    </a>
			                                    {if !in_array($role.name, ['User', 'Admin', 'Moderator', 'Disabled', 'Friend'])}
			                                        <a href="{{url("/admin/role-delete?id={$role.id}")}}" class="btn btn-sm btn-outline-danger confirm_action" data-bs-toggle="tooltip" title="Delete Role">
			                                            <i class="fa fa-trash"></i>
			                                        </a>
			                                    {/if}
			                                </div>
			                            </td>
			                        </tr>
			                    {/foreach}
			                </tbody>
			            </table>
			        </div>
			    </div>

			    <div class="card-footer">
			        <div class="d-flex justify-content-between align-items-center">
			            <div>
			                <small class="text-muted">Showing {$userroles|@count} roles</small>
			            </div>
			            <a href="{{url("/admin/role-add")}}" class="btn btn-primary">
			                <i class="fa fa-plus-circle me-2"></i>Create New Role
			            </a>
			        </div>
			    </div>
			</div>

			<script>
			{literal}
			document.addEventListener('DOMContentLoaded', function() {
			    // Initialize tooltips
			    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
			    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
			        return new bootstrap.Tooltip(tooltipTriggerEl);
			    });

			    // Confirm delete dialog
			    document.querySelectorAll('.confirm_action').forEach(function(element) {
			        element.addEventListener('click', function(e) {
			            if (!confirm('Are you sure you want to delete this role? This cannot be undone.')) {
			                e.preventDefault();
			                return false;
			            }
			        });
			    });
			});
			{/literal}
			</script>

			<style>
			{literal}
			/* Sort controls styling */
			.sort-controls {
			    display: inline-flex;
			    flex-direction: column;
			    margin-left: 0.25rem;
			    line-height: 0.7;
			}

			.sort-icon {
			    color: #adb5bd;
			    font-size: 0.75rem;
			}

			.sort-icon:hover, .sort-icon.active {
			    color: #495057;
			}

			/* Table styling improvements */
			.table-responsive {
			    border-radius: 0.25rem;
			    overflow: hidden;
			}

			/* Badge styling */
			.badge {
			    font-weight: 500;
			    padding: 0.35em 0.65em;
			}

			/* Improve buttons spacing in small screens */
			@media (max-width: 767.98px) {
			    .card-header .d-flex,
			    .card-footer .d-flex {
			        flex-direction: column;
			        gap: 0.5rem;
			    }

			    .card-header .btn,
			    .card-footer .btn {
			        width: 100%;
			    }

			    .card-header h4 {
			        text-align: center;
			        margin-bottom: 0.5rem;
			    }

			    th, td {
			        font-size: 0.85rem;
			    }

			    .badge {
			        font-size: 0.7rem;
			    }
			}
			{/literal}
			</style>
