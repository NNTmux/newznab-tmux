/**
 * Tab switching functions extracted from csp-safe.js
 */

// Tab switcher for profile and other pages
export function initTabSwitcher() {
    document.querySelectorAll('[data-tab-trigger]').forEach(function(trigger) {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            const tabId = this.getAttribute('data-tab-trigger');

            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(function(tab) {
                tab.style.display = 'none';
            });

            // Show selected tab
            const selectedTab = document.getElementById(tabId);
            if (selectedTab) {
                selectedTab.style.display = 'block';
            }

            // Update active state
            document.querySelectorAll('[data-tab-trigger]').forEach(function(t) {
                t.classList.remove('active', 'border-blue-500', 'text-blue-600');
                t.classList.add('border-transparent', 'text-gray-500');
            });

            this.classList.remove('border-transparent', 'text-gray-500');
            this.classList.add('active', 'border-blue-500', 'text-blue-600');
        });
    });
}

// Season switcher for series - optimized
export function initSeasonSwitcher() {
    window.switchSeason = function(seasonNumber) {
        // Hide all season content
        document.querySelectorAll('.season-content').forEach(function(content) {
            content.classList.add('hidden');
        });

        // Remove active styling from all tabs
        document.querySelectorAll('.season-tab').forEach(function(tab) {
            tab.classList.remove('border-blue-500', 'text-blue-600');
            tab.classList.add('border-transparent', 'text-gray-500');

            // Update badge styling
            const badge = tab.querySelector('span');
            if (badge) {
                badge.classList.remove('bg-blue-100', 'text-blue-800');
                badge.classList.add('bg-gray-100', 'text-gray-600');
            }
        });

        // Show selected season content
        const selectedContent = document.querySelector('.season-content[data-season="' + seasonNumber + '"]');
        if (selectedContent) {
            selectedContent.classList.remove('hidden');
        }

        // Add active styling to selected tab
        const selectedTab = document.querySelector('.season-tab[data-season="' + seasonNumber + '"]');
        if (selectedTab) {
            selectedTab.classList.remove('border-transparent', 'text-gray-500');
            selectedTab.classList.add('border-blue-500', 'text-blue-600');

            // Update badge styling
            const badge = selectedTab.querySelector('span');
            if (badge) {
                badge.classList.remove('bg-gray-100', 'text-gray-600');
                badge.classList.add('bg-blue-100', 'text-blue-800');
            }
        }
    };
}

// Profile Page Tab Switching
export function initProfileTabs() {
    const tabLinks = document.querySelectorAll('.tab-link');
    const tabContents = document.querySelectorAll('.tab-content');

    if (tabLinks.length === 0 || tabContents.length === 0) {
        return; // Not on a page with tabs
    }

    let chartsInitialized = false;

    tabLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href').substring(1);

            // Update active states on tab links
            tabLinks.forEach(l => {
                l.classList.remove('bg-blue-50', 'text-blue-700', 'font-medium');
                l.classList.add('text-gray-700');
                l.classList.add('dark:text-gray-300');
            });
            this.classList.add('bg-blue-50', 'text-blue-700', 'font-medium');
            this.classList.remove('text-gray-700', 'dark:text-gray-300');

            // Hide all tab contents
            tabContents.forEach(content => {
                content.style.display = 'none';
            });

            // Show selected tab content
            const targetContent = document.getElementById(targetId);
            if (targetContent) {
                targetContent.style.display = 'block';

                // Initialize charts when API tab is shown for the first time
                if (targetId === 'api' && !chartsInitialized) {
                    chartsInitialized = true;
                    // Small delay to ensure the tab is fully visible before rendering charts
                    setTimeout(() => {
                        // Wait for Chart.js to be available
                        let attempts = 0;
                        const maxAttempts = 20; // Try for up to 2 seconds
                        const checkChartJs = setInterval(() => {
                            attempts++;
                            if (typeof Chart !== 'undefined') {
                                clearInterval(checkChartJs);
                                initializeProfileCharts();
                            } else if (attempts >= maxAttempts) {
                                clearInterval(checkChartJs);
                                console.error('Chart.js failed to load within timeout period');
                            }
                        }, 100);
                    }, 50);
                }
            }

            // Update URL hash without scrolling
            history.pushState(null, null, '#' + targetId);
        });
    });

    // Handle initial hash
    const hash = window.location.hash.substring(1);
    if (hash && document.getElementById(hash)) {
        const link = document.querySelector(`a[href="#${hash}"]`);
        if (link) {
            link.click();
        }
    } else if (hash === '' || hash === 'general') {
        // If on general tab or no hash, ensure other tabs are hidden
        tabContents.forEach((content, index) => {
            if (index !== 0) {
                content.style.display = 'none';
            }
        });
    }
}

// Binary blacklist
export function initBinaryBlacklist() {
    // Placeholder - actual implementation depends on existing ajax function
    window.ajax_binaryblacklist_delete = window.ajax_binaryblacklist_delete || function(id) {
        console.log('Delete blacklist:', id);
    };
}
