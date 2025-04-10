<div class="card">
	    <div class="card-header">
	        <div class="d-flex justify-content-between align-items-center">
	            <h4 class="mb-0">{$title}</h4>
	            <a href="{{url("/admin/group-list")}}" class="btn btn-outline-secondary">
	                <i class="fa fa-list me-2"></i>View All Groups
	            </a>
	        </div>
	    </div>

	    <div class="card-body">
	        {if !empty($groupmsglist)}
	            <div class="alert alert-info mb-4">
	                <i class="fa fa-info-circle me-2"></i>
	                The following groups have been processed. You can now view them in the group list.
	            </div>

	            <div class="table-responsive">
	                <table class="table table-striped table-hover align-middle">
	                    <thead>
	                        <tr>
	                            <th>Group</th>
	                            <th>Status</th>
	                        </tr>
	                    </thead>
	                    <tbody>
	                        {foreach $groupmsglist as $group}
	                            <tr>
	                                <td>
	                                    <div class="d-flex align-items-center">
	                                        <i class="fa fa-users text-muted me-2"></i>
	                                        <span class="fw-medium">{$group.group}</span>
	                                    </div>
	                                </td>
	                                <td>
	                                    {if strpos($group.msg, 'Error') !== false}
	                                        <span class="badge bg-danger rounded-pill">
	                                            <i class="fa fa-exclamation-circle me-1"></i>{$group.msg}
	                                        </span>
	                                    {elseif strpos($group.msg, 'exists') !== false}
	                                        <span class="badge bg-warning text-dark rounded-pill">
	                                            <i class="fa fa-exclamation-triangle me-1"></i>{$group.msg}
	                                        </span>
	                                    {else}
	                                        <span class="badge bg-success rounded-pill">
	                                            <i class="fa fa-check-circle me-1"></i>{$group.msg}
	                                        </span>
	                                    {/if}
	                                </td>
	                            </tr>
	                        {/foreach}
	                    </tbody>
	                </table>
	            </div>
	        {else}
	            <div class="alert alert-info mb-4">
	                <i class="fa fa-info-circle me-2"></i>
	                Enter a regular expression to match multiple groups for bulk addition to the system.
	            </div>

	            {{Form::open(['url'=> "admin/group-bulk?action=submit", 'id' => 'groupBulkForm'])}}
	                <div class="row mb-4">
	                    <div class="col-lg-3 col-md-4">
	                        <label for="groupfilter" class="form-label fw-bold">Group Pattern:</label>
	                    </div>
	                    <div class="col-lg-9 col-md-8">
	                        <div class="input-group">
	                            <span class="input-group-text"><i class="fa fa-filter"></i></span>
	                            <textarea id="groupfilter" name="groupfilter" class="form-control" rows="5" placeholder="e.g. alt.binaries.cd.image.linux|alt.binaries.warez.linux"></textarea>
	                        </div>
	                        <small class="text-muted">
	                            A regular expression to match against group names. Separate multiple patterns with the pipe symbol (|).
	                            <br>Example: <code>alt.binaries.cd.image.linux|alt.binaries.warez.linux</code>
	                        </small>
	                    </div>
	                </div>

	                <div class="row mb-4">
	                    <div class="col-lg-3 col-md-4">
	                        <label class="form-label fw-bold">Active:</label>
	                    </div>
	                    <div class="col-lg-9 col-md-8">
	                        <div class="form-check form-check-inline">
	                            <input class="form-check-input" type="radio" name="active" id="active_yes" value="1" checked>
	                            <label class="form-check-label" for="active_yes">Yes</label>
	                        </div>
	                        <div class="form-check form-check-inline">
	                            <input class="form-check-input" type="radio" name="active" id="active_no" value="0">
	                            <label class="form-check-label" for="active_no">No</label>
	                        </div>
	                        <div class="mt-2">
	                            <small class="text-muted">Inactive groups will not have headers downloaded for them.</small>
	                        </div>
	                    </div>
	                </div>

	                <div class="row mb-4">
	                    <div class="col-lg-3 col-md-4">
	                        <label class="form-label fw-bold">Backfill:</label>
	                    </div>
	                    <div class="col-lg-9 col-md-8">
	                        <div class="form-check form-check-inline">
	                            <input class="form-check-input" type="radio" name="backfill" id="backfill_yes" value="1">
	                            <label class="form-check-label" for="backfill_yes">Yes</label>
	                        </div>
	                        <div class="form-check form-check-inline">
	                            <input class="form-check-input" type="radio" name="backfill" id="backfill_no" value="0" checked>
	                            <label class="form-check-label" for="backfill_no">No</label>
	                        </div>
	                        <div class="mt-2">
	                            <small class="text-muted">Inactive groups will not have backfill headers downloaded for them.</small>
	                        </div>
	                    </div>
	                </div>
	            {{Form::close()}}
	        {/if}
	    </div>

	    <div class="card-footer">
	        <div class="d-flex justify-content-between">
	            <a href="{{url("/admin/group-list")}}" class="btn btn-outline-secondary">
	                <i class="fa fa-arrow-left me-2"></i>Back to Groups
	            </a>
	            {if empty($groupmsglist)}
	                <button type="submit" form="groupBulkForm" class="btn btn-success">
	                    <i class="fa fa-plus-circle me-2"></i>Add Groups
	                </button>
	            {else}
	                <a href="{{url("/admin/group-bulk")}}" class="btn btn-primary">
	                    <i class="fa fa-plus-circle me-2"></i>Add More Groups
	                </a>
	            {/if}
	        </div>
	    </div>
	</div>

	<script>
	{literal}
	document.addEventListener('DOMContentLoaded', function() {
	    // Form validation
	    const form = document.getElementById('groupBulkForm');
	    if (form) {
	        form.addEventListener('submit', function(event) {
	            const groupfilter = document.getElementById('groupfilter').value.trim();

	            if (groupfilter === '') {
	                event.preventDefault();
	                alert('Please enter a group pattern');
	                return false;
	            }

	            // Simple regex validation - could be expanded
	            try {
	                new RegExp(groupfilter);
	            } catch(e) {
	                event.preventDefault();
	                alert('Invalid regex pattern: ' + e.message);
	                return false;
	            }
	        });
	    }
	});
	{/literal}
	</script>

	<style>
	{literal}
	/* Form styling improvements */
	.form-label {
	    margin-bottom: 0.5rem;
	}

	/* Textarea styling */
	textarea.form-control {
	    font-family: monospace;
	    font-size: 0.9rem;
	}

	/* Badge styling */
	.badge {
	    font-weight: 500;
	    padding: 0.4em 0.6em;
	}

	/* Form check styling */
	.form-check-input:checked {
	    background-color: #198754;
	    border-color: #198754;
	}

	/* Code blocks in help text */
	code {
	    background-color: #f8f9fa;
	    padding: 0.2rem 0.4rem;
	    border-radius: 0.2rem;
	    color: #d63384;
	}

	/* Responsive adjustments */
	@media (max-width: 767.98px) {
	    .card-footer .btn {
	        padding: 0.375rem 0.75rem;
	    }

	    .card-footer .d-flex {
	        flex-direction: column;
	        gap: 0.5rem;
	    }

	    .card-footer .d-flex a,
	    .card-footer .d-flex button {
	        width: 100%;
	    }
	}
	{/literal}
	</style>
