/**
 * Alpine.data('mediainfoModal') - Media information modal
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

Alpine.data('mediainfoModal', () => ({
    open: false,
    loading: false,

    _setContent(html) {
        if (this.$refs.content) {
            this.$refs.content.innerHTML = html;
        }
    },

    show(releaseId) {
        this.open = true;
        this.loading = true;
        this._setContent('');

        fetch('/api/release/' + releaseId + '/mediainfo')
            .then(r => { if (!r.ok) throw new Error('Failed'); return r.json(); })
            .then(data => {
                if (!data.video && !data.audio && !data.subs) {
                    this._setContent('<p class="text-center text-gray-500 dark:text-gray-400 py-8">No media information available</p>');
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
        let html = '<div class="space-y-6">';

        if (data.video) {
            html += '<div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900 dark:to-indigo-900 rounded-lg p-4">';
            html += '<h4 class="text-md font-semibold text-gray-800 dark:text-gray-200 mb-3 flex items-center"><i class="fas fa-video mr-2 text-blue-600 dark:text-blue-400"></i> Video Information</h4>';
            html += '<dl class="grid grid-cols-2 gap-3">';
            const v = data.video;
            if (v.containerformat) html += this._dl('Container', v.containerformat);
            if (v.videocodec) html += this._dl('Codec', v.videocodec);
            if (v.videowidth && v.videoheight) html += this._dl('Resolution', v.videowidth + 'x' + v.videoheight);
            if (v.videoaspect) html += this._dl('Aspect Ratio', v.videoaspect);
            if (v.videoframerate) html += this._dl('Frame Rate', v.videoframerate + ' fps');
            if (v.videoduration) {
                const m = Math.round(parseInt(v.videoduration) / 1000 / 60);
                if (m > 0) html += this._dl('Duration', m + ' minutes');
            }
            html += '</dl></div>';
        }

        if (data.audio && data.audio.length > 0) {
            html += '<div class="bg-gradient-to-r from-green-50 to-teal-50 dark:from-green-900 dark:to-teal-900 rounded-lg p-4">';
            html += '<h4 class="text-md font-semibold text-gray-800 dark:text-gray-200 mb-3 flex items-center"><i class="fas fa-volume-up mr-2 text-green-600 dark:text-green-400"></i> Audio Information</h4>';
            data.audio.forEach((a, i) => {
                if (i > 0) html += '<hr class="my-3 border-gray-200 dark:border-gray-700">';
                html += '<dl class="grid grid-cols-2 gap-3">';
                if (a.audioformat) html += this._dl('Format', a.audioformat);
                if (a.audiochannels) html += this._dl('Channels', a.audiochannels);
                if (a.audiobitrate) html += this._dl('Bit Rate', a.audiobitrate);
                if (a.audiolanguage) html += this._dl('Language', a.audiolanguage);
                if (a.audiosamplerate) html += this._dl('Sample Rate', a.audiosamplerate);
                html += '</dl>';
            });
            html += '</div>';
        }

        if (data.subs) {
            html += '<div class="bg-gradient-to-r from-purple-50 to-pink-50 dark:from-purple-900 dark:to-pink-900 rounded-lg p-4">';
            html += '<h4 class="text-md font-semibold text-gray-800 dark:text-gray-200 mb-3 flex items-center"><i class="fas fa-closed-captioning mr-2 text-purple-600 dark:text-purple-400"></i> Subtitles</h4>';
            html += '<p class="text-sm text-gray-900 dark:text-gray-100">' + escapeHtml(data.subs) + '</p></div>';
        }

        html += '</div>';
        return html;
    },

    _dl(label, value) {
        return '<div><dt class="text-xs font-medium text-gray-600 dark:text-gray-400">' + escapeHtml(label) + '</dt><dd class="text-sm text-gray-900 dark:text-gray-100">' + escapeHtml(String(value)) + '</dd></div>';
    },

    init() {
        const self = this;
        window.showMediainfo = function(id) { self.show(id); };
        window.closeMediainfoModal = function() { self.close(); };
        window.formatFileSize = formatFileSize;

        // Document-level click delegation for mediainfo triggers
        document.addEventListener('click', function(e) {
            const badge = e.target.closest('.mediainfo-badge');
            if (badge) { e.preventDefault(); self.show(badge.dataset.releaseId); return; }
            if (e.target.closest('[data-close-mediainfo-modal]')) { e.preventDefault(); self.close(); }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && self.open) self.close();
        });
    }
}));
