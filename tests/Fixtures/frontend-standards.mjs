import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

function loadComponent(path, context) {
    const source = readFileSync(new URL(`../../resources/js/alpine/${path}`, import.meta.url), 'utf8');
    runInNewContext(source.replace("import Alpine from '@alpinejs/csp';", ''), context);
}

for (const useWindow of [false, true]) {
    let reducedMotion = false;
    let listener;
    let removedListener;
    let scrollOptions;
    let factory;
    const target = {
        scrollTop: 400,
        addEventListener: (_event, callback) => { listener = callback; },
        removeEventListener: (_event, callback) => { removedListener = callback; },
        scrollTo: (options) => { scrollOptions = options; },
    };
    const window = {
        ...target,
        scrollY: 400,
        matchMedia: () => ({ matches: reducedMotion }),
    };
    loadComponent('components/back-to-top.js', {
        Alpine: { data: (_name, callback) => { factory = callback; } },
        window,
        document: { querySelector: () => useWindow ? null : target, documentElement: { scrollTop: 0 } },
    });
    const component = factory();
    component.init();
    assert.equal(component.visible, true);
    component.scrollToTop();
    assert.equal(scrollOptions.behavior, 'smooth');
    assert.equal(scrollOptions.top, 0);
    reducedMotion = true;
    component.scrollToTop();
    assert.equal(scrollOptions.behavior, 'instant', 'Respect a motion preference changed after initialization');
    target.scrollTop = 0;
    window.scrollY = 0;
    listener();
    assert.equal(component.visible, false);
    component.destroy();
    assert.equal(removedListener, listener, 'Remove the registered scroll listener on teardown');
}

let theme;
const toggleAttributes = new Map();
const buttons = ['light', 'dark', 'system'].map(value => ({
    dataset: { theme: value },
    attributes: new Map(),
    setAttribute(name, value) { this.attributes.set(name, value); },
    classList: { add() {}, remove() {}, contains() { return false; } },
}));
loadComponent('stores/theme.js', {
    Alpine: { store: (_name, value) => { theme = value; } },
    window: { matchMedia: () => ({ matches: false }) },
    localStorage: {
        getItem() { throw new Error('Storage access denied'); },
        setItem() { throw new Error('Storage access denied'); },
    },
    document: {
        querySelector: () => null,
        getElementById: id => id === 'theme-toggle'
            ? { setAttribute: (name, value) => toggleAttributes.set(name, value) } : null,
        querySelectorAll: selector => selector.includes('theme-btn') ? buttons : [],
    },
});
for (const preference of ['light', 'dark', 'system']) {
    theme.current = preference;
    theme._updateUI();
    assert.equal(toggleAttributes.get('aria-label'), `Change theme. Current theme: ${theme.label()}`);
    for (const button of buttons) {
        assert.equal(button.attributes.get('aria-pressed'), String(button.dataset.theme === preference));
    }
}

theme.apply = () => {};
theme.applyScheme = () => {};
theme._listenOS = () => {};
theme.init();
assert.equal(theme.current, 'light');
assert.equal(theme.colorScheme, 'blue');
theme.set('dark');
assert.equal(theme.current, 'dark', 'Theme selection still works when storage is blocked');

let memberTheme;
const storedTheme = new Map();
const savedRequests = [];
const memberMetas = {
    'meta[name="theme-preference"]': { content: 'dark' },
    'meta[name="color-scheme-preference"]': { content: 'violet' },
    'meta[name="user-authenticated"]': { content: 'true' },
    'meta[name="update-theme-url"]': { content: '/profile/update-theme' },
    'meta[name="csrf-token"]': { content: 'token' },
};
loadComponent('stores/theme.js', {
    Alpine: { store: (_name, value) => { memberTheme = value; } },
    window: { matchMedia: () => ({ matches: false }) },
    localStorage: {
        getItem: key => storedTheme.get(key) ?? null,
        setItem: (key, value) => storedTheme.set(key, value),
    },
    fetch: (url, options) => { savedRequests.push({ url, body: JSON.parse(options.body) }); return Promise.resolve(); },
    document: {
        querySelector: selector => memberMetas[selector] ?? null,
        getElementById: () => null,
        querySelectorAll: () => [],
    },
});
memberTheme.apply = () => {};
memberTheme.applyScheme = () => {};
memberTheme._listenOS = () => {};
memberTheme.init();
assert.equal(memberTheme.colorScheme, 'violet', 'Signed-in users keep their saved scheme');
assert.equal(storedTheme.get('theme'), 'dark', 'Saved theme is mirrored for pages without a session');
assert.equal(storedTheme.get('color_scheme'), 'violet');
memberTheme.setScheme('teal');
assert.equal(storedTheme.get('color_scheme'), 'teal');
assert.deepEqual(savedRequests.at(-1), { url: '/profile/update-theme', body: { theme_preference: 'dark', color_scheme: 'teal' } });

let toastStore;
const toastTimers = [];
loadComponent('stores/toast.js', {
    Alpine: { store: (_name, value) => { toastStore = value; } },
    window: {},
    document: { getElementById: () => null },
    setTimeout: (callback, delay) => { toastTimers.push({ callback, delay }); return toastTimers.length; },
    clearTimeout: () => {},
    Date: { now: () => 1000 },
});
toastStore.show('Saved', 'success');
toastStore.show('Could not save', 'error');
toastStore.show('Check settings', 'warning');
assert.equal(toastTimers.length, 1, 'Only the success toast auto-dismisses');
assert.equal(toastTimers[0].delay, 5000);
assert.equal(toastStore.items[1].remaining, null, 'Errors stay until the user dismisses them');
assert.equal(toastStore.announcement.trim(), 'Check settings', 'The live region mirrors the latest non-error toast');
toastStore.show('Check settings', 'warning');
assert.notEqual(toastStore.announcement, 'Check settings', 'A repeated message changes the live region text so it is announced again');
toastStore.pause(1);
assert.equal(toastStore._timers[1], undefined, 'Hovering or focusing a toast pauses its timer');
toastStore.resume(1);
assert.equal(toastTimers.length, 2, 'Leaving the toast restarts the timer');

{
    const htmlAttributes = new Map();
    const stored = new Map();
    const events = [];
    const listeners = {};
    const context = {
        document: { documentElement: {
            getAttribute: name => htmlAttributes.get(name) ?? null,
            setAttribute: (name, value) => htmlAttributes.set(name, value),
            removeAttribute: name => htmlAttributes.delete(name),
        } },
        localStorage: { setItem: (key, value) => stored.set(key, value), getItem: key => stored.get(key) ?? null },
        window: {
            dispatchEvent: event => { events.push(event); (listeners[event.type] || []).forEach(cb => cb(event)); },
            addEventListener: (type, cb) => { (listeners[type] ||= []).push(cb); },
        },
        CustomEvent: class { constructor(type, init) { this.type = type; this.detail = init.detail; } },
    };
    let railFactory;
    const railSource = readFileSync(new URL('../../resources/js/alpine/components/admin/sidebar-rail.js', import.meta.url), 'utf8')
        .replace("import Alpine from '@alpinejs/csp';", '')
        .replace(/export /g, '');
    runInNewContext(railSource + '\nthis.setAdminSidebarCollapsed = setAdminSidebarCollapsed;', Object.assign(context, {
        Alpine: { data: (_name, callback) => { railFactory = callback; } },
    }));
    const rail = railFactory();
    rail.init();
    assert.equal(rail.collapsed, false);
    rail.toggle();
    assert.equal(htmlAttributes.get('data-admin-sidebar'), 'collapsed', 'Collapsing marks <html> for the rail CSS');
    assert.equal(stored.get('admin_sidebar'), 'collapsed', 'The rail state is remembered');
    assert.equal(rail.collapsed, true, 'Every rail toggle follows the shared change event');
    context.localStorage.setItem = () => { throw new Error('Storage access denied'); };
    context.setAdminSidebarCollapsed(false);
    assert.equal(htmlAttributes.has('data-admin-sidebar'), false, 'Expanding still works when storage is blocked');
    assert.equal(rail.collapsed, false);
}

{
    const tokens = { '--ui-fg-muted': '#64748b', '--ui-border': '#e2e8f0', '--font-sans': 'Figtree Variable', '--color-success-500': '' };
    let observerCallback;
    const context = {
        getComputedStyle: () => ({ getPropertyValue: name => tokens[name] ?? '' }),
        document: {
            documentElement: {},
            createElement: () => ({ getContext: () => { throw new Error('No canvas in tests'); } }),
        },
        MutationObserver: class { constructor(callback) { observerCallback = callback; } observe() {} disconnect() {} },
    };
    const source = readFileSync(new URL('../../resources/js/alpine/lib/chart-theme.js', import.meta.url), 'utf8').replace(/export /g, '');
    runInNewContext(source + '\nthis.api = { toRgba, seriesColors, applyChartDefaults, observeTheme };', context);
    const { toRgba, seriesColors, applyChartDefaults, observeTheme } = context.api;

    assert.equal(toRgba('#3b82f6', 0.5), 'rgba(59, 130, 246, 0.5)');
    assert.equal(toRgba('oklch(70% 0.1 200)'), null, 'Unparseable colors report null so callers can fall back');
    const fallbackColors = seriesColors('success', 0.2);
    assert.equal(fallbackColors.borderColor, 'rgba(16, 185, 129, 1)', 'A missing token falls back to the built-in hex value');
    assert.equal(fallbackColors.backgroundColor, 'rgba(16, 185, 129, 0.2)');

    const Chart = { defaults: { font: {} } };
    applyChartDefaults(Chart);
    assert.equal(Chart.defaults.color, 'rgba(100, 116, 139, 1)');
    assert.equal(Chart.defaults.font.family, 'Figtree Variable');

    let updated = 0;
    const chart = { $themeTone: { name: 'primary', fillAlpha: 0.2 }, data: { datasets: [{}] }, update: () => { updated++; } };
    observeTheme(Chart, () => [chart]);
    tokens['--color-primary-500'] = '#10b981';
    observerCallback();
    assert.equal(updated, 1, 'Theme changes re-render registered charts');
    assert.equal(chart.data.datasets[0].borderColor, 'rgba(16, 185, 129, 1)', 'Charts pick up the new scheme color');
}

{
    let monitoringFactory;
    const monitoringSource = readFileSync(new URL('../../resources/js/alpine/components/admin/monitoring.js', import.meta.url), 'utf8')
        .replace(/^import .*$/gm, '');
    runInNewContext(monitoringSource, {
        Alpine: { data: (_name, callback) => { monitoringFactory = callback; } },
        window: { addEventListener() {}, removeEventListener() {} },
        document: { addEventListener() {}, removeEventListener() {} },
    });
    const monitoring = monitoringFactory();
    monitoring.render = () => {};
    const focused = [];
    const tabs = ['overview', 'releases', 'queues'].map(key => ({ dataset: { tabKey: key }, focus: () => focused.push(key) }));
    let prevented = false;
    monitoring.onTabKeydown({ key: 'ArrowLeft', target: tabs[0], currentTarget: { querySelectorAll: () => tabs }, preventDefault: () => { prevented = true; } });
    assert.equal(monitoring.active, 'queues', 'ArrowLeft wraps to the last tab');
    assert.equal(focused.join(), 'queues');
    assert.equal(prevented, true);
    monitoring.onTabKeydown({ key: 'Home', target: tabs[2], currentTarget: { querySelectorAll: () => tabs }, preventDefault() {} });
    assert.equal(monitoring.active, 'overview');
}

let passwordFactory;
loadComponent('components/password-toggle.js', {
    Alpine: { data: (_name, callback) => { passwordFactory = callback; } },
    document: { querySelectorAll: () => [] },
    window: {},
});
const password = passwordFactory();
password.$refs = { field: { type: 'password', value: 'unchanged' } };
password.toggle();
assert.equal(password.$refs.field.type, 'text');
assert.equal(password.label(), 'Hide password');
password.toggle();
assert.equal(password.$refs.field.type, 'password');
assert.equal(password.label(), 'Show password');
assert.equal(password.$refs.field.value, 'unchanged');

for (const delegated of [false, true]) {
    for (const succeeds of [false, true]) {
        let cartFactory;
        let clickHandler;
        let confirmation;
        let request;
        let reloads = 0;
        const toasts = [];
        loadComponent('components/cart-page.js', {
            Alpine: { data: (name, callback) => { if (name === 'cartPage') cartFactory = callback; } },
            document: {
                querySelector: () => ({ content: 'session-csrf-token' }),
                querySelectorAll: () => [],
                getElementById: () => null,
                addEventListener: (_event, callback) => { clickHandler = callback; },
            },
            window: { location: { reload: () => { reloads++; } } },
            showConfirm: options => { confirmation = options; },
            showToast: (...args) => { toasts.push(args); },
            fetch: (url, options) => { request = { url, options }; return Promise.resolve({ ok: succeeds }); },
        });
        if (delegated) {
            clickHandler({
                preventDefault() {},
                stopPropagation() {},
                target: { closest: selector => selector === '.cart-delete-link' ? {
                    getAttribute: name => name === 'data-delete-url' ? '/cart/delete/test-guid' : 'Test release',
                } : null },
            });
        } else {
            cartFactory().deleteItem('/cart/delete/test-guid', 'Test release');
        }
        assert.equal(request, undefined, 'Wait for confirmation before deleting');
        confirmation.onConfirm();
        await new Promise(resolve => setImmediate(resolve));
        assert.equal(request.url, '/cart/delete/test-guid');
        assert.equal(request.options.method, 'POST');
        assert.equal(request.options.headers['X-CSRF-TOKEN'], 'session-csrf-token');
        assert.equal(reloads, succeeds ? 1 : 0, 'Reload only after a successful delete');
        if (!succeeds) assert.equal(toasts.at(-1)[1], 'error');
    }
}

for (const operation of ['create', 'delete']) {
    for (const status of [423, 403, 422]) {
        let factory;
        let destination;
        let registrations = 0;
        const failure = { response: { status, data: { message: 'Authentication required.' } } };
        if (status === 403) failure.response.data.redirect = '/2fa/verify';
        loadComponent('components/passkey-manage.js', {
            Alpine: { data: (_name, callback) => { factory = callback; } },
            window: {
                browserSupportsWebAuthn: () => true,
                location: { assign: url => { destination = url; } },
                axios: {
                    post: async () => { throw failure; },
                    delete: async () => { throw failure; },
                },
                startRegistration: async () => { registrations++; },
                showConfirm: async () => true,
                navigator: {},
            },
        });
        const component = factory();
        component.$root = { dataset: { confirmPasswordUrl: '/confirm-password' } };
        component.init();
        component.passkeys = [{ id: 1, name: 'Existing key' }];
        if (operation === 'create') await component.createSecurityKeyPasskey();
        else await component.deletePasskey(1);
        assert.equal(destination, status === 423 ? '/confirm-password' : status === 403 ? '/2fa/verify' : undefined);
        assert.equal(registrations, 0, 'Do not start WebAuthn until server authorization succeeds');
        assert.equal(component.passkeys.length, 1, 'Keep existing passkeys when authorization fails');
        assert.equal(component.busy, false);
    }
}

console.log('Frontend motion, privacy, password, theme, cart and passkey behavior passed.');
