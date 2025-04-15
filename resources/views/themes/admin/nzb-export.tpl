<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0">{$title}</h4>
        </div>
    </div>

    <div class="card-body">
        <p class="mb-4">
            Export NZBs from the system into a folder. Specify the full file path to a folder.
            If you are exporting a large number of NZB files, run this script from the command line and pass in the folder
            path as the first argument.
        </p>

        <div class="alert alert-info">
            <h5 class="alert-heading"><i class="fa fa-info-circle me-2"></i>Command Line Usage</h5>
            <p class="mb-2">Example command line usage:</p>
            <pre class="bg-light p-2 mb-0"><code>php admin/nzb-export /path/to/export/into 01/01/2008 01/01/2010 -1 1050</code></pre>
            <small>Arguments: output_path from_date to_date groups_id(optional, use -1) categories_id(optional)</small>
        </div>

        <form action="{{url("/admin/nzb-export")}}" method="POST" id="exportForm">
            {{csrf_field()}}

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label for="folder" class="form-label fw-bold">Folder:</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-folder-open"></i></span>
                        <input id="folder" class="form-control" name="folder" type="text" value="{$folder}"/>
                    </div>
                    <small class="text-muted">Windows file paths should be specified with forward slashes e.g. c:/temp/</small>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label for="postfrom" class="form-label fw-bold">Posted Between:</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div class="row g-2">
                        <div class="col-md-5">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-calendar-alt"></i></span>
                                <input id="postfrom" class="form-control" name="postfrom" type="text" value="{$fromdate}" placeholder="dd/mm/yyyy"/>
                            </div>
                        </div>
                        <div class="col-md-2 text-center d-flex align-items-center justify-content-center">
                            <span class="fw-bold">and</span>
                        </div>
                        <div class="col-md-5">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-calendar-alt"></i></span>
                                <input id="postto" class="form-control" name="postto" type="text" value="{$todate}" placeholder="dd/mm/yyyy"/>
                            </div>
                        </div>
                    </div>
                    <small class="text-muted">Posted to usenet between a date range specified in the format dd/mm/yyyy</small>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label for="group" class="form-label fw-bold">Group:</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-layer-group"></i></span>
                        <select id="group" name="group" class="form-select">
                            {html_options options=$grouplist selected=$group}
                        </select>
                    </div>
                    <small class="text-muted">Posted to this newsgroup</small>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label for="categories_id" class="form-label fw-bold">Category:</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-tag"></i></span>
                        <select id="categories_id" name="categories_id" class="form-select">
                            {html_options options=$catlist selected=$cat}
                        </select>
                    </div>
                    <small class="text-muted">Posted to this category</small>
                </div>
            </div>
        </form>

        {if !empty($output)}
            <div class="card mt-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Export Results</h5>
                </div>
                <div class="card-body">
                    {$output}
                </div>
            </div>
        {/if}
    </div>

    <div class="card-footer">
        <div class="d-flex justify-content-end">
            <button type="submit" form="exportForm" class="btn btn-success">
                <i class="fa fa-file-export me-2"></i>Export NZB Files
            </button>
        </div>
    </div>
</div>

<style>
{literal}
/* Form styling improvements */
.form-label {
    margin-bottom: 0.5rem;
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

/* Improve input focus states */
.form-control:focus,
.form-select:focus {
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

/* Code block styling */
pre {
    border-radius: 0.25rem;
    font-size: 0.875rem;
}

pre code {
    white-space: pre;
    color: #333;
    font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
}

/* Alert styling */
.alert-info {
    background-color: #cff4fc;
    border-color: #b6effb;
    color: #055160;
}

/* Date input styling */
input[type="text"].form-control {
    padding: 0.375rem 0.75rem;
}
{/literal}
</style>

<script>
{literal}
document.addEventListener('DOMContentLoaded', function() {
    // Add datepicker functionality if available
    if (typeof flatpickr === 'function') {
        flatpickr("#postfrom, #postto", {
            dateFormat: "d/m/Y",
            allowInput: true
        });
    }

    // Form validation
    const form = document.getElementById('exportForm');
    form.addEventListener('submit', function(event) {
        const folderInput = document.getElementById('folder');
        if (!folderInput.value.trim()) {
            event.preventDefault();
            alert('Please enter a folder path for export.');
            folderInput.focus();
            return false;
        }
    });
});
{/literal}
</script>
