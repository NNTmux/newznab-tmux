/**
 * Grafana embeds for the admin Monitoring page (adminMonitoring) and the
 * admin dashboard panels (grafanaPanels).
 *
 * Grafana trusts a short-lived JWT passed as ?auth_token=…; it keeps no
 * session, and its frontend re-sends the token from the iframe URL on every
 * API call. The token is fetched from Laravel (never rendered into HTML),
 * shared by every embed on the page, and refreshed before it expires, at
 * which point the iframes are reloaded with the new token.
 */
import Alpine from '@alpinejs/csp';

const REFRESH_AT = 0.8; // fraction of the token lifetime
const MIN_REFRESH_MS = 30 * 1000;

const tokenState = {
    url: null,
    token: null,
    expiresAt: 0,
    ttl: 0,
    pending: null,
    timer: null,
    listeners: new Set(),
};

function tokenIsFresh() {
    return tokenState.token !== null && Date.now() < tokenState.expiresAt * 1000 - MIN_REFRESH_MS;
}

function scheduleTokenRefresh() {
    clearTimeout(tokenState.timer);
    const delay = Math.max(MIN_REFRESH_MS, tokenState.ttl * 1000 * REFRESH_AT);
    tokenState.timer = setTimeout(() => {
        fetchToken(true).catch(() => {});
    }, delay);
}

function fetchToken(force = false) {
    if (!force && tokenIsFresh()) {
        return Promise.resolve(tokenState.token);
    }
    if (tokenState.pending) {
        return tokenState.pending;
    }

    tokenState.pending = fetch(tokenState.url, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
        .then(response => {
            if (!response.ok) {
                throw new Error(response.status === 404
                    ? 'Monitoring is not configured on this server.'
                    : `Could not get a Grafana token (HTTP ${response.status}).`);
            }
            return response.json();
        })
        .then(data => {
            const changed = tokenState.token !== null;
            tokenState.token = data.token;
            tokenState.expiresAt = Number(data.expires_at);
            tokenState.ttl = Number(data.ttl);
            scheduleTokenRefresh();
            if (changed) {
                tokenState.listeners.forEach(listener => listener());
            }
            return tokenState.token;
        })
        .finally(() => {
            tokenState.pending = null;
        });

    return tokenState.pending;
}

/**
 * Background tabs throttle timers; catch up as soon as the page is visible again.
 */
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && tokenState.url && !tokenIsFresh()) {
        fetchToken(true).catch(() => {});
    }
});

function currentTheme() {
    return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
}

/**
 * Apply query overrides to a Grafana URL without turning the bare `kiosk`
 * flag into `kiosk=` (URLSearchParams always serializes a value).
 */
function grafanaUrl(base, overrides) {
    const url = new URL(base, window.location.origin);
    const kiosk = url.searchParams.has('kiosk');
    url.searchParams.delete('kiosk');

    Object.entries(overrides).forEach(([key, value]) => {
        if (value === null || value === '') {
            url.searchParams.delete(key);
        } else {
            url.searchParams.set(key, value);
        }
    });

    const path = `${url.pathname}?${url.searchParams.toString()}${kiosk ? '&kiosk' : ''}`;

    return url.origin === window.location.origin ? path : url.origin + path;
}

/**
 * Shared lifecycle for components that host Grafana iframes: token fetch,
 * token-refresh reloads and theme switching.
 */
function grafanaHost(component) {
    tokenState.url = component.$el.dataset.tokenUrl;

    const rerender = () => component.render();
    tokenState.listeners.add(rerender);

    let theme = currentTheme();
    const observer = new MutationObserver(() => {
        if (currentTheme() !== theme) {
            theme = currentTheme();
            rerender();
        }
    });
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

    component._teardown = () => {
        tokenState.listeners.delete(rerender);
        observer.disconnect();
    };

    fetchToken()
        .then(() => component.render())
        .catch(error => {
            component.error = error.message;
        });
}

Alpine.data('grafanaPanels', () => ({
    error: '',
    _teardown: null,

    init() {
        grafanaHost(this);
    },

    destroy() {
        this._teardown?.();
    },

    render() {
        if (!tokenState.token) return;
        this.error = '';
        this.$root.querySelectorAll('iframe[data-src-base]').forEach(frame => {
            frame.src = grafanaUrl(frame.dataset.srcBase, {
                theme: currentTheme(),
                auth_token: tokenState.token,
            });
        });
    },
}));

Alpine.data('adminMonitoring', () => ({
    active: '',
    range: 'now-6h',
    refresh: '1m',
    error: '',
    _teardown: null,

    init() {
        this.active = this.$el.dataset.defaultTab || '';
        this.range = this.$el.dataset.defaultRange || this.range;
        this.refresh = this.$el.dataset.defaultRefresh ?? this.refresh;
        grafanaHost(this);
    },

    destroy() {
        this._teardown?.();
    },

    isActive(key) {
        return this.active === key;
    },

    tabClass(key) {
        return this.active === key
            ? 'bg-blue-600 text-white shadow-sm'
            : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600';
    },

    selectTab(key) {
        this.active = key;
        this.render();
    },

    applyRange() {
        this.render();
    },

    openInGrafana() {
        const frame = this._frame(this.active);
        if (!frame || !tokenState.token) return;
        window.open(this._url(frame.dataset.openUrl), '_blank', 'noopener');
    },

    /**
     * Only the visible dashboard keeps a live iframe; hidden ones are blanked
     * so they stop polling Prometheus and never hold a stale token.
     */
    render() {
        if (!tokenState.token) return;
        this.error = '';
        this.$root.querySelectorAll('iframe[data-dashboard]').forEach(frame => {
            if (frame.dataset.dashboard === this.active) {
                frame.src = this._url(frame.dataset.srcBase);
            } else if (frame.getAttribute('src') && frame.getAttribute('src') !== 'about:blank') {
                frame.src = 'about:blank';
            }
        });
    },

    _frame(key) {
        return this.$root.querySelector(`iframe[data-dashboard="${key}"]`);
    },

    _url(base) {
        return grafanaUrl(base, {
            from: this.range,
            to: 'now',
            refresh: this.refresh,
            theme: currentTheme(),
            auth_token: tokenState.token,
        });
    },
}));
