<div class="card">
				    <div class="card-header">
				        <div class="d-flex justify-content-between align-items-center">
				            <h4 class="mb-0">{$title}</h4>
				            <div>
				                <a href="{{url("/admin")}}" class="btn btn-outline-secondary">
				                    <i class="fa fa-arrow-left me-2"></i>Back to Dashboard
				                </a>
				            </div>
				        </div>
				    </div>

				    <div class="card-body">
				        {if $commentslist}
				            <div class="table-responsive">
				                <table class="table table-striped table-hover">
				                    <thead>
				                        <tr>
				                            <th><i class="fa fa-user me-2"></i>User</th>
				                            <th><i class="fa fa-calendar me-2"></i>Date</th>
				                            <th><i class="fa fa-comment me-2"></i>Comment</th>
				                            <th><i class="fa fa-server me-2"></i>Host</th>
				                            <th><i class="fa fa-cog me-2"></i>Options</th>
				                        </tr>
				                    </thead>
				                    <tbody>
				                        {foreach from=$commentslist item=comment}
				                            <tr>
				                                <td>
				                                    {if $comment.users_id > 0}
				                                        <a href="{{url("/admin/user-edit?id={$comment.users_id}")}}" class="text-decoration-none">
				                                            {$comment.username}
				                                        </a>
				                                    {else}
				                                        {$comment.username}
				                                    {/if}
				                                </td>
				                                <td data-bs-toggle="tooltip" title="{$comment.created_at}">{$comment.created_at|timeago}</td>
				                                {if $comment.shared == 2}
				                                    <td class="text-danger">{$comment.text|escape:"htmlall"|nl2br}</td>
				                                {else}
				                                    <td>{$comment.text|escape:"htmlall"|nl2br}</td>
				                                {/if}
				                                <td>{$comment.host}</td>
				                                <td>
				                                    <div class="btn-group btn-group-sm">
				                                        {if $comment.guid}
				                                            <a href="{{url("/details/{$comment.guid}#comments")}}" class="btn btn-outline-primary" title="View comment">
				                                                <i class="fa fa-eye"></i>
				                                            </a>
				                                        {/if}
				                                        <a href="{{url("/admin/comments-delete?id={$comment.id}")}}" class="btn btn-outline-danger" title="Delete comment"
				                                           onclick="return confirm('Are you sure you want to delete this comment?');">
				                                            <i class="fa fa-trash"></i>
				                                        </a>
				                                    </div>
				                                </td>
				                            </tr>
				                        {/foreach}
				                    </tbody>
				                </table>
				            </div>

				            <div class="d-flex justify-content-center mt-4">
				                {$commentslist->onEachSide(5)->links()}
				            </div>
				        {else}
				            <div class="alert alert-info">
				                <i class="fa fa-info-circle me-2"></i>No comments available
				            </div>
				        {/if}
				    </div>

				    <div class="card-footer">
				        <div class="d-flex justify-content-between">
				            <span class="text-muted">
				                <i class="fa fa-comment me-2"></i>
				                {if $commentslist}
				                    Showing {$commentslist->firstItem()} to {$commentslist->lastItem()} of {$commentslist->total()} comments
				                {else}
				                    No comments found
				                {/if}
				            </span>
				        </div>
				    </div>
				</div>

				<style>
				{literal}
				/* Table styling improvements */
				.table {
				    margin-bottom: 0;
				}

				.table th {
				    background-color: #f8f9fa;
				    border-top: none;
				    font-weight: 600;
				}

				.table td {
				    vertical-align: middle;
				}

				/* Pagination styling */
				.pagination {
				    margin-bottom: 0;
				}

				/* Button styling */
				.btn-group-sm .btn {
				    padding: 0.25rem 0.5rem;
				}

				/* Responsive adjustments */
				@media (max-width: 767.98px) {
				    .table-responsive {
				        border: 0;
				    }

				    .card-footer .text-muted {
				        font-size: 0.875rem;
				    }
				}

				/* Text formatting for comments */
				td.text-danger {
				    color: #dc3545 !important;
				    font-style: italic;
				}
				{/literal}
				</style>

				<script>
				{literal}
				document.addEventListener('DOMContentLoaded', function() {
				    // Initialize tooltips
				    if (typeof bootstrap !== 'undefined') {
				        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
				        tooltipTriggerList.map(function (tooltipTriggerEl) {
				            return new bootstrap.Tooltip(tooltipTriggerEl);
				        });
				    }
				});
				{/literal}
				</script>
