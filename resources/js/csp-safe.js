// CSP-Safe JavaScript - Modular Orchestrator
// All functionality has been extracted into ES modules under ./modules/

// Core modules
import { escapeHtml } from './modules/utils.js';
import { initThemeSystem, applyTheme, saveThemePreference } from './modules/theme.js';
import { initToastNotifications, initFlashMessages } from './modules/toast.js';
import { initEventDelegation } from './modules/event-delegation.js';

// UI modules
import {
    initNfoModal,
    initConfirmDialogs,
    initImageModal,
    initPreviewModal,
    initMediainfoAndFilelist,
    initDetailsPageImageModal,
    initModalStyles
} from './modules/modals.js';
import { initAdminMenu, initSidebarToggle, initDropdownMenus, initMobileEnhancements } from './modules/navigation.js';
import { initTabSwitcher, initSeasonSwitcher, initProfileTabs, initBinaryBlacklist } from './modules/tabs.js';
import { initSelectRedirects, initLogoutForms, initPasswordVisibilityToggles, initRegexManagement, initImageFallbacks } from './modules/forms.js';

// Feature modules
import { initCartFunctionality } from './modules/cart.js';
import { initSearchAutocomplete } from './modules/search.js';
import { initProfilePage, initProfileEdit, initThemeManagement, initCopyToClipboard } from './modules/profile.js';
import { initContentToggle, initContentDelete, initMoviesLayoutToggle, initQualityFilter, initMyMovies } from './modules/content.js';
import { initReleaseReportButtons, initAdminReleaseReports } from './modules/releases.js';
import { initAuthPages } from './modules/auth.js';

// Admin modules
import { initAdminDashboardCharts, initRecentActivityRefresh } from './modules/admin/dashboard.js';
import { initAdminGroups } from './modules/admin/groups.js';
import { initAdminUserEdit, initAdminSpecificFeatures, initTinyMCE, initVerifyUserModal, initUserListScrollSync } from './modules/admin/features.js';

// Make shared utilities available globally for CSP compliance
window.escapeHtml = escapeHtml;

// Main initialization
document.addEventListener('DOMContentLoaded', function() {
    // Initialize theme system first
    initThemeSystem();

    // Core functionality
    initEventDelegation();
    initToastNotifications();

    // Modals
    initNfoModal();
    initConfirmDialogs();
    initImageModal();
    initPreviewModal();
    initMediainfoAndFilelist();

    // Navigation
    initAdminMenu();
    initSidebarToggle();
    initDropdownMenus();
    initMobileEnhancements();

    // Forms and tabs
    initSelectRedirects();
    initLogoutForms();
    initPasswordVisibilityToggles();
    initRegexManagement();
    initImageFallbacks();
    initTabSwitcher();
    initSeasonSwitcher();
    initBinaryBlacklist();
    initProfileTabs();

    // Features
    initCartFunctionality();
    initSearchAutocomplete();
    initContentToggle();
    initContentDelete();

    // Admin
    initAdminUserEdit();
    initAdminDashboardCharts();
    initAdminGroups();
    initTinyMCE();
    initAdminSpecificFeatures();
    initRecentActivityRefresh();

    // Page-specific functionality
    initMyMovies();
    initAuthPages();
    initProfileEdit();
    initDetailsPageImageModal();
    initMoviesLayoutToggle();
    initProfilePage();
    initCopyToClipboard();
    initVerifyUserModal();
    initQualityFilter();
    initUserListScrollSync();
});

// Theme management and flash messages (run immediately or on DOMContentLoaded)
(function() {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initThemeManagement();
            initFlashMessages();
        });
    } else {
        initThemeManagement();
        initFlashMessages();
    }
})();
