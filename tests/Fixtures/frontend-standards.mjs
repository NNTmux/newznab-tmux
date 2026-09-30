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

console.log('Frontend motion, privacy, password, theme and cart behavior passed.');
