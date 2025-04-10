<div class="card">
			    <div class="card-header">
			        <div class="d-flex justify-content-between align-items-center">
			            <div>
			                <h4 class="mb-0">{$title}</h4>
			            </div>
			            <div>
			                <a href="{{url("/admin/binaryblacklist-list")}}" class="btn btn-sm btn-outline-primary">
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

			        {{Form::open(['url'=> "admin/binaryblacklist-edit?action=submit", 'id' => 'blacklistForm', 'class' => 'needs-validation'])}}
			            <input type="hidden" name="id" value="{if (isset({$regex.id}))} {$regex.id} {else}''{/if}"/>

			            <div class="row mb-4">
			                <div class="col-lg-3 col-md-4">
			                    <label for="groupname" class="form-label fw-bold">Group Name:</label>
			                </div>
			                <div class="col-lg-9 col-md-8">
			                    <div class="input-group">
			                        <span class="input-group-text"><i class="fa fa-layer-group"></i></span>
			                        <input type="text" id="groupname" name="groupname" class="form-control" value="{$regex.groupname|escape:html}" required>
			                    </div>
			                    <small class="text-muted mt-1">
			                        <i class="fa fa-info-circle me-1"></i>The full name of a valid newsgroup. (Wildcard in the format 'alt.binaries.*')
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
			                        <i class="fa fa-info-circle me-1"></i>The regex to be applied. (Note: Beginning and Ending / are already included)
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
			                        <i class="fa fa-info-circle me-1"></i>A description for this regex
			                    </small>
			                </div>
			            </div>

			            <div class="row mb-4">
			                <div class="col-lg-3 col-md-4">
			                    <label class="form-label fw-bold">Message Field:</label>
			                </div>
			                <div class="col-lg-9 col-md-8">
			                    <div class="border rounded p-3 bg-light">
			                        {foreach from=$msgcol_ids key=i item=id name=msgcol}
			                            <div class="form-check mb-2 {if $smarty.foreach.msgcol.last}mb-0{/if}">
			                                <input class="form-check-input" type="radio" name="msgcol" id="msgcol{$id}" value="{$id}" {if $regex.msgcol == $id}checked{/if}>
			                                <label class="form-check-label" for="msgcol{$id}">
			                                    {$msgcol_names[$i]}
			                                </label>
			                            </div>
			                        {/foreach}
			                    </div>
			                    <small class="text-muted mt-1">
			                        <i class="fa fa-info-circle me-1"></i>Which field in the message to apply the black/white list to.
			                    </small>
			                </div>
			            </div>

			            <div class="row mb-4">
			                <div class="col-lg-3 col-md-4">
			                    <label class="form-label fw-bold">Status:</label>
			                </div>
			                <div class="col-lg-9 col-md-8">
			                    <div class="border rounded p-3 bg-light">
			                        {foreach from=$status_ids key=i item=id name=status}
			                            <div class="form-check mb-2 {if $smarty.foreach.status.last}mb-0{/if}">
			                                <input class="form-check-input" type="radio" name="status" id="status{$id}" value="{$id}" {if $regex.status == $id}checked{/if}>
			                                <label class="form-check-label" for="status{$id}">
			                                    {$status_names[$i]}
			                                </label>
			                            </div>
			                        {/foreach}
			                    </div>
			                    <small class="text-muted mt-1">
			                        <i class="fa fa-info-circle me-1"></i>Only active regexes are applied during the release process.
			                    </small>
			                </div>
			            </div>

			            <div class="row mb-4">
			                <div class="col-lg-3 col-md-4">
			                    <label class="form-label fw-bold">Type:</label>
			                </div>
			                <div class="col-lg-9 col-md-8">
			                    <div class="border rounded p-3 bg-light">
			                        {foreach from=$optype_ids key=i item=id name=optype}
			                            <div class="form-check mb-2 {if $smarty.foreach.optype.last}mb-0{/if}">
			                                <input class="form-check-input" type="radio" name="optype" id="optype{$id}" value="{$id}" {if $regex.optype == $id}checked{/if}>
			                                <label class="form-check-label" for="optype{$id}">
			                                    {$optype_names[$i]}
			                                </label>
			                            </div>
			                        {/foreach}
			                    </div>
			                    <small class="text-muted mt-1">
			                        <i class="fa fa-info-circle me-1"></i>Black will exclude all messages for a group which match this regex. White will include only those which match.
			                    </small>
			                </div>
			            </div>
			        {{Form::close()}}
			    </div>

			    <div class="card-footer">
			        <div class="d-flex justify-content-between">
			            <a href="{{url("/admin/binaryblacklist-list")}}" class="btn btn-outline-secondary">
			                <i class="fa fa-times me-2"></i>Cancel
			            </a>
			            <button type="submit" form="blacklistForm" class="btn btn-success">
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
			    const form = document.getElementById('blacklistForm');
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
			/* Responsive adjustments */
			@media (max-width: 767.98px) {
			    .form-label {
			        margin-bottom: 0.5rem;
			    }
			}

			/* Custom form styling */
			.form-control:focus {
			    border-color: #80bdff;
			    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
			}

			/* Improve radio button spacing */
			.form-check {
			    padding-left: 1.8rem;
			}

			.form-check-input {
			    margin-top: 0.3rem;
			}

			/* Help text styling */
			small.text-muted {
			    display: block;
			    font-size: 80%;
			}
			{/literal}
			</style>
