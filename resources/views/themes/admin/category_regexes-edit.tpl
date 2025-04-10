<div class="card">
				    <div class="card-header">
				        <div class="d-flex justify-content-between align-items-center">
				            <div>
				                <h4 class="mb-0">{$title}</h4>
				            </div>
				            <div>
				                <a href="{{url("/admin/category_regexes-list")}}" class="btn btn-sm btn-outline-primary">
				                    <i class="fa fa-arrow-left me-2"></i>Back to List
				                </a>
				            </div>
				        </div>
				    </div>

				    <div class="card-body">
				        {if isset($error) && $error != ''}
				            <div class="alert alert-danger alert-dismissible fade show" role="alert">
				                <i class="fa fa-exclamation-triangle me-2"></i>{$error}
				                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
				            </div>
				        {/if}

				        {{Form::open(['url'=> "admin/category_regexes-edit?action=submit", 'id' => 'regexForm', 'class' => 'needs-validation'])}}
				            <input type="hidden" name="id" value="{$regex.id}"/>

				            <div class="row mb-4">
				                <div class="col-lg-3 col-md-4">
				                    <label for="group_regex" class="form-label fw-bold">Group:</label>
				                </div>
				                <div class="col-lg-9 col-md-8">
				                    <div class="input-group">
				                        <span class="input-group-text"><i class="fa fa-users"></i></span>
				                        <input type="text" id="group_regex" name="group_regex" class="form-control" value="{$regex.group_regex|escape:html}" required>
				                    </div>
				                    <small class="text-muted mt-1">
				                        Regex to match against a group or multiple groups. Delimiters are already added, and PCRE_CASELESS is added after for case insensitivity.<br>
				                        Example of matching a single group: <code>alt\.binaries\.example</code><br>
				                        Example of matching multiple groups: <code>alt\.binaries.*</code>
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
				                        <textarea id="regex" name="regex" class="form-control" rows="4" required>{$regex.regex|escape:html}</textarea>
				                    </div>
				                    <small class="text-muted mt-1">
				                        Regex to use when categorizing releases.<br>
				                        The regex delimiters are not added, you MUST add them. See <a href="http://php.net/manual/en/regexp.reference.delimiters" target="_blank">this</a> page.<br>
				                        To make the regex case insensitive, add <code>i</code> after the last delimiter.
				                    </small>
				                </div>
				            </div>

				            <div class="row mb-4">
				                <div class="col-lg-3 col-md-4">
				                    <label for="description" class="form-label fw-bold">Description:</label>
				                </div>
				                <div class="col-lg-9 col-md-8">
				                    <div class="input-group">
				                        <span class="input-group-text"><i class="fa fa-align-left"></i></span>
				                        <textarea id="description" name="description" class="form-control" rows="3">{$regex.description|escape:html}</textarea>
				                    </div>
				                    <small class="text-muted mt-1">
				                        Description for this regex. You can include an example usenet subject this regex would match on.
				                    </small>
				                </div>
				            </div>

				            <div class="row mb-4">
				                <div class="col-lg-3 col-md-4">
				                    <label for="ordinal" class="form-label fw-bold">Ordinal:</label>
				                </div>
				                <div class="col-lg-9 col-md-8">
				                    <div class="input-group">
				                        <span class="input-group-text"><i class="fa fa-sort-numeric-asc"></i></span>
				                        <input type="number" id="ordinal" name="ordinal" class="form-control" value="{$regex.ordinal}" min="0" required>
				                    </div>
				                    <small class="text-muted mt-1">
				                        The order to run this regex in. Must be a number, 0 or higher.<br>
				                        If multiple regex have the same ordinal, MySQL will randomly sort them.
				                    </small>
				                </div>
				            </div>

				            <div class="row mb-4">
				                <div class="col-lg-3 col-md-4">
				                    <label class="form-label fw-bold">Active:</label>
				                </div>
				                <div class="col-lg-9 col-md-8">
				                    <div class="border rounded p-3 bg-light">
				                        {foreach from=$status_ids item=id key=k}
				                            <div class="form-check mb-2">
				                                <input class="form-check-input" type="radio" name="status" id="status{$id}" value="{$id}" {if $regex.status == $id}checked{/if}>
				                                <label class="form-check-label" for="status{$id}">
				                                    {$status_names[$k]}
				                                </label>
				                            </div>
				                        {/foreach}
				                    </div>
				                    <small class="text-muted mt-1">
				                        Only active regex are used during the collection matching process.
				                    </small>
				                </div>
				            </div>

				            <div class="row mb-4">
				                <div class="col-lg-3 col-md-4">
				                    <label for="categories_id" class="form-label fw-bold">Category:</label>
				                </div>
				                <div class="col-lg-9 col-md-8">
				                    <div class="input-group">
				                        <span class="input-group-text"><i class="fa fa-folder-open"></i></span>
				                        <select id="categories_id" name="categories_id" class="form-select" required>
				                            {html_options values=$category_ids output=$category_names selected=$regex.categories_id}
				                        </select>
				                    </div>
				                    <small class="text-muted mt-1">
				                        Select a category which releases matched to this regex will go into.
				                    </small>
				                </div>
				            </div>
				        </form>
				    </div>

				    <div class="card-footer">
				        <div class="d-flex justify-content-between">
				            <a href="{{url("/admin/category_regexes-list")}}" class="btn btn-outline-secondary">
				                <i class="fa fa-times me-2"></i>Cancel
				            </a>
				            <button type="submit" form="regexForm" class="btn btn-success">
				                <i class="fa fa-save me-2"></i>Save Changes
				            </button>
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

				    // Form validation
				    const form = document.getElementById('regexForm');
				    form.addEventListener('submit', function(event) {
				        if (!form.checkValidity()) {
				            event.preventDefault();
				            event.stopPropagation();
				        }
				        form.classList.add('was-validated');
				    });
				});
				{/literal}
				</script>

				<style>
				{literal}
				/* Typography improvements */
				.form-label {
				    font-weight: 500;
				}

				/* Code styling */
				code {
				    background-color: rgba(0, 0, 0, 0.05);
				    padding: 2px 5px;
				    border-radius: 3px;
				    font-family: monospace;
				    font-size: 0.85rem;
				}

				/* Form field enhancements */
				textarea.form-control {
				    font-family: monospace;
				    font-size: 0.9rem;
				}

				/* Responsive adjustments */
				@media (max-width: 767.98px) {
				    .form-label {
				        margin-bottom: 0.5rem;
				    }
				}

				/* Improve input focus styling */
				.form-control:focus, .form-select:focus {
				    border-color: #80bdff;
				    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
				}

				/* Custom validation styling */
				.was-validated .form-control:invalid,
				.was-validated .form-select:invalid {
				    border-color: #dc3545;
				    padding-right: calc(1.5em + 0.75rem);
				    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
				    background-repeat: no-repeat;
				    background-position: right calc(0.375em + 0.1875rem) center;
				    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
				}
				{/literal}
				</style>
