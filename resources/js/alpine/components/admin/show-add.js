/**
 * Alpine.data('showAddForm') - Admin "Add TV Show" form with live preview.
 *
 * CSP-safe: all logic lives here; the Blade template only references
 * properties and zero-arg methods on this component.
 */
import Alpine from '@alpinejs/csp';

Alpine.data('showAddForm', () => ({
    // --- state ---
    source: 'tvdb',
    externalId: '',
    type: '0',
    loading: false,
    previewData: null,
    previewError: '',
    lookupUrl: '',
    csrfToken: '',

    placeholders: {
        tvdb: 'e.g. 81189',
        tvmaze: 'e.g. 169',
        tmdb: 'e.g. 1396',
        trakt: 'e.g. 1388 or breaking-bad',
        imdb: 'e.g. tt0903747 or 0903747',
    },

    init() {
        const root = this.$root;
        const config = root ? root.getAttribute('data-config') : null;
        if (config) {
            try {
                const parsed = JSON.parse(config);
                this.source = parsed.source || 'tvdb';
                this.externalId = parsed.externalId || '';
                this.type = parsed.type || '0';
                this.lookupUrl = parsed.lookupUrl || '';
                this.csrfToken = parsed.csrfToken || '';
            } catch (e) {
                // ignore - fall back to defaults
            }
        }
    },

    // --- computed-style getters consumed via x-text / x-bind ---
    get placeholder() {
        return this.placeholders[this.source] || 'External ID';
    },

    get buttonLabel() {
        return this.loading ? 'Looking…' : 'Preview';
    },

    get buttonClasses() {
        return this.loading
            ? 'flex-1 px-3 py-2 bg-gray-500 text-white rounded-lg text-sm whitespace-nowrap opacity-50 cursor-wait'
            : 'flex-1 px-3 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 text-sm whitespace-nowrap';
    },

    get hasPreview() {
        return !!this.previewData;
    },

    get hasError() {
        return !!this.previewError;
    },

    get posterUrl() {
        return this.previewData && this.previewData.poster ? this.previewData.poster : '';
    },

    get hasPoster() {
        return !!this.posterUrl;
    },

    get title() {
        return this.previewData && this.previewData.title ? this.previewData.title : '';
    },

    get summary() {
        return this.previewData && this.previewData.summary ? this.previewData.summary : '';
    },

    get started() {
        return this.previewData && this.previewData.started ? this.previewData.started : '';
    },

    get publisher() {
        return this.previewData && this.previewData.publisher ? this.previewData.publisher : '';
    },

    get hasPublisher() {
        return !!this.publisher;
    },

    /**
     * Returns external IDs as an array of {label, value} for x-for rendering.
     */
    get idEntries() {
        if (!this.previewData || !this.previewData.ids) {
            return [];
        }
        const out = [];
        const ids = this.previewData.ids;
        const order = ['tvdb', 'tvmaze', 'tmdb', 'trakt', 'tvrage', 'imdb'];
        order.forEach((key) => {
            const val = ids[key];
            if (val && val !== 0 && val !== '0') {
                out.push({ label: key.toUpperCase(), value: String(val) });
            }
        });

        return out;
    },

    // --- actions ---
    preview() {
        if (!this.externalId) {
            this.previewError = 'Enter an ID first.';
            this.previewData = null;

            return;
        }

        if (!this.lookupUrl) {
            this.previewError = 'Lookup endpoint is not configured.';

            return;
        }

        this.loading = true;
        this.previewError = '';
        this.previewData = null;

        const url = new URL(this.lookupUrl, window.location.origin);
        url.searchParams.set('source', this.source);
        url.searchParams.set('external_id', this.externalId);

        const headers = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };
        if (this.csrfToken) {
            headers['X-CSRF-TOKEN'] = this.csrfToken;
        }

        const self = this;
        fetch(url.toString(), {
            method: 'GET',
            credentials: 'same-origin',
            headers: headers,
        })
            .then(function (r) {
                return r.json().then(function (body) {
                    return { status: r.status, body: body };
                }).catch(function () {
                    return { status: r.status, body: { error: 'Invalid JSON (HTTP ' + r.status + ')' } };
                });
            })
            .then(function (resp) {
                if (resp.status >= 200 && resp.status < 300 && resp.body && resp.body.ok) {
                    self.previewData = resp.body.show;
                } else {
                    self.previewError = (resp.body && resp.body.error) ? resp.body.error : ('Lookup failed (HTTP ' + resp.status + ').');
                }
            })
            .catch(function (err) {
                self.previewError = 'Lookup failed: ' + (err && err.message ? err.message : 'network error');
            })
            .finally(function () {
                self.loading = false;
            });
    },
}));

