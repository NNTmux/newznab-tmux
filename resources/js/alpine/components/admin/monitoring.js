/**
 * Grafana embeds for the admin Monitoring page (adminMonitoring) and the
 * admin dashboard panels (grafanaPanels).
 *
 * The iframe URLs never carry a login token. In proxy mode (nginx) the web
 * server checks the Laravel session on every Grafana request and adds the
 * token itself. In cookie mode (Apache; the element has data-token-url)
 * Laravel sets the token as an HttpOnly cookie scoped to /grafana/: it is set
 * before the first embed loads and refreshed before it expires. Grafana keeps
 * no session, so its next request simply picks up the refreshed cookie.
 */
import Alpine from '@alpinejs/csp';

const REFRESH_AT = 0.8; // fraction of the token lifetime
const MIN_REFRESH_MS = 30 * 1000;

const cookieState = {
    url: null,
    expiresAt: 0,
    ttl: 0,
    pending: null,
    timer: null,
};

function cookieIsFresh() {
    return Date.now() < cookieState.expiresAt * 1000 - MIN_REFRESH_MS;
}

function scheduleCookieRefresh() {
    clearTimeout(cookieState.timer);
    const delay = Math.max(MIN_REFRESH_MS, cookieState.ttl * 1000 * REFRESH_AT);
    cookieState.timer = setTimeout(() => {
        refreshCookie(true).catch(() => {});
    }, delay);
}

function refreshCookie(force = false) {
    if (!cookieState.url || (!force && cookieIsFresh())) {
        return Promise.resolve();
    }
    if (cookieState.pending) {
        return cookieState.pending;
    }

    cookieState.pending = fetch(cookieState.url, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
        .then(response => {
            if (!response.ok) {
                throw new Error(response.status === 404
                    ? 'Monitoring is not configured on this server.'
                    : `Could not sign in to Grafana (HTTP ${response.status}).`);
            }
            return response.json();
        })
        .then(data => {
            cookieState.expiresAt = Number(data.expires_at);
            cookieState.ttl = Number(data.ttl);
            scheduleCookieRefresh();
        })
        .finally(() => {
            cookieState.pending = null;
        });

    return cookieState.pending;
}

/**
 * Background tabs throttle timers; catch up as soon as the page is visible again.
 */
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && cookieState.url && !cookieIsFresh()) {
        refreshCookie(true).catch(() => {});
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
 * Shared lifecycle for components that host Grafana iframes: the cookie-mode
 * login before the first render, and theme switching.
 */
function grafanaHost(component) {
    if (component.$el.dataset.tokenUrl) {
        cookieState.url = component.$el.dataset.tokenUrl;
    }

    let theme = currentTheme();
    const observer = new MutationObserver(() => {
        if (currentTheme() !== theme) {
            theme = currentTheme();
            component.render();
        }
    });
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

    component._teardown = () => observer.disconnect();

    refreshCookie()
        .then(() => {
            component.ready = true;
            component.render();
        })
        .catch(error => {
            component.error = error.message;
        });
}

Alpine.data('grafanaPanels', () => ({
    ready: false,
    error: '',
    _teardown: null,

    init() {
        grafanaHost(this);
    },

    destroy() {
        this._teardown?.();
    },

    render() {
        if (!this.ready) return;
        this.error = '';
        this.$root.querySelectorAll('iframe[data-src-base]').forEach(frame => {
            frame.src = grafanaUrl(frame.dataset.srcBase, { theme: currentTheme() });
        });
    },
}));

Alpine.data('adminMonitoring', () => ({
    ready: false,
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
        if (!frame || !this.ready) return;
        window.open(this._url(frame.dataset.openUrl), '_blank', 'noopener');
    },

    /**
     * Only the visible dashboard keeps a live iframe; hidden ones are blanked
     * so they stop polling Prometheus.
     */
    render() {
        if (!this.ready) return;
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
        });
    },
}));
