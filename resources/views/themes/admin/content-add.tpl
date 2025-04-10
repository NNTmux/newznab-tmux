<div class="card">
				    <div class="card-header">
				        <div class="d-flex justify-content-between align-items-center">
				            <h4 class="mb-0">{$title}</h4>
				            <a href="{{url("/admin/content-list")}}" class="btn btn-outline-secondary">
				                <i class="fa fa-arrow-left me-2"></i>Back to Content List
				            </a>
				        </div>
				    </div>

				    <div class="card-body">
				        <form action="{{url("/admin/content-add?action=submit")}}" method="post" id="contentForm">
				            {{csrf_field()}}
				            <input type="hidden" name="id" {if isset($content)}value="{$content->id}"{/if}/>

				            <div class="row mb-4">
				                <div class="col-lg-3 col-md-4">
				                    <label for="title" class="form-label fw-bold">Title:</label>
				                </div>
				                <div class="col-lg-9 col-md-8">
				                    <div class="input-group">
				                        <span class="input-group-text"><i class="fa fa-heading"></i></span>
				                        <input id="title" class="form-control" name="title" type="text"
				                               {if isset($content)}value="{$content->title}"{/if} required/>
				                    </div>
				                </div>
				            </div>

				            <div class="row mb-4">
				                <div class="col-lg-3 col-md-4">
				                    <label for="url" class="form-label fw-bold">URL:</label>
				                </div>
				                <div class="col-lg-9 col-md-8">
				                    <div class="input-group">
				                        <span class="input-group-text"><i class="fa fa-link"></i></span>
				                        <input id="url" class="form-control" name="url" type="text"
				                               {if isset($content)}value="{$content->url}"{/if}/>
				                    </div>
				                    <small class="text-muted">The URL segment for this content (e.g., "about" for "/about")</small>
				                </div>
				            </div>

				            <div class="row mb-4">
				                <div class="col-lg-3 col-md-4">
				                    <label for="body" class="form-label fw-bold">Body Content:</label>
				                </div>
				                <div class="col-lg-9 col-md-8">
				                    <textarea id="body" name="body" class="form-control" rows="10">{if isset($content)}{$content->body}{/if}</textarea>
				                    <small class="text-muted">Supports HTML formatting</small>
				                </div>
				            </div>

				            <div class="row mb-4">
				                <div class="col-lg-3 col-md-4">
				                    <label for="metadescription" class="form-label fw-bold">Meta Description:</label>
				                </div>
				                <div class="col-lg-9 col-md-8">
				                    <div class="input-group">
				                        <span class="input-group-text"><i class="fa fa-comment"></i></span>
				                        <textarea id="metadescription" name="metadescription" class="form-control" rows="2">{if isset($content)}{$content->metadescription}{/if}</textarea>
				                    </div>
				                    <small class="text-muted">For search engine optimization (150-160 characters recommended)</small>
				                </div>
				            </div>

				            <div class="row mb-4">
				                <div class="col-lg-3 col-md-4">
				                    <label for="metakeywords" class="form-label fw-bold">Meta Keywords:</label>
				                </div>
				                <div class="col-lg-9 col-md-8">
				                    <div class="input-group">
				                        <span class="input-group-text"><i class="fa fa-tags"></i></span>
				                        <textarea id="metakeywords" name="metakeywords" class="form-control" rows="2">{if isset($content)}{$content->metakeywords}{/if}</textarea>
				                    </div>
				                    <small class="text-muted">Comma-separated keywords</small>
				                </div>
				            </div>

				            <div class="row mb-4">
				                <div class="col-lg-3 col-md-4">
				                    <label for="contenttype" class="form-label fw-bold">Content Type:</label>
				                </div>
				                <div class="col-lg-9 col-md-8">
				                    <div class="input-group">
				                        <span class="input-group-text"><i class="fa fa-file-alt"></i></span>
				                        <select id="contenttype" name="contenttype" class="form-select">
				                            {html_options options=$contenttypelist selected=$content->contenttype}
				                        </select>
				                    </div>
				                </div>
				            </div>

				            <div class="row mb-4">
				                <div class="col-lg-3 col-md-4">
				                    <label for="role" class="form-label fw-bold">Visible To:</label>
				                </div>
				                <div class="col-lg-9 col-md-8">
				                    <div class="input-group">
				                        <span class="input-group-text"><i class="fa fa-users"></i></span>
				                        <select id="role" name="role" class="form-select">
				                            {html_options options=$rolelist selected=$content->role}
				                        </select>
				                    </div>
				                    <small class="text-muted">Only appropriate for articles and useful links</small>
				                </div>
				            </div>

				            <div class="row mb-4">
				                <div class="col-lg-3 col-md-4">
				                    <label class="form-label fw-bold">Status:</label>
				                </div>
				                <div class="col-lg-9 col-md-8">
				                    <div class="form-check form-check-inline">
				                        <input type="radio" id="status_1" name="status" value="1" class="form-check-input" {if !isset($content) || $content->status == 1}checked{/if}>
				                        <label for="status_1" class="form-check-label">Enabled</label>
				                    </div>
				                    <div class="form-check form-check-inline">
				                        <input type="radio" id="status_0" name="status" value="0" class="form-check-input" {if isset($content) && $content->status == 0}checked{/if}>
				                        <label for="status_0" class="form-check-label">Disabled</label>
				                    </div>
				                </div>
				            </div>

				            <div class="row mb-4">
				                <div class="col-lg-3 col-md-4">
				                    <label for="ordinal" class="form-label fw-bold">Ordinal:</label>
				                </div>
				                <div class="col-lg-9 col-md-8">
				                    <div class="input-group">
				                        <span class="input-group-text"><i class="fa fa-sort-numeric-up"></i></span>
				                        <input id="ordinal" name="ordinal" type="number" class="form-control"
				                               {if isset($content)}value="{$content->ordinal}"{else}value="0"{/if}/>
				                    </div>
				                    <small class="text-muted">
				                        If you set the ordinal = 1, then all ordinals greater than 0 will be renumbered.
				                        This allows new content to be at the top without having to renumber all previous content.
				                        If you set ordinal = 0, it will be at the top, sorted by ID (order added).
				                    </small>
				                </div>
				            </div>

				            <div class="row">
				                <div class="col-lg-3 col-md-4">
				                    <label for="showinmenu" class="form-label fw-bold">Show in Menu:</label>
				                </div>
				                <div class="col-lg-9 col-md-8">
				                    <div class="form-check form-switch">
				                        <input type="checkbox" id="showinmenu" name="showinmenu" value="1" class="form-check-input"
				                               {if isset($content) && $content->showinmenu == 1}checked{/if}>
				                        <label for="showinmenu" class="form-check-label">Display this content in site navigation</label>
				                    </div>
				                </div>
				            </div>
				        </form>
				    </div>

				    <div class="card-footer">
				        <div class="d-flex justify-content-between">
				            <button type="button" class="btn btn-outline-secondary" onclick="window.location='{{url("/admin/content-list")}}'">
				                <i class="fa fa-times me-2"></i>Cancel
				            </button>
				            <button type="submit" form="contentForm" class="btn btn-success">
				                <i class="fa fa-save me-2"></i>Save Content
				            </button>
				        </div>
				    </div>
				</div>

				<script>
				{literal}
				document.addEventListener('DOMContentLoaded', function() {
				    // Initialize any rich text editor for the body field
				    // This is a placeholder - replace with your actual rich text editor initialization
				    // For example, if using TinyMCE:
				    if (typeof tinymce !== 'undefined') {
				        tinymce.init({
				            selector: '#body',
				            height: 400,
				            plugins: [
				                'advlist autolink lists link image charmap print preview anchor',
				                'searchreplace visualblocks code fullscreen',
				                'insertdatetime media table paste code help wordcount'
				            ],
				            toolbar: 'undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help'
				        });
				    }

				    // Form validation
				    const form = document.getElementById('contentForm');
				    form.addEventListener('submit', function(event) {
				        if (!form.checkValidity()) {
				            event.preventDefault();
				            event.stopPropagation();
				        }
				        form.classList.add('was-validated');
				    });

				    // URL generator from title
				    const titleInput = document.getElementById('title');
				    const urlInput = document.getElementById('url');

				    if (titleInput && urlInput && urlInput.value === '') {
				        titleInput.addEventListener('blur', function() {
				            if (urlInput.value === '') {
				                // Generate URL slug from title
				                const slug = titleInput.value
				                    .toLowerCase()
				                    .replace(/[^\w\s-]/g, '') // Remove special chars
				                    .replace(/\s+/g, '-')     // Replace spaces with hyphens
				                    .replace(/--+/g, '-')     // Replace multiple hyphens with single hyphen
				                    .trim();                   // Trim leading/trailing spaces

				                urlInput.value = slug;
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

				/* Visual indicator for required fields */
				.form-control:required {
				    border-left: 4px solid #0d6efd;
				}

				/* Responsive adjustments */
				@media (max-width: 767.98px) {
				    .card-footer .btn {
				        padding: 0.375rem 0.75rem;
				    }

				    .input-group .input-group-text {
				        padding: 0.375rem 0.75rem;
				    }
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

				/* Improve textarea appearance */
				textarea {
				    min-height: 80px;
				}

				#body {
				    min-height: 300px;
				}

				/* Improve input focus states */
				.form-control:focus,
				.form-select:focus {
				    border-color: #80bdff;
				    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
				}

				/* Form check switch styling */
				.form-check-input:checked {
				    background-color: #198754;
				    border-color: #198754;
				}
				{/literal}
				</style>
