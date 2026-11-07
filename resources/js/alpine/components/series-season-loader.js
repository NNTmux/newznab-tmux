import Alpine from '@alpinejs/csp';

Alpine.data('seriesSeasonLoader', () => ({
    loading: false,
    _abortController: null,

    init() {
        this.$el.addEventListener('click', event => {
            const link = event.target instanceof Element
                ? event.target.closest('[data-series-season-link], [data-series-pagination-link]')
                : null;
            if (!link || !this.$el.contains(link) || link.getAttribute('href') === '#') {
                return;
            }

            event.preventDefault();
            this.load(link);
        });

        window.addEventListener('popstate', () => {
            this.loadUrl(window.location.href, false);
        });
    },

    load(link) {
        this.loadUrl(link.href, true, link);
    },

    loadUrl(href, pushState = true, link = null) {
        if (this.loading && this._abortController) {
            this._abortController.abort();
        }

        const visibleUrl = new URL(href, window.location.origin);
        const requestUrl = new URL(visibleUrl.toString());
        requestUrl.hash = '';
        requestUrl.searchParams.set('_fragment', 'season');

        this.loading = true;
        this.$el.setAttribute('aria-busy', 'true');
        this._setPanelLoading(true);
        this._abortController = new AbortController();

        fetch(requestUrl.toString(), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal: this._abortController.signal,
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Season request failed: ${response.status}`);
                }

                return response.json();
            })
            .then(payload => {
                this._replaceHtml('[data-series-season-content]', payload.contentHtml);
                const pagination = this.$el.querySelector('[data-series-pagination-container]');
                if (pagination) {
                    pagination.innerHTML = payload.paginationHtml || '';
                }

                this._setActiveSeason(String(payload.selectedSeason));
                this._resetSelection();

                if (pushState) {
                    window.history.pushState({}, '', payload.url || visibleUrl.toString());
                }
            })
            .catch(error => {
                if (error.name !== 'AbortError') {
                    window.location.href = href;
                }
            })
            .finally(() => {
                this.loading = false;
                this.$el.removeAttribute('aria-busy');
                this._setPanelLoading(false);
                this._abortController = null;
            });
    },

    _replaceHtml(selector, html) {
        const current = this.$el.querySelector(selector);
        if (!current || typeof html !== 'string') {
            return;
        }

        current.outerHTML = html;

        const replacement = this.$el.querySelector(selector);
        if (replacement) {
            Alpine.initTree(replacement);
        }
    },

    _setActiveSeason(season) {
        const activeClasses = ['border-primary-500', 'text-link'];
        const inactiveClasses = ['border-transparent', 'text-gray-500', 'hover:text-gray-700', 'dark:text-gray-300', 'hover:border-gray-300'];
        const activeBadgeClasses = ['bg-primary-100', 'text-primary-800'];
        const inactiveBadgeClasses = ['bg-gray-100', 'dark:bg-gray-800', 'text-gray-600'];

        this.$el.querySelectorAll('[data-series-season-link]').forEach(tab => {
            const isActive = tab.dataset.season === season;
            tab.classList.remove(...activeClasses, ...inactiveClasses);
            tab.classList.add(...(isActive ? activeClasses : inactiveClasses));

            if (isActive) {
                tab.setAttribute('aria-current', 'page');
            } else {
                tab.removeAttribute('aria-current');
            }

            const badge = tab.querySelector('span');
            if (badge) {
                badge.classList.remove(...activeBadgeClasses, ...inactiveBadgeClasses);
                badge.classList.add(...(isActive ? activeBadgeClasses : inactiveBadgeClasses));
            }
        });
    },

    _resetSelection() {
        this.$el.querySelectorAll('.chkRelease').forEach(checkbox => {
            checkbox.checked = false;
        });

        const selectAll = this.$el.querySelector('#chkSelectAll');
        if (selectAll) {
            selectAll.checked = false;
            selectAll.dispatchEvent(new Event('change', { bubbles: true }));
        }
    },

    _setPanelLoading(isLoading) {
        const content = this.$el.querySelector('[data-series-season-content]');
        if (!content) {
            return;
        }

        content.classList.toggle('opacity-60', isLoading);
        content.classList.toggle('pointer-events-none', isLoading);
    },
}));
