<div class="card">
				    <div class="card-header">
				        <div class="d-flex justify-content-between align-items-center">
				            <h4 class="mb-0">{$title}</h4>
				            <a href="{{url("/admin/collection_regexes-edit")}}" class="btn btn-primary">
				                <i class="fa fa-plus me-2"></i>Add New Regex
				            </a>
				        </div>
				    </div>

				    <div class="card-body">
				        <p class="mb-4">This page lists regex used for grouping usenet collections.</p>
				        <div id="message"></div>

				        <form name="groupsearch" action="" class="mb-4">
				            {{csrf_field()}}
				            <div class="row">
				                <div class="col-md-6 col-lg-4">
				                    <div class="input-group">
				                        <span class="input-group-text"><i class="fa fa-search"></i></span>
				                        <input id="group" type="text" name="group" class="form-control" value="{$group}" placeholder="Search a group...">
				                        <button class="btn btn-primary" type="submit">Search</button>
				                    </div>
				                </div>
				            </div>
				        </form>

				        {if $regex}
				            <div class="table-responsive">
				                <table class="table table-striped table-hover">
				                    <thead>
				                        <tr>
				                            <th style="width:60px;">ID</th>
				                            <th>Group</th>
				                            <th>Description</th>
				                            <th>Ordinal</th>
				                            <th>Status</th>
				                            <th>Regex</th>
				                            <th style="width:120px;">Actions</th>
				                        </tr>
				                    </thead>
				                    <tbody>
				                        {foreach from=$regex item=row}
				                            <tr id="row-{$row.id}">
				                                <td>{$row.id}</td>
				                                <td>{$row.group_regex}</td>
				                                <td>{$row.description|truncate:50:"...":true}</td>
				                                <td>{$row.ordinal}</td>
				                                <td>
				                                    {if $row.status==1}
				                                        <span class="badge bg-success">Active</span>
				                                    {else}
				                                        <span class="badge bg-danger">Disabled</span>
				                                    {/if}
				                                </td>
				                                <td><code>{$row.regex|escape:html|truncate:50:"...":true}</code></td>
				                                <td>
				                                    <div class="btn-group btn-group-sm">
				                                        <a href="{{url("/admin/collection_regexes-edit?id={$row.id}")}}" class="btn btn-outline-primary" title="Edit this regex">
				                                            <i class="fa fa-edit"></i>
				                                        </a>
				                                        <a href="javascript:ajax_collection_regex_delete({$row.id})"
				                                           onclick="return confirm('Are you sure? This will delete the regex from this list.');"
				                                           class="btn btn-outline-danger"
				                                           title="Delete this regex">
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
				                {$regex->onEachSide(5)->links()}
				            </div>
				        {else}
				            <div class="alert alert-info">
				                <i class="fa fa-info-circle me-2"></i>No regex rules found matching your criteria.
				            </div>
				        {/if}
				    </div>

				    <div class="card-footer">
				        <div class="d-flex justify-content-end">
				            <a href="{{url("/admin/collection_regexes-edit")}}" class="btn btn-primary">
				                <i class="fa fa-plus me-2"></i>Add New Regex
				            </a>
				        </div>
				    </div>
				</div>

				<style>
				{literal}
				/* Improve table appearance */
				.table {
				    margin-bottom: 0;
				}

				.table th {
				    background-color: #f8f9fa;
				    border-top: none;
				}

				.table code {
				    font-size: 0.875rem;
				    background-color: rgba(0,0,0,0.05);
				    padding: 0.2em 0.4em;
				}

				/* Pagination styling */
				.pagination {
				    margin-bottom: 0;
				}

				/* Responsive adjustments */
				@media (max-width: 767.98px) {
				    .btn-group-sm .btn {
				        padding: 0.25rem 0.5rem;
				    }
				}

				/* Badge styling */
				.badge {
				    font-weight: 500;
				    font-size: 0.75rem;
				    padding: 0.35em 0.65em;
				}
				{/literal}
				</style>

				<script>
				{literal}
				function ajax_collection_regex_delete(id) {
				    if (confirm('Are you sure you want to delete this regex?')) {
				        fetch(`/admin/collection_regexes-delete?id=${id}`, {
				            method: 'POST',
				            headers: {
				                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
				                'Content-Type': 'application/json'
				            }
				        })
				        .then(response => response.json())
				        .then(data => {
				            if (data.success) {
				                document.getElementById(`row-${id}`).remove();
				                document.getElementById('message').innerHTML =
				                    `<div class="alert alert-success alert-dismissible fade show">
				                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
				                        <strong>Success!</strong> Regex deleted successfully.
				                    </div>`;
				            } else {
				                document.getElementById('message').innerHTML =
				                    `<div class="alert alert-danger alert-dismissible fade show">
				                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
				                        <strong>Error!</strong> ${data.error || 'Failed to delete regex.'}
				                    </div>`;
				            }
				        })
				        .catch(error => {
				            document.getElementById('message').innerHTML =
				                `<div class="alert alert-danger alert-dismissible fade show">
				                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
				                    <strong>Error!</strong> An unexpected error occurred.
				                </div>`;
				        });
				    }
				    return false;
				}
				{/literal}
				</script>
