/**
 * Modal-related functions extracted from csp-safe.js
 */

import { escapeHtml } from './utils.js';

// Styled Confirmation Modal (replaces native confirm dialogs)
let confirmationCallback = null;

window.showConfirm = function(options) {
    // Options: { message, title, details, confirmText, cancelText, type, onConfirm }
    const modal = document.getElementById('confirmationModal');
    if (!modal) return Promise.reject('Modal not found');

    const defaults = {
        title: 'Confirm Action',
        message: 'Are you sure you want to proceed?',
        details: '',
        confirmText: 'Confirm',
        cancelText: 'Cancel',
        type: 'info', // 'info', 'warning', 'danger', 'success'
        onConfirm: null
    };

    const config = { ...defaults, ...options };

    // Set modal content - with null checks
    const titleText = document.getElementById('confirmationModalTitleText');
    const messageText = document.getElementById('confirmationModalMessage');
    const confirmText = document.getElementById('confirmationModalConfirmText');
    const cancelText = document.getElementById('confirmationModalCancelText');

    if (titleText) titleText.textContent = config.title;
    if (messageText) messageText.textContent = config.message;
    if (confirmText) confirmText.textContent = config.confirmText;
    if (cancelText) cancelText.textContent = config.cancelText;

    // Set icon based on type
    const icon = document.getElementById('confirmationModalIcon');
    const confirmBtn = document.getElementById('confirmationModalConfirmBtn');

    // Helper function to set classes (works with both regular elements and SVG)
    function setIconClasses(element, classes) {
        if (!element) return;
        if (element instanceof SVGElement) {
            element.setAttribute('class', classes.join(' '));
        } else {
            element.className = classes.join(' ');
        }
    }

    // Set base icon classes
    let iconClasses = ['fas', 'mr-2'];

    // Remove all existing classes from button and set base classes
    if (confirmBtn) {
        confirmBtn.className = '';
        confirmBtn.classList.add('px-4', 'py-2', 'text-white', 'rounded-lg', 'transition', 'font-medium');
    }

    if (config.type === 'danger') {
        iconClasses.push('fa-exclamation-triangle', 'text-red-600', 'dark:text-red-400');
        if (confirmBtn) {
            confirmBtn.classList.add('bg-red-600', 'dark:bg-red-700', 'hover:bg-red-700', 'dark:hover:bg-red-800');
        }
    } else if (config.type === 'warning') {
        iconClasses.push('fa-exclamation-circle', 'text-yellow-600', 'dark:text-yellow-400');
        if (confirmBtn) {
            confirmBtn.classList.add('bg-yellow-600', 'dark:bg-yellow-700', 'hover:bg-yellow-700', 'dark:hover:bg-yellow-800');
        }
    } else if (config.type === 'success') {
        iconClasses.push('fa-check-circle', 'text-green-600', 'dark:text-green-400');
        if (confirmBtn) {
            confirmBtn.classList.add('bg-green-600', 'dark:bg-green-700', 'hover:bg-green-700', 'dark:hover:bg-green-800');
        }
    } else { // info
        iconClasses.push('fa-info-circle', 'text-blue-600', 'dark:text-blue-400');
        if (confirmBtn) {
            confirmBtn.classList.add('bg-blue-600', 'dark:bg-blue-700', 'hover:bg-blue-700', 'dark:hover:bg-blue-800');
        }
    }

    // Apply icon classes
    setIconClasses(icon, iconClasses);

    // Handle details
    const detailsDiv = document.getElementById('confirmationModalDetails');
    const detailsText = document.getElementById('confirmationModalDetailsText');
    if (config.details && detailsText && detailsDiv) {
        detailsText.textContent = config.details;
        detailsDiv.classList.remove('hidden');
    } else if (detailsDiv) {
        detailsDiv.classList.add('hidden');
    }

    // Store callback for use when confirm is clicked
    const storedOnConfirm = config.onConfirm;

    // Show modal
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    // Return promise for async/await usage
    return new Promise((resolve, reject) => {
        confirmationCallback = function(confirmed) {
            if (confirmed && storedOnConfirm) {
                storedOnConfirm();
            }
            resolve(confirmed);
        };
    });
};

window.closeConfirmationModal = function() {
    const modal = document.getElementById('confirmationModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
    if (confirmationCallback) {
        confirmationCallback(false);
        confirmationCallback = null;
    }
};

window.confirmConfirmationModal = function() {
    const modal = document.getElementById('confirmationModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
    if (confirmationCallback) {
        confirmationCallback(true);
        confirmationCallback = null;
    }
};

// Enhanced confirm function (backward compatible but with styled modal)
window.confirmStyled = function(message, title = 'Confirm', type = 'info') {
    return new Promise((resolve) => {
        showConfirm({
            message: message,
            title: title,
            type: type,
            onConfirm: () => resolve(true)
        }).then(result => {
            if (!result) resolve(false);
        });
    });
};

// NFO Modal
export function initNfoModal() {
    window.openNfoModal = function(guid) {
        const modal = document.getElementById('nfoModal');
        const content = document.getElementById('nfoContent');

        if (!modal || !content) return;

        // Show modal
        modal.classList.remove('hidden');
        modal.style.display = 'block';

        // Reset content with loading message
        content.innerHTML = '<div class="flex items-center justify-center py-8"><i class="fas fa-spinner fa-spin text-2xl mr-2"></i><span>Loading NFO...</span></div>';

        // Fetch NFO content
        const baseUrl = document.querySelector('meta[name="app-url"]')?.content || '';
        fetch(baseUrl + '/nfo/' + guid + '?modal=1')
            .then(response => {
                if (!response.ok) {
                    throw new Error('NFO not found');
                }
                return response.text();
            })
            .then(html => {
                // Extract the NFO content from the response
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const nfoText = doc.querySelector('pre')?.textContent || doc.body.textContent;
                content.textContent = nfoText;
            })
            .catch(error => {
                content.innerHTML = '<div class="text-center py-8 text-red-400"><i class="fas fa-exclamation-triangle text-2xl mr-2"></i><span>Error loading NFO file</span></div>';
                console.error('Error loading NFO:', error);
            });
    };

    window.closeNfoModal = function() {
        const modal = document.getElementById('nfoModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.style.display = 'none';
        }
    };

    // Close modal on Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const modal = document.getElementById('nfoModal');
            if (modal && !modal.classList.contains('hidden')) {
                closeNfoModal();
            }
        }
    });

    // Add click handlers for NFO badges
    document.addEventListener('click', function(event) {
        if (event.target.closest('.nfo-badge')) {
            event.preventDefault();
            const badge = event.target.closest('.nfo-badge');
            const guid = badge.getAttribute('data-guid');
            if (guid) {
                openNfoModal(guid);
            }
        }
    });
}

// Confirm dialogs
export function initConfirmDialogs() {
    window.confirmDelete = function(id) {
        if (confirm('Are you sure you want to delete this item?')) {
            // If there's a form with this ID, submit it
            const form = document.getElementById('delete-form-' + id);
            if (form) {
                form.submit();
            }
            return true;
        }
        return false;
    };
}

// Image modal
export function initImageModal() {
    // Handle image modal if needed
    document.querySelectorAll('[data-open-image-modal]').forEach(function(element) {
        element.addEventListener('click', function(e) {
            e.preventDefault();
            const imageUrl = this.getAttribute('data-open-image-modal');
            openImageModal(imageUrl);
        });
    });

    window.openImageModal = function(imageUrl) {
        const modal = document.getElementById('imageModal');
        if (modal) {
            const img = modal.querySelector('img');
            if (img) {
                img.src = imageUrl;
            }
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
    };

    window.closeImageModal = function() {
        const modal = document.getElementById('imageModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    };
}

// Preview Modal for Browse Page
export function initPreviewModal() {
    window.closePreviewModal = function() {
        const modal = document.getElementById('previewModal');
        if (modal) {
            modal.style.display = 'none';
            modal.classList.add('hidden');
            const img = document.getElementById('previewImage');
            if (img) img.src = '';
        }
    };

    window.showPreviewImage = function(guid, type = 'preview') {
        const modal = document.getElementById('previewModal');
        const img = document.getElementById('previewImage');
        const error = document.getElementById('previewError');
        const title = document.getElementById('previewTitle');

        if (!modal || !img || !error || !title) return;

        modal.style.display = 'flex';
        modal.classList.remove('hidden');
        title.textContent = type === 'sample' ? 'Sample Image' : 'Preview Image';

        const imageUrl = '/covers/' + type + '/' + guid + '_thumb.jpg';
        img.src = imageUrl;
        error.classList.add('hidden');
        img.style.display = 'block';

        img.onerror = function() {
            error.textContent = (type === 'sample' ? 'Sample' : 'Preview') + ' image not available';
            error.classList.remove('hidden');
            img.style.display = 'none';
        };

        img.onload = function() {
            img.style.display = 'block';
        };
    };

    // Event listeners for preview badges
    document.querySelectorAll('.preview-badge').forEach(function(badge) {
        badge.addEventListener('click', function() {
            const guid = this.getAttribute('data-guid');
            if (guid) {
                window.showPreviewImage(guid, 'preview');
            }
        });
    });

    // Event listeners for sample badges
    document.querySelectorAll('.sample-badge').forEach(function(badge) {
        badge.addEventListener('click', function() {
            const guid = this.getAttribute('data-guid');
            if (guid) {
                window.showPreviewImage(guid, 'sample');
            }
        });
    });

    // NFO badge clicks are handled by initNfoModal() which uses event delegation
    // and the correct endpoint /nfo/{guid}?modal=1

    // Close preview modal on background click
    const previewModal = document.getElementById('previewModal');
    if (previewModal) {
        previewModal.addEventListener('click', function(e) {
            if (e.target === this) {
                window.closePreviewModal();
            }
        });

        // Event listener for close button (using onclick attribute)
        const closeButton = previewModal.querySelector('button[onclick*="closePreviewModal"]');
        if (closeButton) {
            closeButton.addEventListener('click', function(e) {
                e.stopPropagation();
                window.closePreviewModal();
            });
        }

        // Also handle any data-close-preview buttons
        const dataCloseButton = previewModal.querySelector('[data-close-preview]');
        if (dataCloseButton) {
            dataCloseButton.addEventListener('click', function(e) {
                e.stopPropagation();
                window.closePreviewModal();
            });
        }
    }

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            window.closePreviewModal();
        }
    });
}

// Mediainfo and Filelist Modals
export function initMediainfoAndFilelist() {
    window.closeMediainfoModal = function() {
        const modal = document.getElementById('mediainfoModal');
        if (modal) {
            modal.style.display = 'none';
        }
    };

    window.closeFilelistModal = function() {
        const modal = document.getElementById('filelistModal');
        if (modal) {
            modal.style.display = 'none';
        }
    };

    window.formatFileSize = function(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
    };

    window.showFilelist = function(guid) {
        let modal = document.getElementById('filelistModal');
        if (modal && modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }

        const content = document.getElementById('filelistContent');
        modal.style.display = 'flex';
        modal.style.position = 'fixed';
        modal.style.top = '0';
        modal.style.left = '0';
        modal.style.right = '0';
        modal.style.bottom = '0';
        modal.style.zIndex = '99999';
        modal.style.backgroundColor = 'rgba(0, 0, 0, 0.75)';

        content.innerHTML = `
            <div class="text-center py-8">
                <i class="fas fa-spinner fa-spin text-3xl text-green-600"></i>
                <p class="text-gray-600 dark:text-gray-400 mt-2">Loading file list...</p>
            </div>
        `;

        const apiUrl = '/api/release/' + guid + '/filelist';

        fetch(apiUrl)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Failed to load file list');
                }
                return response.json();
            })
            .then(data => {
                if (!data.files || data.files.length === 0) {
                    content.innerHTML = '<p class="text-center text-gray-500 dark:text-gray-400 py-8">No files available</p>';
                    return;
                }

                let html = '<div class="space-y-4">';
                html += `
                    <div class="bg-gradient-to-r from-green-50 to-teal-50 dark:from-green-900 dark:to-teal-900 rounded-lg p-4 mb-4">
                        <h4 class="text-md font-semibold text-gray-800 dark:text-gray-200 mb-2">${escapeHtml(data.release.searchname)}</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Total Files: ${data.total}</p>
                    </div>
                `;

                html += `
                    <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-100 dark:bg-gray-800">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">File Name</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">Size</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                `;

                data.files.forEach((file, index) => {
                    const rowClass = index % 2 === 0 ? 'bg-white dark:bg-gray-800' : 'bg-gray-50 dark:bg-gray-900';
                    const fileName = file.title || file.name || 'Unknown';
                    const fileSize = file.size ? formatFileSize(file.size) : 'N/A';

                    html += `
                        <tr class="${rowClass} hover:bg-gray-100 dark:hover:bg-gray-700">
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100 break-all">${escapeHtml(fileName)}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">${fileSize}</td>
                        </tr>
                    `;
                });

                html += `
                            </tbody>
                        </table>
                    </div>
                `;

                html += '</div>';
                content.innerHTML = html;
            })
            .catch(error => {
                content.innerHTML = `
                    <div class="text-center py-8">
                        <i class="fas fa-exclamation-circle text-3xl text-red-600"></i>
                        <p class="text-red-600 mt-2">${escapeHtml(error.message)}</p>
                    </div>
                `;
            });
    };

    window.showMediainfo = function(releaseId) {
        let modal = document.getElementById('mediainfoModal');

        if (modal && modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }

        const content = document.getElementById('mediainfoContent');

        modal.style.display = 'flex';
        modal.style.position = 'fixed';
        modal.style.top = '0';
        modal.style.left = '0';
        modal.style.right = '0';
        modal.style.bottom = '0';
        modal.style.zIndex = '99999';
        modal.style.backgroundColor = 'rgba(0, 0, 0, 0.75)';

        content.innerHTML = `
            <div class="text-center py-8">
                <i class="fas fa-spinner fa-spin text-3xl text-blue-600"></i>
                <p class="text-gray-600 dark:text-gray-400 mt-2">Loading media information...</p>
            </div>
        `;

        const apiUrl = '/api/release/' + releaseId + '/mediainfo';

        fetch(apiUrl)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Failed to load media information');
                }
                return response.json();
            })
            .then(data => {
                if (!data.video && !data.audio && !data.subs) {
                    content.innerHTML = '<p class="text-center text-gray-500 dark:text-gray-400 py-8">No media information available</p>';
                    return;
                }

                let html = '<div class="space-y-6">';

                // Video information
                if (data.video) {
                    html += `
                        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900 dark:to-indigo-900 rounded-lg p-4">
                            <h4 class="text-md font-semibold text-gray-800 dark:text-gray-200 mb-3 flex items-center">
                                <i class="fas fa-video mr-2 text-blue-600 dark:text-blue-400"></i> Video Information
                            </h4>
                            <dl class="grid grid-cols-2 gap-3">
                    `;

                    if (data.video.containerformat) {
                        html += `
                            <div>
                                <dt class="text-xs font-medium text-gray-600 dark:text-gray-400">Container</dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100">${escapeHtml(data.video.containerformat)}</dd>
                            </div>
                        `;
                    }
                    if (data.video.videocodec) {
                        html += `
                            <div>
                                <dt class="text-xs font-medium text-gray-600 dark:text-gray-400">Codec</dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100">${escapeHtml(data.video.videocodec)}</dd>
                            </div>
                        `;
                    }
                    if (data.video.videowidth && data.video.videoheight) {
                        html += `
                            <div>
                                <dt class="text-xs font-medium text-gray-600 dark:text-gray-400">Resolution</dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100">${data.video.videowidth}x${data.video.videoheight}</dd>
                            </div>
                        `;
                    }
                    if (data.video.videoaspect) {
                        html += `
                            <div>
                                <dt class="text-xs font-medium text-gray-600 dark:text-gray-400">Aspect Ratio</dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100">${escapeHtml(data.video.videoaspect)}</dd>
                            </div>
                        `;
                    }
                    if (data.video.videoframerate) {
                        html += `
                            <div>
                                <dt class="text-xs font-medium text-gray-600 dark:text-gray-400">Frame Rate</dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100">${escapeHtml(data.video.videoframerate)} fps</dd>
                            </div>
                        `;
                    }
                    if (data.video.videoduration) {
                        const durationMs = parseInt(data.video.videoduration);
                        if (!isNaN(durationMs) && durationMs > 0) {
                            const minutes = Math.round(durationMs / 1000 / 60);
                            html += `
                                <div>
                                    <dt class="text-xs font-medium text-gray-600 dark:text-gray-400">Duration</dt>
                                    <dd class="text-sm text-gray-900 dark:text-gray-100">${minutes} minutes</dd>
                                </div>
                            `;
                        }
                    }

                    html += '</dl></div>';
                }

                // Audio information
                if (data.audio && data.audio.length > 0) {
                    html += `
                        <div class="bg-gradient-to-r from-green-50 to-teal-50 dark:from-green-900 dark:to-teal-900 rounded-lg p-4">
                            <h4 class="text-md font-semibold text-gray-800 dark:text-gray-200 mb-3 flex items-center">
                                <i class="fas fa-volume-up mr-2 text-green-600 dark:text-green-400"></i> Audio Information
                            </h4>
                    `;

                    data.audio.forEach((audio, index) => {
                        if (index > 0) html += '<hr class="my-3 border-gray-200 dark:border-gray-700">';
                        html += '<dl class="grid grid-cols-2 gap-3">';

                        if (audio.audioformat) {
                            html += `
                                <div>
                                    <dt class="text-xs font-medium text-gray-600 dark:text-gray-400">Format</dt>
                                    <dd class="text-sm text-gray-900 dark:text-gray-100">${escapeHtml(audio.audioformat)}</dd>
                                </div>
                            `;
                        }
                        if (audio.audiochannels) {
                            html += `
                                <div>
                                    <dt class="text-xs font-medium text-gray-600 dark:text-gray-400">Channels</dt>
                                    <dd class="text-sm text-gray-900 dark:text-gray-100">${escapeHtml(audio.audiochannels)}</dd>
                                </div>
                            `;
                        }
                        if (audio.audiobitrate) {
                            html += `
                                <div>
                                    <dt class="text-xs font-medium text-gray-600 dark:text-gray-400">Bit Rate</dt>
                                    <dd class="text-sm text-gray-900 dark:text-gray-100">${escapeHtml(audio.audiobitrate)}</dd>
                                </div>
                            `;
                        }
                        if (audio.audiolanguage) {
                            html += `
                                <div>
                                    <dt class="text-xs font-medium text-gray-600 dark:text-gray-400">Language</dt>
                                    <dd class="text-sm text-gray-900 dark:text-gray-100">${escapeHtml(audio.audiolanguage)}</dd>
                                </div>
                            `;
                        }
                        if (audio.audiosamplerate) {
                            html += `
                                <div>
                                    <dt class="text-xs font-medium text-gray-600 dark:text-gray-400">Sample Rate</dt>
                                    <dd class="text-sm text-gray-900 dark:text-gray-100">${escapeHtml(audio.audiosamplerate)}</dd>
                                </div>
                            `;
                        }

                        html += '</dl>';
                    });

                    html += '</div>';
                }

                // Subtitle information
                if (data.subs) {
                    html += `
                        <div class="bg-gradient-to-r from-purple-50 to-pink-50 dark:from-purple-900 dark:to-pink-900 rounded-lg p-4">
                            <h4 class="text-md font-semibold text-gray-800 dark:text-gray-200 mb-3 flex items-center">
                                <i class="fas fa-closed-captioning mr-2 text-purple-600 dark:text-purple-400"></i> Subtitles
                            </h4>
                            <p class="text-sm text-gray-900 dark:text-gray-100">${escapeHtml(data.subs)}</p>
                        </div>
                    `;
                }

                html += '</div>';
                content.innerHTML = html;
            })
            .catch(error => {
                content.innerHTML = `
                    <div class="text-center py-8">
                        <i class="fas fa-exclamation-circle text-3xl text-red-600"></i>
                        <p class="text-red-600 mt-2">${escapeHtml(error.message)}</p>
                    </div>
                `;
            });
    };

    // Event handlers for badges and modals
    document.addEventListener('click', function(e) {
        // Preview badge
        const previewBadge = e.target.closest('.preview-badge');
        if (previewBadge) {
            e.preventDefault();
            const guid = previewBadge.dataset.guid;
            if (typeof showPreviewImage === 'function') {
                showPreviewImage(guid, 'preview');
            }
        }

        // Sample badge
        const sampleBadge = e.target.closest('.sample-badge');
        if (sampleBadge) {
            e.preventDefault();
            const guid = sampleBadge.dataset.guid;
            if (typeof showPreviewImage === 'function') {
                showPreviewImage(guid, 'sample');
            }
        }

        // Mediainfo badge
        const mediainfoBadge = e.target.closest('.mediainfo-badge');
        if (mediainfoBadge) {
            e.preventDefault();
            const releaseId = mediainfoBadge.dataset.releaseId;
            showMediainfo(releaseId);
        }

        // File list badge
        const filelistBadge = e.target.closest('.filelist-badge');
        if (filelistBadge) {
            e.preventDefault();
            const guid = filelistBadge.dataset.guid;
            showFilelist(guid);
        }

        // Handle modal close buttons with data attributes
        if (e.target.hasAttribute('data-close-preview-modal') || e.target.closest('[data-close-preview-modal]')) {
            e.preventDefault();
            if (typeof closePreviewModal === 'function') {
                closePreviewModal();
            }
        }

        if (e.target.hasAttribute('data-close-mediainfo-modal') || e.target.closest('[data-close-mediainfo-modal]')) {
            e.preventDefault();
            if (typeof closeMediainfoModal === 'function') {
                closeMediainfoModal();
            }
        }

        if (e.target.hasAttribute('data-close-filelist-modal') || e.target.closest('[data-close-filelist-modal]')) {
            e.preventDefault();
            if (typeof closeFilelistModal === 'function') {
                closeFilelistModal();
            }
        }
    });

    // Close modals on background click
    const previewModal = document.getElementById('previewModal');
    if (previewModal) {
        previewModal.addEventListener('click', function(e) {
            if (e.target === this && typeof closePreviewModal === 'function') {
                closePreviewModal();
            }
        });
    }

    const mediainfoModal = document.getElementById('mediainfoModal');
    if (mediainfoModal) {
        mediainfoModal.addEventListener('click', function(e) {
            // Close if clicking the backdrop OR the close button
            if (e.target === this || e.target.closest('[data-close-mediainfo-modal]')) {
                closeMediainfoModal();
            }
        });
    }

    const filelistModal = document.getElementById('filelistModal');
    if (filelistModal) {
        filelistModal.addEventListener('click', function(e) {
            // Close if clicking the backdrop OR the close button
            if (e.target === this || e.target.closest('[data-close-filelist-modal]')) {
                closeFilelistModal();
            }
        });
    }

    // Close modals on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (typeof closePreviewModal === 'function') closePreviewModal();
            closeMediainfoModal();
            closeFilelistModal();
        }
    });
}

// Details page image modal
export function initDetailsPageImageModal() {
    // Handle image modal triggers on release details page
    const imageModalTriggers = document.querySelectorAll('.image-modal-trigger');
    const imageModal = document.getElementById('imageModal');
    const imageModalImage = document.getElementById('imageModalImage');
    const imageModalTitle = document.getElementById('imageModalTitle');
    const closeButtons = document.querySelectorAll('[data-close-image-modal]');

    if (!imageModal || !imageModalImage || !imageModalTitle) {
        return; // Not on the details page
    }

    // Add click handlers to image triggers
    imageModalTriggers.forEach(function(trigger) {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            const imageUrl = this.getAttribute('data-image-url');
            const imageTitle = this.getAttribute('data-image-title') || 'Image Preview';

            // Use the image URL as-is (keeping _thumb suffix)
            imageModalImage.src = imageUrl;
            imageModalTitle.textContent = imageTitle;
            imageModal.classList.remove('hidden');
            imageModal.style.display = 'flex';
        });
    });

    // Add click handler to close button
    closeButtons.forEach(function(closeBtn) {
        closeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            imageModal.classList.add('hidden');
            imageModal.style.display = 'none';
        });
    });

    // Close modal when clicking on backdrop
    imageModal.addEventListener('click', function(e) {
        if (e.target === imageModal) {
            imageModal.classList.add('hidden');
            imageModal.style.display = 'none';
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !imageModal.classList.contains('hidden')) {
            imageModal.classList.add('hidden');
            imageModal.style.display = 'none';
        }
    });
}

// Modal styles initialization
export function initModalStyles() {
    // Preview modal
    const previewModal = document.getElementById('previewModal');
    if (previewModal) {
        previewModal.style.display = 'none';
        previewModal.style.zIndex = '9999';
    }

    // NFO modal
    const nfoModal = document.getElementById('nfoModal');
    if (nfoModal) {
        nfoModal.style.display = 'none';
    }

    // Image modal
    const imageModal = document.getElementById('imageModal');
    if (imageModal) {
        imageModal.style.display = 'none';
    }

    // MediaInfo modal
    const mediainfoModal = document.getElementById('mediainfoModal');
    if (mediainfoModal) {
        mediainfoModal.style.display = 'none';
        mediainfoModal.style.zIndex = '9999';
    }

    // Filelist modal
    const filelistModal = document.getElementById('filelistModal');
    if (filelistModal) {
        filelistModal.style.display = 'none';
        filelistModal.style.zIndex = '9999';
    }

    // MediaInfo content scroll
    const mediainfoContent = document.getElementById('mediainfoContent');
    if (mediainfoContent) {
        mediainfoContent.style.maxHeight = 'calc(90vh - 80px)';
    }

    // Filelist content scroll
    const filelistContent = document.getElementById('filelistContent');
    if (filelistContent) {
        filelistContent.style.maxHeight = 'calc(90vh - 80px)';
    }
}
