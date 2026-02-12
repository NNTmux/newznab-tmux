/**
 * Alpine.js CSP-safe lazy component loader.
 *
 * Scans the DOM for x-data attributes that match known lazy components.
 * Only imports the JS modules for components actually present on the page.
 * After all needed modules are loaded, calls Alpine.start().
 *
 * Dynamic import() is CSP-compliant (no eval / new Function).
 */
import Alpine from '@alpinejs/csp';

/**
 * Map of Alpine component name → dynamic import function.
 * Each module registers itself via Alpine.data() as a side effect.
 */
const lazyComponentMap = {
    // --- Modal components (only on release pages) ---
    'nfoModal':        () => import('./components/nfo-modal.js'),
    'filelistModal':   () => import('./components/filelist-modal.js'),
    'previewModal':    () => import('./components/preview-modal.js'),
    'mediainfoModal':  () => import('./components/mediainfo-modal.js'),
    'imageModal':      () => import('./components/image-modal.js'),

    // --- Page-specific components ---
    'moviesPage':      () => import('./components/movies-page.js'),
    'moviesLayout':    () => import('./components/movies-layout.js'),
    'qualityFilter':   () => import('./components/quality-filter.js'),
    'contentToggle':   () => import('./components/content-toggle.js'),
    'contentDelete':   () => import('./components/content-toggle.js'),  // same file
    'releaseReport':   () => import('./components/release-report.js'),
    'adminReleaseReports': () => import('./components/release-report.js'),  // same file
    'profileEdit':     () => import('./components/profile-edit.js'),
    'profilePage':     () => import('./components/profile-edit.js'),    // same file
    'copyToClipboard': () => import('./components/profile-edit.js'),    // same file
    'cartPage':        () => import('./components/cart-page.js'),
    'authPage':        () => import('./components/auth-page.js'),
    'otpInput':        () => import('./components/auth-page.js'),       // same file

    // --- Admin components (includes Chart.js — heavy) ---
    'adminDashboard':  () => import('./components/admin/dashboard.js'),
    'recentActivity':  () => import('./components/admin/dashboard.js'), // same file
    'adminGroups':     () => import('./components/admin/groups.js'),
    'adminFeatures':   () => import('./components/admin/features.js'),
};

/**
 * Extract Alpine component names from all x-data attributes in the DOM.
 * Handles: x-data="componentName", x-data="componentName()", x-data="{ ... }" (skipped).
 */
function getUsedComponentNames() {
    const names = new Set();
    document.querySelectorAll('[x-data]').forEach(el => {
        const raw = (el.getAttribute('x-data') || '').trim();
        // Skip inline objects like { show: true }
        if (!raw || raw.startsWith('{')) return;
        // Extract identifier before ( or whitespace
        const match = raw.match(/^([a-zA-Z_$][a-zA-Z0-9_$]*)/);
        if (match) names.add(match[1]);
    });
    return names;
}

/**
 * Load only the lazy components found on the current page, then start Alpine.
 */
export function loadAndStart() {
    const usedNames = getUsedComponentNames();
    const imports = new Set(); // deduplicate (multiple names → same file)

    for (const name of usedNames) {
        if (lazyComponentMap[name]) {
            imports.add(lazyComponentMap[name]);
        }
    }

    if (imports.size === 0) {
        Alpine.start();
        return;
    }

    // Load all needed modules in parallel, then start Alpine
    Promise.all([...imports].map(fn => fn()))
        .then(() => Alpine.start())
        .catch(err => {
            console.error('[lazy-loader] Failed to load component:', err);
            // Start Alpine anyway so the page is usable
            Alpine.start();
        });
}
