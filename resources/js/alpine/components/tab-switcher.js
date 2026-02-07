/**
 * Alpine.data('tabSwitcher') - Generic tab switching
 * Alpine.data('seasonSwitcher') - TV series season tabs
 * Alpine.data('profileTabs') - Profile page tabs with Chart.js support
 */
import Alpine from '@alpinejs/csp';

Alpine.data('tabSwitcher', () => ({
    activeTab: '',

    init() {
        // Set default to first tab trigger if none active
        const first = this.$el.querySelector('[data-tab]');
        if (first) this.activeTab = first.getAttribute('data-tab');
    },

    switchTab(tabId) {
        this.activeTab = tabId;
    },

    isActive(tabId) {
        return this.activeTab === tabId;
    }
}));

Alpine.data('seasonSwitcher', () => ({
    activeSeason: '',

    init() {
        const first = this.$el.querySelector('[data-season]');
        if (first) this.activeSeason = first.getAttribute('data-season');
        // Backward compat
        const self = this;
        window.switchSeason = function(n) { self.activeSeason = String(n); };
    },

    switchSeason(n) {
        this.activeSeason = String(n);
    },

    isActive(n) {
        return this.activeSeason === String(n);
    }
}));

Alpine.data('profileTabs', () => ({
    activeTab: 'general',
    _chartsInitialized: false,

    init() {
        const hash = window.location.hash.substring(1);
        if (hash) this.activeTab = hash;
    },

    switchTab(tabId) {
        this.activeTab = tabId;
        history.pushState(null, null, '#' + tabId);

        // Initialize charts when API tab is first shown
        if (tabId === 'api' && !this._chartsInitialized) {
            this._chartsInitialized = true;
            this._waitForChartJs();
        }
    },

    isActive(tabId) {
        return this.activeTab === tabId;
    },

    _waitForChartJs() {
        let attempts = 0;
        const check = setInterval(() => {
            attempts++;
            if (typeof Chart !== 'undefined') {
                clearInterval(check);
                if (typeof initializeProfileCharts === 'function') initializeProfileCharts();
            } else if (attempts >= 20) {
                clearInterval(check);
            }
        }, 100);
    }
}));
