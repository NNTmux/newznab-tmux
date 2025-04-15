<div class="card">
			    <div class="card-header">
			        <div class="d-flex justify-content-between align-items-center">
			            <h4 class="mb-0">{$title}</h4>
			            <a href="{{url("/admin/release_naming_regexes-list")}}" class="btn btn-outline-secondary">
			                <i class="fa fa-arrow-left me-2"></i>Back to Regexes List
			            </a>
			        </div>
			    </div>

			    <div class="card-body">
			        <p class="mb-4">This page is used for testing regex for getting release names from usenet subjects. Enter the group, regex, and limits below to see the results.</p>

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
			                    <small class="text-muted">
			                        Regex to match against a group.<br/>
			                        Delimiters are already added.<br/>
			                        An example of matching a group: alt.binaries.example
			                    </small>
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
			                    <small class="text-muted">
			                        The regex to use when trying to name a release using the usenet subject.<br/>
			                        The regex delimiters are not added, you MUST add them. See <a href="http://php.net/manual/en/regexp.reference.delimiters">this</a> page.<br/>
			                        To make the regex case insensitive, add i after the last delimiter.<br/>
			                        You MUST include at least one regex capture group.<br/>
			                        You MUST name your regex capture groups (the ones you want included).<br/>
			                        The named capture groups will be concatenated into a string.<br/>
			                        Capture groups are sorted alphabetically (by capture group name) when concatenating the string.<br/>
			                        Capture groups named 'reqid' and 'parts' are ignored.
			                    </small>
			                </div>
			            </div>

			            <div class="row mb-4">
			                <div class="col-lg-3 col-md-4">
			                    <label for="showlimit" class="form-label fw-bold">Maximum Releases to Display:</label>
			                </div>
			                <div class="col-lg-9 col-md-8">
			                    <div class="input-group">
			                        <span class="input-group-text"><i class="fa fa-eye"></i></span>
			                        <input id="showlimit" type="number" name="showlimit" class="form-control" value="{$showlimit}"/>
			                    </div>
			                    <small class="text-muted">0 for no limit (may affect page load time)</small>
			                </div>
			            </div>

			            <div class="row mb-4">
			                <div class="col-lg-3 col-md-4">
			                    <label for="querylimit" class="form-label fw-bold">Query Limit:</label>
			                </div>
			                <div class="col-lg-9 col-md-8">
			                    <div class="input-group">
			                        <span class="input-group-text"><i class="fa fa-database"></i></span>
			                        <input id="querylimit" type="number" name="querylimit" class="form-control" value="{$querylimit}"/>
			                    </div>
			                    <small class="text-muted">Limit the amount of releases to select from database. 0 for no limit (setting this high can be slow)</small>
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
			    <div class="card mt-4">
			        <div class="card-header bg-light">
			            <h5 class="mb-0">Test Results</h5>
			        </div>
			        <div class="card-body p-0">
			            <div class="table-responsive">
			                <table class="table table-striped table-hover mb-0">
			                    <thead>
			                        <tr>
			                            <th>Release ID</th>
			                            <th>Usenet Subject</th>
			                            <th>Old Search Name</th>
			                            <th>New Search Name</th>
			                        </tr>
			                    </thead>
			                    <tbody>
			                        {foreach from=$data key=id item=names}
			                            <tr id="row-{$id}">
			                                <td>{$id}</td>
			                                <td class="text-break">{$names.subject}</td>
			                                <td class="text-break">{$names.old_name}</td>
			                                <td class="text-break">{$names.new_name}</td>
			                            </tr>
			                        {/foreach}
			                    </tbody>
			                </table>
			            </div>
			        </div>
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

			/* Results table styling */
			#regexTestForm #regex {
			    font-family: monospace;
			}

			.text-break {
			    word-break: break-word;
			    max-width: 300px;
			}
			{/literal}
			</style>
