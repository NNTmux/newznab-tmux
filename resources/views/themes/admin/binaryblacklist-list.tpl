<div class="card">
			    <div class="card-header">
			        <div class="d-flex justify-content-between align-items-center">
			            <div>
			                <h4 class="mb-0">{$title}</h4>
			            </div>
			            <div>
			                <a href="{{url("/admin/binaryblacklist-edit")}}" class="btn btn-sm btn-primary">
			                    <i class="fa fa-plus me-2"></i>Add New Blacklist
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
			                        Binaries can be prevented from being added to the index if they match a regex in the blacklist.
			                        They can also be included only if they match a regex (whitelist).
			                        <strong>Click Edit or on the blacklist to enable/disable.</strong>
			                    </p>
			                </div>
			            </div>
			        </div>

			        <div id="message"></div>

			        <div class="table-responsive">
			            <table class="table table-striped table-hover align-middle">
			                <thead class="thead-light">
			                    <tr>
			                        <th style="width: 60px;" class="text-center">ID</th>
			                        <th>Group</th>
			                        <th>Description</th>
			                        <th style="width: 80px;" class="text-center">Type</th>
			                        <th style="width: 80px;" class="text-center">Field</th>
			                        <th style="width: 80px;" class="text-center">Status</th>
			                        <th style="width: 300px;">Regex</th>
			                        <th style="width: 150px;" class="text-center">Last Activity</th>
			                        <th style="width: 120px;" class="text-center">Actions</th>
			                    </tr>
			                </thead>
			                <tbody>
			                    {foreach from=$binlist item=bin}
			                        <tr id="row-{$bin->id}" class="{cycle values=",bg-light"}">
			                            <td class="text-center fw-bold">{$bin->id}</td>
			                            <td>
			                                <span class="d-inline-block text-truncate" style="max-width: 150px;" title="{$bin->groupname}">
			                                    {$bin->groupname|replace:"alt.binaries":"a.b"}
			                                </span>
			                            </td>
			                            <td>
			                                <span class="d-inline-block text-truncate" style="max-width: 200px;" title="{$bin->description}">
			                                    {$bin->description}
			                                </span>
			                            </td>
			                            <td class="text-center">
			                                {if $bin->optype==1}
			                                    <span class="badge bg-danger">Black</span>
			                                {else}
			                                    <span class="badge bg-success">White</span>
			                                {/if}
			                            </td>
			                            <td class="text-center">
			                                {if $bin->msgcol==1}
			                                    <span class="badge bg-info">Subject</span>
			                                {elseif $bin->msgcol==2}
			                                    <span class="badge bg-warning text-dark">Poster</span>
			                                {else}
			                                    <span class="badge bg-secondary">MessageID</span>
			                                {/if}
			                            </td>
			                            <td class="text-center">
			                                {if $bin->status==1}
			                                    <span class="badge bg-success">Active</span>
			                                {else}
			                                    <span class="badge bg-danger">Disabled</span>
			                                {/if}
			                            </td>
			                            <td>
			                                <div class="text-truncate" style="max-width: 290px;">
			                                    <a href="{{url("/admin/binaryblacklist-edit?id={$bin->id}")}}" class="text-decoration-none" title="{$bin->regex|escape:html}">
			                                        <code>{$bin->regex|escape:html}</code>
			                                    </a>
			                                </div>
			                            </td>
			                            <td class="text-center">
			                                <span class="d-inline-block" title="{$bin->last_activity}">
			                                    {if $bin->last_activity}
			                                        <i class="fa fa-clock-o me-1 text-muted"></i>{$bin->last_activity}
			                                    {else}
			                                        <span class="text-muted">Never</span>
			                                    {/if}
			                                </span>
			                            </td>
			                            <td class="text-center">
			                                <div class="btn-group">
			                                    <a href="{{url("/admin/binaryblacklist-edit?id={$bin->id}")}}" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Edit this blacklist">
			                                        <i class="fa fa-edit"></i>
			                                    </a>
			                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="if(confirm('Are you sure? This will delete the blacklist from this list.')) { ajax_binaryblacklist_delete({$bin->id}) }" data-bs-toggle="tooltip" title="Delete this blacklist">
			                                        <i class="fa fa-trash"></i>
			                                    </button>
			                                </div>
			                            </td>
			                        </tr>
			                    {foreachelse}
			                        <tr>
			                            <td colspan="9" class="text-center py-4">
			                                <div class="alert alert-warning mb-0">
			                                    <i class="fa fa-exclamation-triangle me-2"></i>No blacklist entries found
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
			                <span class="text-muted">Total entries: {$binlist|@count}</span>
			            </div>
			            <div>
			                <a href="{{url("/admin/binaryblacklist-edit")}}" class="btn btn-sm btn-primary">
			                    <i class="fa fa-plus me-2"></i>Add New Blacklist
			                </a>
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
			    function showMessage(message, type = 'success') {
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
			    }

			    // Extend the existing ajax function to show user feedback
			    window.ajax_binaryblacklist_delete_original = window.ajax_binaryblacklist_delete || function() {};

			    window.ajax_binaryblacklist_delete = function(id) {
			        // Call the original function with a callback
			        const xhr = new XMLHttpRequest();
			        xhr.open('GET', `{{url("/admin/binaryblacklist-delete?id=")}}`+id, true);
			        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

			        xhr.onload = function() {
			            if (xhr.status === 200) {
			                const row = document.getElementById('row-'+id);
			                if (row) {
			                    // Fade out and remove the row
			                    row.style.transition = 'opacity 0.5s';
			                    row.style.opacity = '0';
			                    setTimeout(() => row.remove(), 500);
			                }
			                showMessage('Blacklist entry deleted successfully');
			            } else {
			                showMessage('Error deleting blacklist entry', 'danger');
			            }
			        };

			        xhr.onerror = function() {
			            showMessage('Error deleting blacklist entry', 'danger');
			        };

			        xhr.send();
			    };
			});
			{/literal}
			</script>

			<style>
			{literal}
			/* Custom styling for the blacklist table */
			.table td {
			    vertical-align: middle;
			}

			/* Code styling for regex */
			code {
			    background-color: rgba(0, 0, 0, 0.05);
			    padding: 2px 5px;
			    border-radius: 3px;
			    font-family: monospace;
			    word-break: break-all;
			    font-size: 0.85rem;
			}

			/* Improve mobile responsiveness */
			@media (max-width: 767.98px) {
			    .table-responsive {
			        border: 0;
			    }

			    .card-footer {
			        flex-direction: column;
			    }

			    .card-footer > div:first-child {
			        margin-bottom: 1rem;
			    }
			}

			/* Hover effect for action buttons */
			.btn-outline-primary:hover, .btn-outline-danger:hover {
			    transform: translateY(-2px);
			    transition: transform 0.2s ease;
			}

			/* Improve table hover state */
			.table-hover tbody tr:hover {
			    background-color: rgba(0, 123, 255, 0.05) !important;
			}
			{/literal}
			</style>
