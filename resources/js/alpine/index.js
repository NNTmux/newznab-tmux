/**
 * Alpine.js CSP-safe initialization
 * Registers all stores and components, then starts Alpine.
 */
import Alpine from '@alpinejs/csp';

// Make Alpine globally available
window.Alpine = Alpine;

// --- Stores (must register before components that use them) ---
import './stores/theme.js';
import './stores/toast.js';
import './stores/cart.js';

// --- Core UI components ---
import './components/theme-toggle.js';
import './components/dropdown.js';
import './components/sidebar-toggle.js';
import './components/admin-submenu.js';
import './components/mobile-nav.js';
import './components/password-toggle.js';

// --- Modal components ---
import './components/confirm-modal.js';
import './components/nfo-modal.js';
import './components/image-modal.js';
import './components/preview-modal.js';
import './components/mediainfo-modal.js';
import './components/filelist-modal.js';

// --- Feature components ---
import './components/tab-switcher.js';
import './components/cart-button.js';
import './components/cart-page.js';
import './components/search-autocomplete.js';
import './components/quality-filter.js';
import './components/content-toggle.js';
import './components/movies-layout.js';

// --- Page-specific components ---
import './components/release-report.js';
import './components/profile-edit.js';
import './components/auth-page.js';
import './components/toast-notification.js';

// --- Admin components ---
import './components/admin/dashboard.js';
import './components/admin/groups.js';
import './components/admin/features.js';

// --- Event bridge for existing Blade templates ---
// Provides document-level event delegation for CSS-class-based hooks
// until Blade templates are updated with x-data directives
import './components/event-bridge.js';

// --- Shared utility (backward compat) ---
window.escapeHtml = function(text) {
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return String(text).replace(/[&<>"']/g, m => map[m]);
};

// Start Alpine
Alpine.start();
