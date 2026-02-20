/**
 * Alpine.js CSP-safe initialization
 *
 * Core components are imported eagerly (used on every/most pages).
 * Page-specific and heavy components are lazy-loaded: only the modules
 * whose x-data names appear in the current page's DOM are fetched.
 * This keeps the initial JS bundle small and improves TTI.
 */
import Alpine from '@alpinejs/csp';

// Make Alpine globally available
window.Alpine = Alpine;

// --- Stores (must register before components that use them) ---
import './stores/theme.js';
import './stores/toast.js';
import './stores/cart.js';

// --- Core UI components (used on every/most pages) ---
import './components/theme-toggle.js';
import './components/back-to-top.js';
import './components/dropdown.js';
import './components/sidebar-toggle.js';
import './components/admin-submenu.js';
import './components/mobile-nav.js';
import './components/password-toggle.js';
import './components/confirm-modal.js';
import './components/confirm-link.js';
import './components/toast-notification.js';
import './components/tab-switcher.js';
import './components/cart-button.js';
import './components/search-autocomplete.js';
import './components/dismissible.js';

// --- Shared utility (backward compat) ---
window.escapeHtml = function(text) {
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return String(text).replace(/[&<>"']/g, m => map[m]);
};

// --- Lazy-load page-specific components, then start Alpine ---
import { loadAndStart } from './lazy-loader.js';
loadAndStart();
