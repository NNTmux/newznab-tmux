document.addEventListener('DOMContentLoaded', function() {
    // Search type toggle functionality
    const searchTypeToggle = document.getElementById('searchTypeToggle');
    if (searchTypeToggle) {
        searchTypeToggle.addEventListener('click', function(e) {
            e.preventDefault();

            const currentType = this.getAttribute('data-current-type');
            const sbasic = document.getElementById('sbasic');
            const sadvanced = document.getElementById('sadvanced');

            if (currentType === 'advanced') {
                this.textContent = 'Basic Search';
                this.setAttribute('data-current-type', 'basic');
            } else {
                this.textContent = 'Advanced Search';
                this.setAttribute('data-current-type', 'advanced');
            }

            sbasic.style.display = sbasic.style.display === 'none' ? 'flex' : 'none';
            sadvanced.style.display = sadvanced.style.display === 'none' ? 'block' : 'none';
        });
    }
    // File List Modal
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
                                    cells[1].querySelector('.text-truncate').getAttribute('title') :
                                    cells[1].textContent.trim(),
                                ext: cells[2].querySelector('.badge') ?
                                    cells[2].querySelector('.badge').textContent.trim() : '',
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
