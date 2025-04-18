<div class="card">
		    <div class="card-header">
		        <div class="d-flex justify-content-between align-items-center">
		            <h4 class="mb-0">{$title}</h4>
		        </div>
		    </div>

		    <div class="card-body">
		        <p class="mb-4">
		            Import NZB's from a folder or via the browser into the system. Specify the full file path to a folder containing
		            NZB's. Importing will add the release to your database, compress the NZB and store it in the nzbfiles/ folder.
		        </p>

		        <div class="alert alert-info">
		            <h5 class="alert-heading"><i class="fa fa-info-circle me-2"></i>Important Information</h5>
		            <ul class="mb-0">
		                <li>If you are importing a large number of NZB files, run the nzb-import script in misc/testing/ from the
		                    command line and pass in the folder path as the first argument.
		                </li>
		                <li>If you are running the script in misc/testing/ from the command line you can pass "true" (no quotes) as the
		                    second argument to use the NZB filename as the release name.
		                </li>
		                <li>Groups contained in the NZB's should be added to the site before the import is run.</li>
		                <li>If you re-import the same NZB it will not be added a second time.</li>
		                <li>If imported successfully the NZB will be deleted.</li>
		            </ul>
		        </div>
		        <div class="card">
		            <div class="card-header bg-light">
		                <h5 class="mb-0">Import From Browser</h5>
		            </div>
		            <div class="card-body">
		                <form action="{{url("/admin/nzb-import#results")}}" method="POST" enctype="multipart/form-data" id="browserImportForm">
		                    {{csrf_field()}}

		                    <div class="row mb-4">
		                        <div class="col-lg-3 col-md-4">
		                            <label for="uploadedfiles" class="form-label fw-bold">NZB Files:</label>
		                        </div>
		                        <div class="col-lg-9 col-md-8">
		                            <div class="input-group">
		                                <span class="input-group-text"><i class="fa fa-file-upload"></i></span>
		                                <input id="uploadedfiles" name="uploadedfiles[]" type="file" class="form-control" multiple accept=".nzb"/>
		                            </div>
		                            <small class="text-muted">Select one or more .nzb files. These NZBs will not be deleted once imported.</small>
		                            <div id="upload-progress" class="progress mt-2 d-none">
		                                <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
		                            </div>
		                            <div id="upload-messages" class="mt-2"></div>
		                        </div>
		                    </div>
		                </form>
		            </div>
		            <div class="card-footer">
		                <div class="d-flex justify-content-end">
		                    <button type="submit" form="browserImportForm" id="uploadButton" class="btn btn-success">
		                        <i class="fa fa-upload me-2"></i>Import from Browser
		                    </button>
		                </div>
		            </div>
		        </div>

		        {if !empty($output)}
		            <div class="card mt-4">
		                <div class="card-header bg-light">
		                    <h5 class="mb-0" id="results">Import Results</h5>
		                </div>
		                <div class="card-body">
		                    {$output}
		                </div>
		            </div>
		        {/if}
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

		/* Form check switch styling */
		.form-check-input:checked {
		    background-color: #198754;
		    border-color: #198754;
		}

		/* File input styling */
		input[type="file"].form-control {
		    padding: 0.375rem 0.75rem;
		}

		/* Alert styling enhancement */
		.alert-info ul {
		    padding-left: 1.25rem;
		    margin-top: 0.5rem;
		}
		{/literal}
		</style>

		<script>
		{literal}
		document.addEventListener('DOMContentLoaded', function() {
		    // Add any file input enhancement here if needed
		    const fileInput = document.getElementById('uploadedfiles');
		    if (fileInput) {
		        fileInput.addEventListener('change', function(e) {
		            const fileCount = e.target.files.length;
		            if (fileCount > 0) {
		                console.log(`${fileCount} file(s) selected`);
		                // You can add visual feedback here
		            }
		        });
		    }
		});

        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('browserImportForm');
            const fileInput = document.getElementById('uploadedfiles');
            const uploadButton = document.getElementById('uploadButton');
            const progressBar = document.querySelector('.progress-bar');
            const progressContainer = document.getElementById('upload-progress');
            const messagesContainer = document.getElementById('upload-messages');

            if (fileInput) {
                fileInput.addEventListener('change', function(e) {
                    const files = e.target.files;
                    if (files.length > 0) {
                        // Reset messages
                        messagesContainer.innerHTML = '';

                        // Validate file extensions
                        let allValid = true;
                        for (let i = 0; i < files.length; i++) {
                            if (!files[i].name.toLowerCase().endsWith('.nzb')) {
                                const alertDiv = document.createElement('div');
                                alertDiv.className = 'alert alert-warning';
                                alertDiv.innerHTML = `<i class="fa fa-exclamation-triangle"></i> "${files[i].name}" is not an NZB file`;
                                messagesContainer.appendChild(alertDiv);
                                allValid = false;
                            }
                        }

                        if (!allValid) {
                            uploadButton.disabled = true;
                            return;
                        }

                        uploadButton.disabled = false;
                        const alertDiv = document.createElement('div');
                        alertDiv.className = 'alert alert-info';
                        alertDiv.innerHTML = `<i class="fa fa-info-circle"></i> ${files.length} file(s) selected and ready for upload`;
                        messagesContainer.appendChild(alertDiv);
                    }
                });
            }

            if (form) {
                form.addEventListener('submit', function(e) {
                    const files = fileInput.files;
                    if (files.length === 0) {
                        e.preventDefault();
                        const alertDiv = document.createElement('div');
                        alertDiv.className = 'alert alert-danger';
                        alertDiv.innerHTML = '<i class="fa fa-times-circle"></i> Please select at least one NZB file to upload';
                        messagesContainer.innerHTML = '';
                        messagesContainer.appendChild(alertDiv);
                        return;
                    }

                    // Show progress bar
                    progressContainer.classList.remove('d-none');
                    uploadButton.disabled = true;

                    // Simulate upload progress (in real world this would use XHR/fetch with progress events)
                    let progress = 0;
                    const interval = setInterval(function() {
                        progress += 10;
                        if (progress > 90) {
                            clearInterval(interval);
                        }
                        progressBar.style.width = progress + '%';
                    }, 300);
                });
            }
        });
		{/literal}
		</script>
