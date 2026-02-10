/**
 * Alpine.data('filelistModal') - File list modal
 */
import Alpine from '@alpinejs/csp';

function escapeHtml(text) {
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
}

Alpine.data('filelistModal', () => ({
    open: false,
    loading: false,

    _setContent(html) {
        if (this.$refs.content) {
            this.$refs.content.innerHTML = html;
        }
    },

    show(guid) {
        this.open = true;
        this.loading = true;
        this._setContent('');

        fetch('/api/release/' + guid + '/filelist')
            .then(r => { if (!r.ok) throw new Error('Failed to load file list'); return r.json(); })
            .then(data => {
                if (!data.files || data.files.length === 0) {
                    this._setContent('<p class="text-center text-gray-500 dark:text-gray-400 py-8">No files available</p>');
                } else {
                    this._setContent(this._buildHtml(data));
                }
                this.loading = false;
            })
            .catch(err => {
                this._setContent('<div class="text-center py-8"><i class="fas fa-exclamation-circle text-3xl text-red-600"></i><p class="text-red-600 mt-2">' + escapeHtml(err.message) + '</p></div>');
                this.loading = false;
            });
    },

    close() { this.open = false; this._setContent(''); },

    _buildHtml(data) {
        let html = '<div class="space-y-4">';
        html += '<div class="bg-gradient-to-r from-green-50 to-teal-50 dark:from-green-900 dark:to-teal-900 rounded-lg p-4 mb-4">';
        html += '<h4 class="text-md font-semibold text-gray-800 dark:text-gray-200 mb-2">' + escapeHtml(data.release.searchname) + '</h4>';
        html += '<p class="text-sm text-gray-600 dark:text-gray-400">Total Files: ' + data.total + '</p></div>';
        html += '<div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">';
        html += '<table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700"><thead class="bg-gray-100 dark:bg-gray-800"><tr>';
        html += '<th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">File Name</th>';
        html += '<th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">Size</th>';
        html += '</tr></thead><tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">';

        data.files.forEach((file, i) => {
            const rowClass = i % 2 === 0 ? 'bg-white dark:bg-gray-800' : 'bg-gray-50 dark:bg-gray-900';
            const name = file.title || file.name || 'Unknown';
            const size = file.size ? formatFileSize(file.size) : 'N/A';
            html += '<tr class="' + rowClass + ' hover:bg-gray-100 dark:hover:bg-gray-700">';
            html += '<td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100 break-all">' + escapeHtml(name) + '</td>';
            html += '<td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">' + size + '</td></tr>';
        });

        html += '</tbody></table></div></div>';
        return html;
    },

    init() {
        const self = this;
        window.showFilelist = function(guid) { self.show(guid); };
        window.closeFilelistModal = function() { self.close(); };

        // Document-level click delegation for filelist triggers
        document.addEventListener('click', function(e) {
            const badge = e.target.closest('.filelist-badge');
            if (badge) { e.preventDefault(); self.show(badge.dataset.guid); return; }
            if (e.target.closest('[data-close-filelist-modal]')) { e.preventDefault(); self.close(); }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && self.open) self.close();
        });
    }
}));
