<div class="card">
		    <div class="card-header">
		        <div class="d-flex justify-content-between align-items-center">
		            <h4 class="mb-0">{$title}</h4>
		            <a href="{{url("/admin/collection_regexes-list")}}" class="btn btn-outline-secondary">
		                <i class="fa fa-arrow-left me-2"></i>Back to Regexes List
		            </a>
		        </div>
		    </div>

		    <div class="card-body">
		        <p class="mb-4">This page is used for testing regex for grouping usenet collections. Enter the group name to test and a regex. Limit is how many collections to show max on the page, 0 for no limit (slow).</p>

		        <form name="search" action="" method="post" id="regexTestForm">
		            {{csrf_field()}}

		            <div class="row mb-4">
		                <div class="col-lg-3 col-md-4">
		                    <label for="group" class="form-label fw-bold">Group:</label>
		                </div>
		                <div class="col-lg-9 col-md-8">
		                    <div class="input-group">
		                        <span class="input-group-text"><i class="fa fa-layer-group"></i></span>
		                        <input id="group" type="text" name="group" class="form-control" value="{$group|htmlentities}"/>
		                    </div>
		                    <small class="text-muted">The newsgroup to test against (e.g., alt.binaries.example)</small>
		                </div>
		            </div>

		            <div class="row mb-4">
		                <div class="col-lg-3 col-md-4">
		                    <label for="regex" class="form-label fw-bold">Regex:</label>
		                </div>
		                <div class="col-lg-9 col-md-8">
		                    <div class="input-group">
		                        <span class="input-group-text"><i class="fa fa-code"></i></span>
		                        <input id="regex" type="text" name="regex" class="form-control" value="{$regex|htmlentities}"/>
		                    </div>
		                    <small class="text-muted">The regex pattern to test (include delimiters and modifiers)</small>
		                </div>
		            </div>

		            <div class="row mb-4">
		                <div class="col-lg-3 col-md-4">
		                    <label for="limit" class="form-label fw-bold">Limit:</label>
		                </div>
		                <div class="col-lg-9 col-md-8">
		                    <div class="input-group">
		                        <span class="input-group-text"><i class="fa fa-list-ol"></i></span>
		                        <input id="limit" type="number" name="limit" class="form-control" value="{$limit}"/>
		                    </div>
		                    <small class="text-muted">Maximum number of collections to display (use 0 for no limit, but this may be slow)</small>
		                </div>
		            </div>
		        </form>
		    </div>

		    <div class="card-footer">
		        <div class="d-flex justify-content-end">
		            <button type="submit" form="regexTestForm" class="btn btn-success">
		                <i class="fa fa-vial me-2"></i>Test Regex
		            </button>
		        </div>
		    </div>
		</div>

		{if isset($data)}
		    <div class="mt-4">
		        {foreach from=$data key=hash item=collection}
		            <div class="card mb-4">
		                <div class="card-header bg-light">
		                    <div class="d-flex justify-content-between align-items-center">
		                        <h5 class="mb-0">Collection Hash: {$hash}</h5>
		                        <span class="badge bg-primary">{count($collection)} Files</span>
		                    </div>
		                </div>
		                <div class="card-body p-0">
		                    <div class="table-responsive">
		                        <table class="table table-striped table-hover mb-0">
		                            <thead>
		                                <tr>
		                                    <th>Name</th>
		                                    <th>Current Parts</th>
		                                    <th>Total Parts</th>
		                                    <th>Poster</th>
		                                    <th>Old Hash</th>
		                                </tr>
		                            </thead>
		                            <tbody>
		                                {foreach from=$collection item=row}
		                                    <tr id="row-{$row.new_collection_hash}">
		                                        <td>{$row.file_name}</td>
		                                        <td>{$row.file_current_parts}</td>
		                                        <td>{$row.file_total_parts}</td>
		                                        <td>{$row.collection_poster}</td>
		                                        <td><code>{$row.old_collection_hash}</code></td>
		                                    </tr>
		                                {/foreach}
		                            </tbody>
		                        </table>
		                    </div>
		                </div>
		            </div>
		        {/foreach}
		    </div>
		{/if}

		<style>
		{literal}
		/* Form styling improvements */
		.form-label {
		    margin-bottom: 0.5rem;
		}

		/* Number input styling */
		input[type="number"] {
		    -moz-appearance: textfield;
		}

		input[type="number"]::-webkit-inner-spin-button,
		input[type="number"]::-webkit-outer-spin-button {
		    -webkit-appearance: none;
		    margin: 0;
		}

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

		/* Responsive adjustments */
		@media (max-width: 767.98px) {
		    .card-footer .btn {
		        padding: 0.375rem 0.75rem;
		    }
		}

		/* Improve input focus states */
		.form-control:focus,
		.form-select:focus {
		    border-color: #80bdff;
		    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
		}
		{/literal}
		</style>
