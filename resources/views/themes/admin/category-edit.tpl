<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0">{$title}</h4>
            <a href="{{url("/admin/category-list")}}" class="btn btn-outline-secondary">
                <i class="fa fa-arrow-left me-2"></i>Back to Categories
            </a>
        </div>
    </div>

    {if isset($error) && $error != ''}
        <div class="alert alert-danger m-3">
            <i class="fa fa-exclamation-circle me-2"></i>{$error}
        </div>
    {/if}

    <div class="card-body">
        <form action="{{url("/admin/category-edit?action=submit")}}" method="POST" id="categoryForm">
            {{csrf_field()}}
            <input type="hidden" name="id" value="{$category.id}"/>

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label class="form-label fw-bold">Title:</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    <p class="form-control-plaintext">{$category.title}</p>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label for="parentcat" class="form-label fw-bold">Parent Category:</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-folder-open"></i></span>
                        {$category.parent.title}
                    </div>
                    <small class="text-muted">Select a parent category or "No Parent" for top-level categories</small>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label for="description" class="form-label fw-bold">Description:</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-align-left"></i></span>
                        <input type="text" id="description" name="description" class="form-control" value="{$category.description}"/>
                    </div>
                    <small class="text-muted">Brief explanation of what belongs in this category</small>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label for="minsizetoformrelease" class="form-label fw-bold">Minimum Size (Bytes):</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-download"></i></span>
                        <input type="number" id="minsizetoformrelease" name="minsizetoformrelease" class="form-control" value="{$category.minsizetoformrelease}"/>
                    </div>
                    <small class="text-muted">Minimum file size for releases in this category (in bytes)</small>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label for="maxsizetoformrelease" class="form-label fw-bold">Maximum Size (Bytes):</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-upload"></i></span>
                        <input type="number" id="maxsizetoformrelease" name="maxsizetoformrelease" class="form-control" value="{$category.maxsizetoformrelease}"/>
                    </div>
                    <small class="text-muted">Maximum file size for releases in this category (in bytes)</small>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label class="form-label fw-bold">Status:</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div class="form-check form-check-inline">
                        <input type="radio" id="status_1" name="status" value="1" class="form-check-input" {if $category.status == 1}checked{/if}>
                        <label for="status_1" class="form-check-label">Active</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input type="radio" id="status_0" name="status" value="0" class="form-check-input" {if $category.status == 0}checked{/if}>
                        <label for="status_0" class="form-check-label">Inactive</label>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted">Inactive categories won't appear in menus but can still be used for release matching</small>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label class="form-label fw-bold">Preview:</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div class="form-check form-check-inline">
                        <input type="radio" id="disablepreview_0" name="disablepreview" value="0" class="form-check-input" {if $category.disablepreview == 0}checked{/if}>
                        <label for="disablepreview_0" class="form-check-label">Enabled</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input type="radio" id="disablepreview_1" name="disablepreview" value="1" class="form-check-input" {if $category.disablepreview == 1}checked{/if}>
                        <label for="disablepreview_1" class="form-check-label">Disabled</label>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted">Disabling prevents ffmpeg from generating previews for releases in this category</small>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="card-footer">
        <div class="d-flex justify-content-between">
            <button type="button" class="btn btn-outline-secondary" onclick="window.location='{{url("/admin/category-list")}}'">
                <i class="fa fa-times me-2"></i>Cancel
            </button>
            <button type="submit" form="categoryForm" class="btn btn-success">
                <i class="fa fa-save me-2"></i>Save Changes
            </button>
        </div>
    </div>
</div>

<script>
{literal}
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const form = document.getElementById('categoryForm');
    form.addEventListener('submit', function(event) {
        // Basic validation example - can be expanded
        const minSize = document.getElementById('minsizetoformrelease').value;
        const maxSize = document.getElementById('maxsizetoformrelease').value;

        // Validate min/max size relationship
        if (parseInt(minSize) > parseInt(maxSize) && parseInt(maxSize) > 0) {
            event.preventDefault();
            alert('Minimum size cannot be greater than maximum size');
            return false;
        }

        // Additional validation can be added here
    });
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

/* Improve input focus states */
.form-control:focus,
.form-select:focus {
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

/* Form check styling */
.form-check-input:checked {
    background-color: #198754;
    border-color: #198754;
}
{/literal}
</style>
