/**
 * Content management module
 * Extracted from csp-safe.js
 */
import { escapeHtml } from './utils.js';

// Admin Content List - Toggle Enable/Disable
export function initContentToggle() {
    document.addEventListener('click', function(e) {
        const toggleBtn = e.target.closest('.content-toggle-status');
        if (!toggleBtn) return;

        e.preventDefault();
        const contentId = toggleBtn.getAttribute('data-content-id');
        const currentStatus = parseInt(toggleBtn.getAttribute('data-current-status'));
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

        if (!contentId || !csrfToken) {
            if (typeof showToast === 'function') {
                showToast('Error: Missing required data', 'error');
            }
            return;
        }

        // Disable button during request
        toggleBtn.disabled = true;
        toggleBtn.style.opacity = '0.6';

        fetch('/admin/content-toggle-status', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ id: contentId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const newStatus = data.status;
                const row = toggleBtn.closest('tr');

                // Find the status cell (6th td in the row - 0-indexed = 5)
                const statusCell = row.cells[5];

                // Update the status badge
                if (newStatus === 1) {
                    statusCell.innerHTML = `
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-100">
                            <i class="fa fa-check mr-1"></i>Enabled
                        </span>
                    `;
                } else {
                    statusCell.innerHTML = `
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-100">
                            <i class="fa fa-times mr-1"></i>Disabled
                        </span>
                    `;
                }

                // Update the toggle button itself
                toggleBtn.setAttribute('data-current-status', newStatus);
                toggleBtn.title = newStatus === 1 ? 'Disable' : 'Enable';

                // Update button color classes
                if (newStatus === 1) {
                    toggleBtn.className = 'content-toggle-status text-green-600 dark:text-green-400 hover:text-green-900 dark:hover:text-green-300';
                    toggleBtn.innerHTML = '<i class="fa fa-toggle-on"></i>';
                } else {
                    toggleBtn.className = 'content-toggle-status text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-300';
                    toggleBtn.innerHTML = '<i class="fa fa-toggle-off"></i>';
                }

                // Re-enable button
                toggleBtn.disabled = false;
                toggleBtn.style.opacity = '1';

                if (typeof showToast === 'function') {
                    showToast(data.message, 'success');
                }
            } else {
                if (typeof showToast === 'function') {
                    showToast(data.message || 'Failed to toggle content status', 'error');
                }
                toggleBtn.disabled = false;
                toggleBtn.style.opacity = '1';
            }
        })
        .catch(error => {
            console.error('Error toggling content status:', error);
            if (typeof showToast === 'function') {
                showToast('An error occurred while toggling content status', 'error');
            }
            toggleBtn.disabled = false;
            toggleBtn.style.opacity = '1';
        });
    });
}

// Admin Content List - Delete Content
export function initContentDelete() {
    document.addEventListener('click', function(e) {
        const deleteBtn = e.target.closest('.content-delete');
        if (!deleteBtn) return;

        e.preventDefault();
        const contentId = deleteBtn.getAttribute('data-content-id');
        const contentTitle = deleteBtn.getAttribute('data-content-title');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

        if (!contentId || !csrfToken) {
            if (typeof showToast === 'function') {
                showToast('Error: Missing required data', 'error');
            }
            return;
        }

        // Show styled confirmation modal
        showConfirm({
            title: 'Delete Content',
            message: `Are you sure you want to delete "${contentTitle}"?`,
            details: 'This action cannot be undone.',
            type: 'danger',
            confirmText: 'Delete',
            cancelText: 'Cancel',
            onConfirm: function() {
                // Disable button during request
                deleteBtn.disabled = true;
                deleteBtn.style.opacity = '0.6';

                fetch('/admin/content-delete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ id: contentId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove the row from the table
                        const row = deleteBtn.closest('tr');
                        if (row) {
                            row.style.transition = 'opacity 0.3s';
                            row.style.opacity = '0';
                            setTimeout(() => {
                                row.remove();

                                // Check if table is empty now
                                const tbody = document.querySelector('tbody');
                                if (tbody && tbody.children.length === 0) {
                                    location.reload(); // Reload to show "no content found" message
                                }
                            }, 300);
                        }

                        if (typeof showToast === 'function') {
                            showToast(data.message, 'success');
                        }
                    } else {
                        if (typeof showToast === 'function') {
                            showToast(data.message || 'Failed to delete content', 'error');
                        }
                        deleteBtn.disabled = false;
                        deleteBtn.style.opacity = '1';
                    }
                })
                .catch(error => {
                    console.error('Error deleting content:', error);
                    if (typeof showToast === 'function') {
                        showToast('An error occurred while deleting content', 'error');
                    }
                    deleteBtn.disabled = false;
                    deleteBtn.style.opacity = '1';
                });
            }
        });
    });
}

// Movies page layout toggle functionality
export function initMoviesLayoutToggle() {
    const toggleButton = document.getElementById('layoutToggle');
    const toggleText = document.getElementById('layoutToggleText');
    const moviesGrid = document.getElementById('moviesGrid');

    if (!toggleButton || !toggleText || !moviesGrid) {
        return; // Not on movies page
    }

    // Get current layout from data attribute
    let currentLayout = parseInt(moviesGrid.dataset.userLayout) || 2;

    // Apply current layout
    applyLayout(currentLayout);

    // Handle toggle button click
    toggleButton.addEventListener('click', function() {
        // Toggle between 1 and 2 column layouts
        currentLayout = currentLayout === 2 ? 1 : 2;

        // Save preference to server
        const csrfToken = document.querySelector('meta[name="csrf-token"]');
        if (!csrfToken || !csrfToken.content) {
            console.error('CSRF token not found');
            applyLayout(currentLayout); // Apply layout anyway
            return;
        }

        fetch('/movies/update-layout', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken.content
            },
            body: JSON.stringify({ layout: currentLayout })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    applyLayout(currentLayout);
                }
            })
            .catch(error => {
                console.error('Error saving layout preference:', error);
                // Apply layout anyway even if save fails
                applyLayout(currentLayout);
            });
    });

    function applyLayout(layout) {
        const images = moviesGrid.querySelectorAll('img[alt], .bg-gray-200.dark\\:bg-gray-700');
        const releaseContainers = moviesGrid.querySelectorAll('.release-card-container');

        if (layout === 1) {
            // 1 Column Layout
            moviesGrid.classList.remove('lg:grid-cols-2');
            moviesGrid.classList.add('grid-cols-1');
            if (toggleText) toggleText.textContent = '1 Column';
            const icon = toggleButton.querySelector('i');
            if (icon) icon.className = 'fas fa-th-list mr-2';

            // Make images larger
            images.forEach(img => {
                img.classList.remove('w-32', 'h-48');
                img.classList.add('w-48', 'h-72');
            });

            // Adjust release card containers to horizontal layout
            releaseContainers.forEach(container => {
                container.classList.remove('space-y-2');
                container.classList.add('flex', 'flex-row', 'items-start', 'justify-between', 'gap-3');

                const infoWrapper = container.querySelector('.release-info-wrapper');
                if (infoWrapper) {
                    infoWrapper.classList.add('flex-1', 'min-w-0');
                }

                const actions = container.querySelector('.release-actions');
                if (actions) {
                    actions.classList.remove('flex-wrap');
                    actions.classList.add('flex-shrink-0', 'flex-row', 'items-center');
                }
            });
        } else {
            // 2 Column Layout
            moviesGrid.classList.remove('grid-cols-1');
            moviesGrid.classList.add('lg:grid-cols-2');
            if (toggleText) toggleText.textContent = '2 Columns';
            const icon = toggleButton.querySelector('i');
            if (icon) icon.className = 'fas fa-th-large mr-2';

            // Make images smaller
            images.forEach(img => {
                img.classList.remove('w-48', 'h-72');
                img.classList.add('w-32', 'h-48');
            });

            // Adjust release card containers to vertical layout
            releaseContainers.forEach(container => {
                container.classList.add('space-y-2');
                container.classList.remove('flex', 'flex-row', 'items-start', 'justify-between', 'gap-3');

                const infoWrapper = container.querySelector('.release-info-wrapper');
                if (infoWrapper) {
                    infoWrapper.classList.remove('flex-1', 'min-w-0');
                }

                const actions = container.querySelector('.release-actions');
                if (actions) {
                    actions.classList.add('flex-wrap', 'items-center');
                    actions.classList.remove('flex-shrink-0', 'flex-row');
                }
            });
        }
    }
}

export function initQualityFilter() {
    const resolutionButtons = document.querySelectorAll('.resolution-filter-btn');
    const sourceButtons = document.querySelectorAll('.source-filter-btn');
    const releaseItems = document.querySelectorAll('.release-item');
    const releaseCount = document.getElementById('release-count');

    if (!releaseItems.length || !releaseCount) {
        return; // Exit if elements don't exist on the page
    }

    const totalReleases = releaseItems.length;
    let activeResolution = 'all';
    let activeSource = 'all';

    // Function to apply filters
    function applyFilters() {
        let visibleCount = 0;

        releaseItems.forEach(item => {
            const releaseName = item.getAttribute('data-release-name');
            if (!releaseName) return;

            let matchesResolution = true;
            let matchesSource = true;

            // Check resolution filter
            if (activeResolution !== 'all') {
                matchesResolution = releaseName.includes(activeResolution.toLowerCase());
            }

            // Check source filter
            if (activeSource !== 'all') {
                const sourceLower = activeSource.toLowerCase();
                // Handle different naming variations
                if (sourceLower === 'bluray') {
                    matchesSource = releaseName.includes('bluray') ||
                                   releaseName.includes('blu-ray') ||
                                   releaseName.includes('bdrip') ||
                                   releaseName.includes('brrip');
                } else if (sourceLower === 'web-dl') {
                    matchesSource = releaseName.includes('web-dl') ||
                                   releaseName.includes('webdl') ||
                                   releaseName.includes('web.dl');
                } else if (sourceLower === 'webrip') {
                    matchesSource = releaseName.includes('webrip') ||
                                   releaseName.includes('web-rip') ||
                                   releaseName.includes('web.rip');
                } else {
                    matchesSource = releaseName.includes(sourceLower);
                }
            }

            // Show/hide based on both filters
            if (matchesResolution && matchesSource) {
                item.style.display = '';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });

        // Update count display
        if (activeResolution === 'all' && activeSource === 'all') {
            releaseCount.textContent = `(${totalReleases} total)`;
        } else {
            releaseCount.textContent = `(${visibleCount} of ${totalReleases})`;
        }
    }

    // Resolution filter buttons
    resolutionButtons.forEach(button => {
        button.addEventListener('click', function() {
            const filter = this.getAttribute('data-filter');
            activeResolution = filter;

            // Update active button styling
            resolutionButtons.forEach(btn => {
                btn.classList.remove('active', 'bg-blue-600', 'text-white');
                btn.classList.add('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');
            });
            this.classList.add('active', 'bg-blue-600', 'text-white');
            this.classList.remove('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');

            applyFilters();
        });
    });

    // Source filter buttons
    sourceButtons.forEach(button => {
        button.addEventListener('click', function() {
            const filter = this.getAttribute('data-filter');
            activeSource = filter;

            // Update active button styling
            sourceButtons.forEach(btn => {
                btn.classList.remove('active', 'bg-purple-600', 'text-white');
                btn.classList.add('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');
            });
            this.classList.add('active', 'bg-purple-600', 'text-white');
            this.classList.remove('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');

            applyFilters();
        });
    });
}

export function initMyMovies() {
    // Confirm before removing movies
    document.querySelectorAll('.confirm_action').forEach(element => {
        element.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to remove this movie from your watchlist?')) {
                e.preventDefault();
            }
        });
    });

    // Image fallback for movie covers
    document.querySelectorAll('img[data-fallback-src]').forEach(img => {
        img.addEventListener('error', function() {
            const fallback = this.getAttribute('data-fallback-src');
            if (fallback && this.src !== fallback) {
                this.src = fallback;
            }
        });
    });

    // Initialize Bootstrap tooltips if they exist
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
    }
}
