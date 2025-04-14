<div class="card">
					    <div class="card-header">
					        <div class="d-flex justify-content-between align-items-center">
					            <h4 class="mb-0">{$title}</h4>
					            <a href="{{url("/admin/release-list")}}" class="btn btn-outline-secondary">
					                <i class="fa fa-arrow-left me-2"></i>Back to Releases
					            </a>
					        </div>
					    </div>

					    <div class="card-body">
					        <form action="release-edit?action=submit" method="POST" class="needs-validation" novalidate>
					            {{csrf_field()}}
					            <input type="hidden" name="id" value="{$release.id}"/>
					            <input type="hidden" name="guid" value="{$release.guid}"/>

					            <!-- Basic Information -->
					            <div class="row mb-4">
					                <div class="col-12">
					                    <h5 class="border-bottom pb-2 mb-3">Release Information</h5>
					                </div>
					               <div class="col-md-12 mb-3">
					                    <label for="name" class="form-label">Original Name</label>
					                    <input id="name" class="form-control" name="name" value="{$release.name|escape:'htmlall'}" required disabled />
					                    <div class="invalid-feedback">Please enter the original name</div>
					                </div>
					                <div class="col-md-12 mb-3">
					                    <label for="searchname" class="form-label">Search Name</label>
					                    <input id="searchname" class="form-control" name="searchname" value="{$release.searchname|escape:'htmlall'}" required />
					                    <div class="invalid-feedback">Please enter a search name</div>
					                </div>
					                <div class="col-md-12 mb-3">
					                    <label for="fromname" class="form-label">From Name</label>
					                    <input id="fromname" class="form-control" name="fromname" value="{$release.fromname|escape:'htmlall'}" disabled />
					                </div>
					            </div>

					            <!-- Classification & Details -->
					            <div class="row mb-4">
					                <div class="col-12">
					                    <h5 class="border-bottom pb-2 mb-3">Classification & Details</h5>
					                </div>
					                <div class="col-md-6 mb-3">
					                    <label for="category" class="form-label">Category</label>
					                    <select id="category" name="category" class="form-select">
					                        {html_options options=$catlist selected=$release.categories_id}
					                    </select>
					                </div>
					                <div class="col-md-6 mb-3">
					                    <label for="size" class="form-label">Size</label>
					                    <div class="input-group">
					                        <input id="size" class="form-control" name="size" value="{$release.size}" disabled />
					                        <span class="input-group-text">bytes</span>
					                    </div>
					                </div>
					                <div class="col-md-4 mb-3">
					                    <label for="totalpart" class="form-label">Parts</label>
					                    <input id="totalpart" class="form-control" type="number" name="totalpart" value="{$release.totalpart}" disabled />
					                </div>
					                <div class="col-md-4 mb-3">
					                    <label for="grabs" class="form-label">Grabs</label>
					                    <input id="grabs" class="form-control" type="number" name="grabs" value="{$release.grabs}" disabled />
					                </div>
					                <div class="col-md-4 mb-3">
					                    <label for="group" class="form-label">Group</label>
					                    <input id="group" class="form-control" type="text" value="{$release.group_name}" disabled readonly />
					                </div>
					            </div>

					            <!-- External IDs -->
					            <div class="row mb-4">
					                <div class="col-12">
					                    <h5 class="border-bottom pb-2 mb-3">External References</h5>
					                </div>
					                <div class="col-md-3 mb-3">
					                    <label for="videos_id" class="form-label">Video ID</label>
					                    <input id="videos_id" class="form-control" type="number" name="videos_id" value="{$release.videos_id}" />
					                </div>
					                <div class="col-md-3 mb-3">
					                    <label for="tv_episodes_id" class="form-label">TV Episode ID</label>
					                    <input id="tv_episodes_id" class="form-control" type="number" name="tv_episodes_id" value="{$release.tv_episodes_id}" />
					                </div>
					                <div class="col-md-3 mb-3">
					                    <label for="imdbid" class="form-label">IMDB ID</label>
					                    <div class="input-group">
					                        <span class="input-group-text">tt</span>
					                        <input id="imdbid" class="form-control" name="imdbid" value="{$release.imdbid}" />
					                    </div>
					                </div>
					                <div class="col-md-3 mb-3">
					                    <label for="anidbid" class="form-label">AniDB ID</label>
					                    <input id="anidbid" class="form-control" name="anidbid" value="{$release.anidbid}" />
					                </div>
					            </div>

					            <!-- Dates -->
					            <div class="row mb-4">
					                <div class="col-12">
					                    <h5 class="border-bottom pb-2 mb-3">Dates</h5>
					                </div>
					                <div class="col-md-6 mb-3">
					                    <label for="postdate" class="form-label">Posted Date</label>
					                    <input id="postdate" class="form-control" type="datetime-local" name="postdate" value="{$release.postdate}" />
					                </div>
					                <div class="col-md-6 mb-3">
					                    <label for="adddate" class="form-label">Added Date</label>
					                    <input id="adddate" class="form-control" type="datetime-local" name="adddate" value="{$release.adddate}" />
					                </div>
					            </div>

					            <div class="d-flex justify-content-between">
					                <a href="{{url("/admin/release-list")}}" class="btn btn-outline-secondary">
					                    <i class="fa fa-times me-2"></i>Cancel
					                </a>
					                <div class="btn-group">
					                    <a href="#" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#filelistModal" data-guid="{$release.guid}">
					                        <i class="fa fa-file-text-o me-2"></i>View Files
					                    </a>
					                    <button type="submit" class="btn btn-success">
					                        <i class="fa fa-save me-2"></i>Save Changes
					                    </button>
					                </div>
					            </div>
					        </form>
					    </div>
					</div>

<!-- File List Modal -->
<div class="modal fade" id="filelistModal" tabindex="-1" aria-labelledby="filelistModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="filelistModalLabel">File List</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3 filelist-loading">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading file list...</p>
                </div>
                <div id="filelistContent" class="d-none">
                    <div class="mb-3 d-flex justify-content-between">
                        <span class="text-muted small">Total Files: <span id="total-files">0</span></span>
                        <span class="text-muted small">Total Size: <span id="total-size">0 B</span></span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width: 40px" class="text-center">#</th>
                                    <th>Filename</th>
                                    <th style="width: 60px" class="text-center">Type</th>
                                    <th style="width: 120px" class="text-center">Completion</th>
                                    <th style="width: 100px" class="text-center">Size</th>
                                </tr>
                            </thead>
                            <tbody id="filelist-tbody">
                                <!-- Files will be populated here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

					<script>
					{literal}
					document.addEventListener('DOMContentLoaded', function() {
					    // Form validation
					    const form = document.querySelector('.needs-validation');

					    form.addEventListener('submit', function(event) {
					        if (!form.checkValidity()) {
					            event.preventDefault();
					            event.stopPropagation();
					        }
					        form.classList.add('was-validated');
					    });

					    // Format datetime fields if needed
					    const datetimeFields = document.querySelectorAll('input[type="datetime-local"]');
					    datetimeFields.forEach(field => {
					        if (field.value && !field.value.includes('T')) {
					            // Convert MySQL datetime format to HTML datetime-local input format
					            const dateVal = field.value;
					            if (dateVal.match(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/)) {
					                field.value = dateVal.replace(' ', 'T').substring(0, 16);
					            }
					        }
					    });
					});

                    // File List Modal
                    document.addEventListener('DOMContentLoaded', function() {
                        const filelistModal = document.getElementById('filelistModal');

                        if (filelistModal) {
                            filelistModal.addEventListener('show.bs.modal', function(event) {
                                const button = event.relatedTarget;
                                const guid = button.getAttribute('data-guid');
                                const loading = filelistModal.querySelector('.filelist-loading');
                                const contentElement = document.getElementById('filelistContent');
                                const tbody = document.getElementById('filelist-tbody');
                                const totalFiles = document.getElementById('total-files');
                                const totalSize = document.getElementById('total-size');

                                // Reset and show loading state
                                loading.style.display = 'block';
                                contentElement.classList.add('d-none');
                                tbody.innerHTML = '';

                                // Fetch the file list via AJAX
                                fetch(`/filelist/${guid}?modal=true`, {
                                    headers: {
                                        'X-Requested-With': 'XMLHttpRequest'
                                    }
                                })
                                .then(response => response.text())
                                .then(html => {
                                    const parser = new DOMParser();
                                    const doc = parser.parseFromString(html, 'text/html');

                                    // Extract data from the HTML response
                                    const files = [];
                                    const tableRows = doc.querySelectorAll('table tbody tr');
                                    let totalSizeBytes = 0;

                                    tableRows.forEach(row => {
                                        const cells = row.querySelectorAll('td');
                                        if (cells.length >= 5) {
                                            const file = {
                                                num: cells[0].textContent.trim(),
                                                filename: cells[1].querySelector('.text-truncate') ?
                                                    cells[1].querySelector('.text-truncate').getAttribute('title') : cells[1].textContent.trim(),
                                                ext: cells[2].querySelector('.badge') ? cells[2].querySelector('.badge').textContent.trim() : '',
                                                completion: cells[3].querySelector('.progress-bar') ?
                                                    cells[3].querySelector('.progress-bar').getAttribute('aria-valuenow') : '100',
                                                size: cells[4].textContent.trim()
                                            };

                                            // Parse filesize for total calculation
                                            const sizeMatch = file.size.match(/(\d+(\.\d+)?)\s*(KB|MB|GB|TB)/i);
                                            if (sizeMatch) {
                                                const size = parseFloat(sizeMatch[1]);
                                                const unit = sizeMatch[3].toUpperCase();
                                                let bytes = size;
                                                if (unit === 'KB') bytes *= 1024;
                                                else if (unit === 'MB') bytes *= 1024 * 1024;
                                                else if (unit === 'GB') bytes *= 1024 * 1024 * 1024;
                                                else if (unit === 'TB') bytes *= 1024 * 1024 * 1024 * 1024;
                                                totalSizeBytes += bytes;
                                            }

                                            files.push(file);
                                        }
                                    });

                                    // Format total size
                                    function formatFileSize(bytes) {
                                        if (bytes < 1024) return bytes + ' B';
                                        else if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + ' KB';
                                        else if (bytes < 1024 * 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
                                        else if (bytes < 1024 * 1024 * 1024 * 1024) return (bytes / (1024 * 1024 * 1024)).toFixed(2) + ' GB';
                                        else return (bytes / (1024 * 1024 * 1024 * 1024)).toFixed(2) + ' TB';
                                    }

                                    // Display total information
                                    totalFiles.textContent = files.length;
                                    totalSize.textContent = formatFileSize(totalSizeBytes);

                                    // Populate the modal with files
                                    files.forEach(file => {
                                        const row = document.createElement('tr');
                                        row.innerHTML = `
                                            <td class="text-center">${file.num}</td>
                                            <td class="text-break">
                                                <span class="d-inline-block text-truncate" style="max-width: 400px;" title="${file.filename}">${file.filename}</span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-secondary text-uppercase">${file.ext}</span>
                                            </td>
                                            <td class="text-center">
                                                <div class="progress" style="height: 20px">
                                                    <div class="progress-bar ${file.completion < 100 ? 'bg-warning' : 'bg-success'}"
                                                         role="progressbar"
                                                         style="width: ${file.completion}%"
                                                         aria-valuenow="${file.completion}"
                                                         aria-valuemin="0"
                                                         aria-valuemax="100">
                                                        ${file.completion}%
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex align-items-center justify-content-center">
                                                    <i class="fa fa-hdd-o text-muted me-2"></i>
                                                    <span class="fw-medium">${file.size}</span>
                                                </div>
                                            </td>
                                        `;
                                        tbody.appendChild(row);
                                    });

                                    // Show content, hide loading
                                    loading.style.display = 'none';
                                    contentElement.classList.remove('d-none');
                                })
                                .catch(error => {
                                    console.error('Error fetching file list:', error);
                                    loading.style.display = 'none';
                                    contentElement.classList.remove('d-none');
                                    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error loading file list</td></tr>';
                                });
                            });
                        }
                    });
					{/literal}
					</script>

					<style>
					{literal}
					/* Form styling */
					.form-control:focus, .form-select:focus {
					    border-color: #80bdff;
					    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
					}

					/* Section styling */
					h5.border-bottom {
					    color: #495057;
					    font-size: 1.1rem;
					}

					/* Responsive adjustments */
					@media (max-width: 767.98px) {
					    .d-flex.justify-content-between {
					        flex-direction: column;
					        gap: 1rem;
					    }

					    .d-flex.justify-content-between .btn,
					    .d-flex.justify-content-between .btn-group {
					        width: 100%;
					    }

					    .btn-group {
					        display: flex;
					        flex-direction: column;
					    }

					    .btn-group .btn {
					        border-radius: 0.25rem !important;
					        margin-bottom: 0.5rem;
					    }
					}
					{/literal}
					</style>
